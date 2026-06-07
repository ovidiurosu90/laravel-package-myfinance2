<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
