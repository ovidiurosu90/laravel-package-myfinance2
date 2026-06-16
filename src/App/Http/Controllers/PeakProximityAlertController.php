<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;
use ovidiuro\myfinance2\App\Models\PeakProximityAlertSetting;
use ovidiuro\myfinance2\App\Models\PeakProximityNotification;
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
     * held symbol with its enabled/disabled state.
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

        $symbols   = $this->_openSymbolsForUser($userId);
        $settings  = PeakProximityAlertSetting::where('user_id', $userId)->get()->keyBy('symbol');
        $notifById = $this->_notificationsSummary($userId);

        $startOfDay = now()->startOfDay();

        $items = [];
        foreach ($symbols as $symbol) {
            $setting = $settings->get($symbol);
            $enabled = $setting !== null && $setting->status === PeakProximityAlertSetting::ENABLED;

            if ($view === 'active' && !$enabled) {
                continue;
            }

            $summary = $notifById->get($symbol);
            $total   = $summary['total'] ?? 0;
            $recent  = $summary['recent'] ?? collect();
            $last    = $summary['last'] ?? null;

            $items[] = [
                'symbol'          => $symbol,
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
            'items' => $items,
            'view'  => $view,
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

        $symbols = $this->_resolveHeldSymbols($request, $userId);
        if (empty($symbols)) {
            return $this->_redirect($view)->with('error', 'No matching held symbols to re-arm.');
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
            $dismissed = PeakProximityAlertEvent::where('user_id', $userId)
                ->where('status', PeakProximityAlertEvent::STATUS_DISMISSED)
                ->orderByDesc('dismissed_at')
                ->limit(500)
                ->get();

            return view('myfinance2::peakproximityalerts.inbox.dashboard', [
                'show'       => 'dismissed',
                'actionable' => collect(),
                'info'       => collect(),
                'dismissed'  => $dismissed,
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
     * Dismiss every OPEN event for the user, or only the informational ones when scope=info (keeping
     * the actionable ones in the inbox).
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function dismissAll(Request $request)
    {
        $userId   = (int) auth()->user()->id;
        $onlyInfo = $request->input('scope') === 'info';

        $query = PeakProximityAlertEvent::where('user_id', $userId)
            ->where('status', PeakProximityAlertEvent::STATUS_OPEN);

        if ($onlyInfo) {
            $query->where('classification', PeakProximityAlertEvent::CLASS_INFO);
        }

        $count = $query->update([
            'status'       => PeakProximityAlertEvent::STATUS_DISMISSED,
            'dismissed_at' => now(),
        ]);

        $what = $onlyInfo ? 'informational alert(s)' : 'alert(s)';

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

        $symbols = $this->_resolveHeldSymbols($request, $userId);
        if (empty($symbols)) {
            return $this->_redirect($view)->with('error', 'No matching held symbols to update.');
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
     * Parse the posted symbols[] and keep only the ones the user actually holds (the page never
     * lists anything else, so this rejects spoofed or stale input).
     *
     * @param Request $request
     * @param int     $userId
     *
     * @return array
     */
    private function _resolveHeldSymbols(Request $request, int $userId): array
    {
        $symbols = array_values(array_unique(array_filter(array_map(
            'trim',
            (array) $request->input('symbols', [])
        ))));

        if (empty($symbols)) {
            return [];
        }

        return array_values(array_intersect($symbols, $this->_openSymbolsForUser($userId)));
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
