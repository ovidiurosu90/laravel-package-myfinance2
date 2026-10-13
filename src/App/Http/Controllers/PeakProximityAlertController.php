<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;
use ovidiuro\myfinance2\App\Models\PeakProximityAlertSetting;
use ovidiuro\myfinance2\App\Models\PeakProximityNotification;
use ovidiuro\myfinance2\App\Models\SymbolTierState;
use ovidiuro\myfinance2\App\Services\TierSignalChip;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Models\Trade;

/**
 * Opt-in management UI for the daily peak-proximity exit-hint alerts.
 *
 * These alerts are OFF by default for every user: a held symbol only fires once the user enables it
 * here (individually or in bulk). The page is user-scoped; symbols come from the user's open BUY
 * positions automatically, so there is no create / edit / delete. Enable / disable accept an optional
 * "until" date for temporary states (enable-until / pause-until), auto-reverted by the model.
 */
class PeakProximityAlertController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the per-symbol opt-in dashboard. Active view lists only enabled symbols; All lists every
     * held symbol, then every symbol traded but no longer held (only a BUY can alert on it: range-bound or a
     * tier signal's rare low), with its enabled/disabled state.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $view   = $request->query('view', 'active') === 'all' ? 'all' : 'active';
        $userId = (int) auth()->user()->id;

        PeakProximityAlertSetting::normalizeExpired($userId);

        $held      = $this->_openSymbolsForUser($userId);
        $symbols   = array_merge($held, $this->_exitedSymbolsForUser($userId, $held));
        $settings  = PeakProximityAlertSetting::where('user_id', $userId)->get()->keyBy('symbol');
        $notifById = $this->_notificationsSummary($userId);
        $readings  = $this->_positionReadings($userId);

        // Range-bound, weak-tier-in-profit and tier signal symbols that could raise a TRIM / ADD / BUY, TAKE PROFIT
        // or tier signal alert but are not enabled, for the note above the table (easy to miss in the Active view,
        // which lists enabled symbols only).
        $rangeNotEnabled = [];

        $startOfDay = now()->startOfDay();

        $items = [];
        foreach ($symbols as $symbol) {
            $setting = $settings->get($symbol);
            $enabled = $setting !== null && $setting->status === PeakProximityAlertSetting::ENABLED;
            $range   = $readings[$symbol]['range_bound'] ?? null;
            $profit  = $readings[$symbol]['take_profit'] ?? null;
            // The tier signal chip as the watchlist shows it (none when it defers to the other two).
            $signal  = TierSignalChip::shown(['tier_signal_chip' => $readings[$symbol]['tier_signal'] ?? null]);
            $alerts  = TierSignalChip::alertable(['tier_signal_chip' => $signal]) !== null;
            if (($range !== null || $profit !== null || $alerts) && !$enabled) {
                $rangeNotEnabled[] = [
                    'symbol' => $symbol, 'range_bound' => $range, 'take_profit' => $profit,
                    'tier_signal' => $alerts ? $signal : null,
                ];
            }

            if ($view === 'active' && !$enabled) {
                continue;
            }

            $summary = $notifById->get($symbol);
            $total   = $summary['total'] ?? 0;
            $recent  = $summary['recent'] ?? collect();
            $last    = $summary['last'] ?? null;

            $items[] = [
                'symbol'          => $symbol,
                'held'            => in_array($symbol, $held, true),
                'range_bound'     => $range,
                'take_profit'     => $profit,
                'tier_signal'     => $signal,
                'enabled'         => $enabled,
                'until'           => $setting?->until,
                'alert_total'     => $total,
                'recent_alerts'   => $recent,
                'last_alerted'    => $last,
                // Already emailed today: the once-per-day throttle is blocking further runs, so the
                // row can be "re-armed" by clearing today's SENT record.
                'throttled_today' => $last && $last->sent_at && $last->sent_at->gte($startOfDay),
            ];
        }

        return view('myfinance2::peakproximityalerts.crud.dashboard', [
            'items'           => $items,
            'view'            => $view,
            'rangeNotEnabled' => $rangeNotEnabled,
        ]);
    }

    /**
     * Enable peak-proximity alerts for the posted symbols (optionally until a date).
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function enable(Request $request)
    {
        return $this->_setStatus($request, PeakProximityAlertSetting::ENABLED);
    }

    /**
     * Disable (pause) peak-proximity alerts for the posted symbols (optionally until a date).
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function disable(Request $request)
    {
        return $this->_setStatus($request, PeakProximityAlertSetting::DISABLED);
    }

    /**
     * Re-arm the posted symbols by clearing today's SENT throttle record, so the next run can email
     * them again the same day. Past history (earlier days) is left intact.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function rearm(Request $request)
    {
        $view   = $request->input('view', 'active') === 'all' ? 'all' : 'active';
        $userId = (int) auth()->user()->id;

        $symbols = $this->_resolveListedSymbols($request, $userId);
        if (empty($symbols)) {
            return $this->_redirect($view)->with('error', 'No matching traded symbols to re-arm.');
        }

        $deleted = PeakProximityNotification::where('user_id', $userId)
            ->whereIn('symbol', $symbols)
            ->where('status', 'SENT')
            ->where('sent_at', '>=', now()->startOfDay())
            ->delete();

        if ($deleted === 0) {
            return $this->_redirect($view)->with('error', 'Nothing to re-arm; no alert was sent today.');
        }

        return $this->_redirect($view)->with('success',
            "{$deleted} symbol(s) re-armed; the next run can alert them again today.");
    }

    /**
     * The front-end alerts inbox. The Open view lists the user's OPEN alert events, sorted by severity
     * (HIGH first) then newest, split into "action suggested" (ACTIONABLE) and "for your awareness"
     * (INFO); events persist here until dismissed, even after a symbol drifts off peak. The Dismissed
     * view (?show=dismissed) lists previously dismissed events, newest first.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function inbox(Request $request)
    {
        $userId = (int) auth()->user()->id;
        $show   = $request->query('show') === 'dismissed' ? 'dismissed' : 'open';

        if ($show === 'dismissed') {
            $dismissedEvents = PeakProximityAlertEvent::where('user_id', $userId)
                ->where('status', PeakProximityAlertEvent::STATUS_DISMISSED)
                ->orderByDesc('dismissed_at')
                ->limit(500)
                ->get();

            // Group by dismissal day (newest first, since the rows are already ordered desc) so the
            // view can show "Today / Yesterday / <date>" sections and you can see what you dismissed today.
            $dismissed = $dismissedEvents->groupBy(
                fn ($event) => $event->dismissed_at?->toDateString() ?? 'unknown'
            );

            return view('myfinance2::peakproximityalerts.inbox.dashboard', [
                'show'           => 'dismissed',
                'actionable'     => collect(),
                'info'           => collect(),
                'dismissed'      => $dismissed,
                'dismissedCount' => $dismissedEvents->count(),
            ]);
        }

        $rank = [
            PeakProximityAlertEvent::SEVERITY_HIGH   => 0,
            PeakProximityAlertEvent::SEVERITY_MEDIUM => 1,
            PeakProximityAlertEvent::SEVERITY_LOW    => 2,
        ];

        $events = PeakProximityAlertEvent::where('user_id', $userId)
            ->where('status', PeakProximityAlertEvent::STATUS_OPEN)
            ->get()
            ->sort(function ($a, $b) use ($rank)
            {
                $byRank = ($rank[$a->severity] ?? 9) <=> ($rank[$b->severity] ?? 9);

                return $byRank !== 0 ? $byRank : ($b->opened_at <=> $a->opened_at);
            })
            ->values();

        return view('myfinance2::peakproximityalerts.inbox.dashboard', [
            'show'       => 'open',
            'actionable' => $events->where('classification', PeakProximityAlertEvent::CLASS_ACTIONABLE)->values(),
            'info'       => $events->where('classification', PeakProximityAlertEvent::CLASS_INFO)->values(),
            'dismissed'  => collect(),
        ]);
    }

    /**
     * Dismiss the posted OPEN alert events by id (single or bulk). Dismissing ends the email episode
     * and removes the card from the inbox; a later re-trigger opens a fresh event.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function dismiss(Request $request)
    {
        $userId = (int) auth()->user()->id;

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        if (empty($ids)) {
            return redirect()->route('myfinance2::peak-proximity-alerts.inbox')
                ->with('error', 'No alerts selected to dismiss.');
        }

        $count = PeakProximityAlertEvent::where('user_id', $userId)
            ->whereIn('id', $ids)
            ->where('status', PeakProximityAlertEvent::STATUS_OPEN)
            ->update([
                'status'       => PeakProximityAlertEvent::STATUS_DISMISSED,
                'dismissed_at' => now(),
            ]);

        return redirect()->route('myfinance2::peak-proximity-alerts.inbox')
            ->with('success', "{$count} alert(s) dismissed.");
    }

    /**
     * Dismiss every OPEN event for the user, or only one section when scope is given: scope=actionable
     * keeps the informational ones, scope=info keeps the actionable ones. Each of the inbox's two
     * "Dismiss all" buttons targets its own section, so they no longer overlap.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function dismissAll(Request $request)
    {
        $userId = (int) auth()->user()->id;
        $scope  = $request->input('scope');
        $scope  = is_string($scope) ? $scope : null; // e.g. scope[]=... would fatal the array lookups

        $classification = [
            'actionable' => PeakProximityAlertEvent::CLASS_ACTIONABLE,
            'info'       => PeakProximityAlertEvent::CLASS_INFO,
        ][$scope] ?? null;

        $query = PeakProximityAlertEvent::where('user_id', $userId)
            ->where('status', PeakProximityAlertEvent::STATUS_OPEN);

        if ($classification !== null) {
            $query->where('classification', $classification);
        }

        $count = $query->update([
            'status'       => PeakProximityAlertEvent::STATUS_DISMISSED,
            'dismissed_at' => now(),
        ]);

        $what = [
            'actionable' => 'action-suggested alert(s)',
            'info'       => 'informational alert(s)',
        ][$scope] ?? 'alert(s)';

        return redirect()->route('myfinance2::peak-proximity-alerts.inbox')
            ->with('success', "{$count} {$what} dismissed.");
    }

    /**
     * Upsert the given status for each posted symbol, validating the optional shared "until" date and
     * restricting to symbols the user actually holds. Bulk and single-row both post symbols[].
     *
     * @param Request $request
     * @param string  $status  ENABLED | DISABLED
     *
     * @return \Illuminate\Http\Response
     */
    private function _setStatus(Request $request, string $status)
    {
        $view   = $request->input('view', 'active') === 'all' ? 'all' : 'active';
        $userId = (int) auth()->user()->id;

        $validator = Validator::make($request->all(), [
            'until' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return $this->_redirect($view)->with('error',
                'Invalid "until" date; it must be a future date or left blank for permanent.');
        }

        $symbols = $this->_resolveListedSymbols($request, $userId);
        if (empty($symbols)) {
            return $this->_redirect($view)->with('error', 'No matching traded symbols to update.');
        }

        $until = $request->input('until') ?: null;

        foreach ($symbols as $symbol) {
            PeakProximityAlertSetting::updateOrCreate(
                ['user_id' => $userId, 'symbol' => $symbol],
                ['status' => $status, 'until' => $until]
            );
        }

        $count = count($symbols);
        $verb  = $status === PeakProximityAlertSetting::ENABLED ? 'enabled' : 'disabled';
        $suffix = $until ? " until {$until}" : '';

        return $this->_redirect($view)->with('success', "{$count} symbol(s) {$verb}{$suffix}.");
    }

    /**
     * Parse the posted symbols[] and keep only the ones the page lists, held or traded before (it never
     * lists anything else, so this rejects spoofed or stale input).
     *
     * @param Request $request
     * @param int     $userId
     *
     * @return array
     */
    private function _resolveListedSymbols(Request $request, int $userId): array
    {
        $symbols = array_values(array_unique(array_filter(array_map(
            'trim',
            (array) $request->input('symbols', [])
        ))));

        if (empty($symbols)) {
            return [];
        }

        $held   = $this->_openSymbolsForUser($userId);
        $listed = array_merge($held, $this->_exitedSymbolsForUser($userId, $held));

        return array_values(array_intersect($symbols, $listed));
    }

    /**
     * Redirect back to the index preserving the current view.
     *
     * @param string $view
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    private function _redirect(string $view)
    {
        return redirect()->route('myfinance2::peak-proximity-alerts.index', ['view' => $view]);
    }

    /**
     * Distinct OPEN BUY symbols for the user, sorted alphabetically. Mirrors
     * AlertService::_getOpenSymbolsForUser (the user scope is bypassed and user_id pinned explicitly).
     *
     * @param int $userId
     *
     * @return array
     */
    private function _openSymbolsForUser(int $userId): array
    {
        $symbols = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->distinct()
            ->pluck('symbol')
            ->toArray();

        sort($symbols);

        return $symbols;
    }

    /**
     * The range-bound, weak-tier-in-profit and tier signal readings of each of the user's symbols as of the last
     * hourly cron run (symbol_tier_states), keyed by symbol; symbols with none are left out. Read from the stored
     * state so the page stays light (no categorization run).
     *
     * @param int $userId
     *
     * @return array<string, array{range_bound: ?array, take_profit: ?array, tier_signal: ?array}>
     */
    private function _positionReadings(int $userId): array
    {
        return SymbolTierState::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNotNull('range_bound')->orWhereNotNull('take_profit')
                ->orWhereNotNull('tier_signal'))
            ->get(['symbol', 'range_bound', 'take_profit', 'tier_signal'])
            ->filter(fn (SymbolTierState $state) => !empty($state->range_bound) || !empty($state->take_profit)
                || !empty($state->tier_signal))
            ->mapWithKeys(fn (SymbolTierState $state) => [$state->symbol => [
                'range_bound' => $state->range_bound ?: null,
                'take_profit' => $state->take_profit ?: null,
                'tier_signal' => $state->tier_signal ?: null,
            ]])
            ->all();
    }

    /**
     * Distinct symbols the user traded but no longer holds (not in $held), sorted alphabetically: a BUY alert
     * (range-bound, or a tier signal's rare low) can fire on them.
     *
     * @param int      $userId
     * @param string[] $held
     *
     * @return string[]
     */
    private function _exitedSymbolsForUser(int $userId, array $held): array
    {
        $symbols = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->whereNotIn('symbol', $held)
            ->distinct()
            ->pluck('symbol')
            ->toArray();

        sort($symbols);

        return $symbols;
    }

    /**
     * Per-symbol SENT notification summary for the user, keyed by symbol. Each entry holds the total
     * send count, the 3 most recent rows, and the latest row, feeding the "Alerts" column on the page
     * (count + last 3 timestamps + "view history" link).
     *
     * @param int $userId
     *
     * @return \Illuminate\Support\Collection
     */
    private function _notificationsSummary(int $userId)
    {
        return PeakProximityNotification::where('user_id', $userId)
            ->where('status', 'SENT')
            ->orderBy('sent_at', 'desc')
            ->get()
            ->groupBy('symbol')
            ->map(fn ($group) => [
                'total'  => $group->count(),
                'recent' => $group->take(3),
                'last'   => $group->first(),
            ]);
    }
}
