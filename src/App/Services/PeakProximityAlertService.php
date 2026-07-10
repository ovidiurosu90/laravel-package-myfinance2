<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;
use ovidiuro\myfinance2\App\Models\PeakProximityAlertSetting;
use ovidiuro\myfinance2\App\Models\PeakProximityNotification;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\Mail\PeakProximityAlert;

/**
 * Peak-proximity exit-hint alert engine.
 *
 * Daily job: for each user with open positions, run the watchlist symbols dashboard and email a
 * one-per-day-per-symbol alert when an owned position has rallied to within N% (default 5%) of its
 * peak in ANY of the 3M / 6M / 1Y / 2Y windows. The email reproduces the data shown in the three
 * cards rendered for that symbol on the watchlist-symbols page.
 *
 * Unlike AlertService (minutely, budgeted), this is a heavy daily pass: WatchlistSymbolsDashboard
 * recomputes positions, performance, categorization and technical indicators per user, so the
 * caller must already have set the acting user (auth()->setUser) before calling evaluateForUser().
 */
final class PeakProximityAlertService
{
    /**
     * Distinct user IDs with at least one open BUY trade.
     * Disables the user scope briefly so the CLI sees every user's positions.
     *
     * @return array
     */
    public function getUserIdsWithOpenPositions(): array
    {
        AssignedToUserScope::disable();
        $userIds = Trade::where('status', 'OPEN')
            ->where('action', 'BUY')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
        AssignedToUserScope::enable();

        return $userIds;
    }

    /**
     * Distinct user IDs who have at least one ENABLED peak-proximity setting AND at least one open
     * position. Because the alerts are opt-in (default off), an --all-users run only needs to build
     * the heavy dashboard for these users; everyone else is a guaranteed no-op.
     *
     * @return array
     */
    public function getUserIdsWithEnabledAlerts(): array
    {
        $enabledUserIds = PeakProximityAlertSetting::where('status', PeakProximityAlertSetting::ENABLED)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (empty($enabledUserIds)) {
            return [];
        }

        $openUserIds = $this->getUserIdsWithOpenPositions();

        return array_values(array_intersect($enabledUserIds, $openUserIds));
    }

    /**
     * Evaluate peak-proximity alerts for a single user.
     *
     * The acting user must already be set by the caller (auth()->setUser), since
     * WatchlistSymbolsDashboard relies on the user scope and auth()->id().
     *
     * Returns stats: ['processed', 'triggered', 'skipped', 'failed', 'symbols'].
     *
     * @param int        $userId
     * @param bool       $dryRun        When true, count and log but do not send or record
     * @param float|null $thresholdPct  Override config threshold (default 5)
     * @param array|null $filterSymbols When set, only process these symbols
     *
     * @return array
     */
    public function evaluateForUser(
        int $userId,
        bool $dryRun = false,
        ?float $thresholdPct = null,
        ?array $filterSymbols = null
    ): array
    {
        $dashboard = (new WatchlistSymbolsDashboard())->handle();

        return $this->evaluateItems($userId, $dashboard['items'] ?? [], $dryRun, $thresholdPct, $filterSymbols);
    }

    /**
     * Evaluate already-built dashboard items for a user. Split out from evaluateForUser so the
     * trigger / throttle / send logic can be exercised without the heavy, network-bound dashboard.
     *
     * @param int        $userId
     * @param array      $items         symbol => dashboard quoteData
     * @param bool       $dryRun
     * @param float|null $thresholdPct
     * @param array|null $filterSymbols
     *
     * @return array
     */
    public function evaluateItems(
        int $userId,
        array $items,
        bool $dryRun = false,
        ?float $thresholdPct = null,
        ?array $filterSymbols = null
    ): array
    {
        $stats = [
            'processed' => 0,
            'triggered' => 0,
            'info'      => 0,
            'skipped'   => 0,
            'failed'    => 0,
            'symbols'   => [],
        ];

        $notifiedToday  = $this->_getNotifiedTodaySymbols($userId);
        $enabledSymbols = $this->_enabledSymbols($userId);

        foreach ($items as $symbol => $quoteData) {
            if (empty($quoteData['open_positions'])) {
                continue; // owned positions only
            }
            if ($filterSymbols && !in_array($symbol, $filterSymbols, true)) {
                continue;
            }
            if (!in_array($symbol, $enabledSymbols, true)) {
                continue; // not opted in (default off)
            }

            $triggered = $this->_triggeredWindows($quoteData, $thresholdPct);
            if (empty($triggered)) {
                // Not near peak today. An existing OPEN event is left untouched: it persists in the
                // inbox until the user dismisses it, even after the symbol drifts off peak.
                continue;
            }

            $stats['processed']++;

            // Classify (tier gate + 3M-as-context) and upsert the inbox event (INFO entries too, so
            // they show in the inbox). The event also carries the email cadence state.
            $class = $this->_classify($quoteData, $triggered);

            // Same-day dismissal guard: if the user already dismissed an equivalent alert for this
            // symbol earlier today, do not re-open it (and do not re-email it). This keeps a dismissed
            // card from immediately popping back into the inbox on a same-day re-run. A changed
            // condition (a new window near peak) is not equivalent, so it still opens a fresh event.
            if ($this->_dismissedTodayMatches($userId, $symbol, $triggered, $class)) {
                $stats['skipped']++;
                continue;
            }

            $event = $this->_upsertEvent($userId, $symbol, $quoteData, $triggered, $class, $dryRun);

            if (!$class['actionable']) {
                $stats['info']++;
                continue; // INFO: shown in the inbox, never emailed
            }

            if (in_array($symbol, $notifiedToday, true)) {
                $stats['skipped']++; // daily double-send guard
                continue;
            }

            if (!$this->_shouldEmail($event, $class)) {
                $stats['skipped']++; // cadence not due yet
                continue;
            }

            if ($dryRun) {
                $stats['triggered']++;
                $stats['symbols'][] = $symbol;
                Log::info(
                    "PeakProximityAlertService: [dry-run] user {$userId} {$symbol} near peak"
                    . ' (' . implode(',', array_keys($triggered)) . '), tier ' . ($class['tier'] ?? 'n/a')
                );
                continue;
            }

            // First email of an episode vs a cadence reminder (a previously emailed open event).
            $isReminder = $event !== null && (int) $event->email_count > 0;

            if ($this->_sendEmail($symbol, $quoteData, $triggered, $userId, $isReminder, $thresholdPct)) {
                $this->_recordEmailOnEvent($event, $class);
                $stats['triggered']++;
                $stats['symbols'][] = $symbol;
                $notifiedToday[] = $symbol;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Classify a near-peak symbol for the exit-focused gate, severity and cadence.
     *
     * The email gate is driven by the gain-based effective_tier only: "near peak" is an exit aid, so
     * an alert is actionable only for a position you would actually consider trimming (a weak tier)
     * whose near-peak signal lands in a meaningful (6M/1Y/2Y) window. A 3M-only trigger is context.
     * The HOLD/EXIT action is captured for display but never gates. When exit_focused is off, the
     * gate falls back to "any window near peak".
     *
     * @param array $quoteData
     * @param array $triggered  window label => exit-zone entry (all near-peak windows, incl 3M)
     *
     * @return array
     */
    private function _classify(array $quoteData, array $triggered): array
    {
        $cat    = $quoteData['categorization'] ?? [];
        $tier   = $cat['effective_tier'] ?? null;
        $action = $cat['action'] ?? null;

        $meaningfulWindows = config('alerts.peak_proximity.meaningful_windows', ['6m', '1y', '2y']);
        $meaningful        = array_values(array_intersect(array_keys($triggered), $meaningfulWindows));

        $exitTiers  = array_map('strtoupper', config('alerts.peak_proximity.exit_tiers', ['RUST', 'BRONZE']));
        $exitWorthy = $tier !== null && in_array(strtoupper((string) $tier), $exitTiers, true);

        $exitFocused = (bool) config('alerts.peak_proximity.exit_focused', true);
        if ($exitFocused) {
            $actionable      = !empty($meaningful) && $exitWorthy;
            $cadenceWindows  = $meaningful;
        } else {
            $actionable      = true;
            $cadenceWindows  = array_keys($triggered);
        }

        $rsi        = $quoteData['technical_indicators']['rsi'] ?? null;
        $overbought = $rsi !== null && (float) $rsi >= (float) config('alerts.peak_proximity.rsi_overbought', 70);

        if (!$actionable) {
            $severity = PeakProximityAlertEvent::SEVERITY_LOW;
        } elseif (count($meaningful) >= 2 || $overbought) {
            $severity = PeakProximityAlertEvent::SEVERITY_HIGH;
        } else {
            $severity = PeakProximityAlertEvent::SEVERITY_MEDIUM;
        }

        return [
            'tier'            => $tier,
            'action'          => $action,
            'exit_worthy'     => $exitWorthy,
            'actionable'      => $actionable,
            'severity'        => $severity,
            'meaningful'      => $meaningful,
            'cadence_windows' => $cadenceWindows,
        ];
    }

    /**
     * Build the JSON snapshot of the email's "Summary" block for the inbox card: currency, current
     * price, a per-triggered-window table (from-peak %, peak price, peak date) ordered largest window
     * first, and the unrealized "sell now" gain. Captured at engine time so the inbox renders it
     * without live quote calls.
     *
     * @param array $quoteData
     * @param array $triggered  window label => exit-zone entry
     *
     * @return array
     */
    private function _buildSummary(array $quoteData, array $triggered): array
    {
        $price = isset($quoteData['price']) ? (float) $quoteData['price'] : null;
        $cur   = isset($quoteData['tradeCurrencyModel']->display_code)
            ? html_entity_decode((string) $quoteData['tradeCurrencyModel']->display_code, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '€';

        // Every window (not just the triggered ones) with its trigger target, mirroring the email.
        $thresholds = [];
        foreach (config('alerts.peak_proximity.windows', ['3m', '6m', '1y', '2y']) as $window) {
            $thresholds[$window] = $this->_windowThreshold($window, null);
        }
        $exitZones = $quoteData['categorization']['exit_zones'] ?? [];
        $windows   = self::buildSummaryWindows($price, $exitZones, array_keys($triggered), $thresholds);

        return [
            'currency'            => $cur,
            'price'               => $price,
            'windows'             => $windows,
            'near_count'          => count($triggered),
            'unrealized_gain_eur' => $quoteData['unrealized_gain_eur'] ?? null,
            'unrealized_gain_pct' => $quoteData['unrealized_gain_pct'] ?? null,
            'today_gain_eur'      => $quoteData['today_gain_eur'] ?? null,
            'today_gain_pct'      => $quoteData['today_gain_pct'] ?? null,
        ];
    }

    /**
     * Build the per-window rows shown in the email and the inbox Summary: every window (shortest
     * first, 3M -> 2Y), triggered or not, with its native-currency peak (derived from proximity so it
     * is comparable to the live price), its trigger target (the window threshold % below the peak) and
     * how far the price still has to run to reach it. Pure and side-effect free, shared by the
     * mailable and the inbox snapshot so the math lives in one place.
     *
     * @param float|null $price            current native price
     * @param array      $exitZones        window key => exit-zone entry (proximity_pct, peak date)
     * @param array      $triggeredKeys    window keys currently near peak
     * @param array      $windowThresholds window key => trigger threshold (%)
     *
     * @return array
     */
    public static function buildSummaryWindows(
        ?float $price,
        array $exitZones,
        array $triggeredKeys,
        array $windowThresholds
    ): array
    {
        $labels = ['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'];

        $rows = [];
        foreach (['3m', '6m', '1y', '2y'] as $window) {
            $zone   = $exitZones[$window] ?? null;
            $prox   = isset($zone['proximity_pct']) ? (float) $zone['proximity_pct'] : null;
            $peak   = ($price !== null && $prox !== null && (1.0 + $prox / 100.0) != 0.0)
                ? $price / (1.0 + $prox / 100.0)
                : null;
            $thr    = isset($windowThresholds[$window]) ? (float) $windowThresholds[$window] : null;
            $target = ($peak !== null && $thr !== null) ? $peak * (1.0 - $thr / 100.0) : null;
            $toGo   = ($target !== null && $price !== null && $price > 0.0)
                ? ($target / $price - 1.0) * 100.0
                : null;

            $rows[] = [
                'label'  => $labels[$window] ?? strtoupper($window),
                'prox'   => $prox,
                'peak'   => $peak,
                'date'   => $zone['peak_price_date'] ?? null,
                'near'   => in_array($window, $triggeredKeys, true),
                'thr'    => $thr,
                'target' => $target,
                'to_go'  => $toGo,
            ];
        }

        return $rows;
    }

    /**
     * Create or refresh the OPEN inbox event for this user + symbol, recording the latest snapshot
     * and last_seen_at. Returns the OPEN event, or null on a dry run (no DB writes).
     *
     * @param int   $userId
     * @param string $symbol
     * @param array $quoteData
     * @param array $triggered
     * @param array $class
     * @param bool  $dryRun
     *
     * @return PeakProximityAlertEvent|null
     */
    private function _upsertEvent(
        int $userId,
        string $symbol,
        array $quoteData,
        array $triggered,
        array $class,
        bool $dryRun
    ): ?PeakProximityAlertEvent
    {
        if ($dryRun) {
            return null;
        }

        $snapshot = [
            'classification' => $class['actionable']
                ? PeakProximityAlertEvent::CLASS_ACTIONABLE
                : PeakProximityAlertEvent::CLASS_INFO,
            'severity'              => $class['severity'],
            'effective_tier'        => $class['tier'],
            'head_action'           => $class['action'],
            'triggered_windows'     => implode(',', array_keys($triggered)),
            'meaningful_windows'    => implode(',', $class['meaningful']) ?: null,
            'closest_proximity_pct' => $this->_closestProximityPct($triggered),
            'peak_dates'            => $this->_peakDatesString($triggered),
            'summary'               => $this->_buildSummary($quoteData, $triggered),
            'current_price'         => isset($quoteData['price']) ? (float) $quoteData['price'] : null,
            'last_seen_at'          => now(),
        ];

        $event = PeakProximityAlertEvent::where('user_id', $userId)
            ->where('symbol', $symbol)
            ->where('status', PeakProximityAlertEvent::STATUS_OPEN)
            ->first();

        if ($event === null) {
            return PeakProximityAlertEvent::create(array_merge($snapshot, [
                'user_id'                       => $userId,
                'symbol'                        => $symbol,
                'status'                        => PeakProximityAlertEvent::STATUS_OPEN,
                'opened_at'                     => now(),
                'last_emailed_meaningful_count' => 0,
                'email_count'                   => 0,
            ]));
        }

        $event->fill($snapshot)->save();

        return $event;
    }

    /**
     * Whether this run should send an email for an actionable, OPEN event. Implements the escalating,
     * confluence-driven cadence: a new meaningful window crossing into near-peak emails immediately;
     * otherwise the reminder interval shrinks as more long windows are near peak at once.
     *
     * @param PeakProximityAlertEvent|null $event  null on a dry run / brand-new episode -> would email
     * @param array                        $class
     *
     * @return bool
     */
    private function _shouldEmail(?PeakProximityAlertEvent $event, array $class): bool
    {
        if ($event === null || $event->last_emailed_at === null) {
            return true; // first email of the episode
        }

        $cadenceWindows = $class['cadence_windows'];
        $count          = count($cadenceWindows);

        // Escalate immediately when confluence grows or a new long window joins (your "2 -> 3 peaks").
        if ($count > (int) $event->last_emailed_meaningful_count) {
            return true;
        }
        $previous   = array_filter(explode(',', (string) $event->last_emailed_windows));
        $newWindows = array_diff($cadenceWindows, $previous);
        if (!empty($newWindows)) {
            return true;
        }

        return $event->last_emailed_at->lte(now()->subDays($this->_reminderInterval($count)));
    }

    /**
     * The reminder interval (days) for a given confluence (number of long windows near peak). More
     * confluence -> shorter interval. A confluence above the largest configured key clamps to the
     * shortest interval; zero or unknown falls back to the default.
     *
     * @param int $count
     *
     * @return int
     */
    private function _reminderInterval(int $count): int
    {
        $map = config('alerts.peak_proximity.reminder_days_by_confluence', [1 => 7, 2 => 3, 3 => 1]);

        if (isset($map[$count])) {
            return (int) $map[$count];
        }

        if (!empty($map) && $count > 0) {
            $maxKey = max(array_keys($map));
            if ($count > $maxKey) {
                return (int) $map[$maxKey];
            }
        }

        return (int) config('alerts.peak_proximity.reminder_days_default', 7);
    }

    /**
     * Record that an email was just sent on the event: bump the cadence high-water mark so the next
     * run only re-emails on a fresh window crossing or once the interval elapses.
     *
     * @param PeakProximityAlertEvent|null $event
     * @param array                        $class
     *
     * @return void
     */
    private function _recordEmailOnEvent(?PeakProximityAlertEvent $event, array $class): void
    {
        if ($event === null) {
            return;
        }

        $cadenceWindows = $class['cadence_windows'];

        $event->last_emailed_at               = now();
        $event->email_count                   = (int) $event->email_count + 1;
        $event->last_emailed_meaningful_count = count($cadenceWindows);
        $event->last_emailed_windows          = implode(',', $cadenceWindows) ?: null;
        $event->save();
    }

    /**
     * The triggered windows for a symbol: those whose proximity_pct is within that window's
     * threshold of the peak (proximity_pct >= -threshold). Each window has its own threshold
     * (tighter near-term, looser long-term); $override, when set (the --threshold CLI flag),
     * applies uniformly to every window instead. Keyed by window label (3m/6m/1y/2y), each
     * keeping the full exit-zone entry (proximity_pct, peak_price_date, peak_price_eur, in_zone).
     *
     * @param array      $quoteData
     * @param float|null $override  uniform threshold for all windows, or null for per-window
     *
     * @return array
     */
    private function _triggeredWindows(array $quoteData, ?float $override = null): array
    {
        $exitZones = $quoteData['categorization']['exit_zones'] ?? [];
        $windows   = config('alerts.peak_proximity.windows', ['3m', '6m', '1y', '2y']);

        $triggered = [];
        foreach ($windows as $window) {
            $proximity = $exitZones[$window]['proximity_pct'] ?? null;
            if ($proximity !== null && $proximity >= -$this->_windowThreshold($window, $override)) {
                $triggered[$window] = $exitZones[$window];
            }
        }

        return $triggered;
    }

    /**
     * Resolve the proximity threshold (%) for a window: the --threshold override when provided,
     * else the per-window config value, else the global fallback.
     *
     * @param string     $window
     * @param float|null $override
     *
     * @return float
     */
    private function _windowThreshold(string $window, ?float $override): float
    {
        if ($override !== null) {
            return $override;
        }

        $perWindow = config("alerts.peak_proximity.window_thresholds.{$window}");
        if ($perWindow !== null) {
            return (float) $perWindow;
        }

        return (float) config('alerts.peak_proximity.threshold_pct', 5);
    }

    /**
     * Symbols this user has opted in to (status ENABLED) on the /peak-proximity-alerts page. The
     * default (no row) is DISABLED, so only explicitly enabled symbols trigger a near-peak email.
     * Expired temporary states (enable-until / pause-until) are normalized first.
     *
     * @param int $userId
     *
     * @return array
     */
    private function _enabledSymbols(int $userId): array
    {
        PeakProximityAlertSetting::normalizeExpired($userId);

        return PeakProximityAlertSetting::where('user_id', $userId)
            ->where('status', PeakProximityAlertSetting::ENABLED)
            ->pluck('symbol')
            ->toArray();
    }

    /**
     * Symbols already alerted today for this user (mirrors AlertService throttle, per symbol).
     *
     * @param int $userId
     *
     * @return array
     */
    private function _getNotifiedTodaySymbols(int $userId): array
    {
        return PeakProximityNotification::where('user_id', $userId)
            ->where('status', 'SENT')
            ->where('sent_at', '>=', now()->startOfDay())
            ->pluck('symbol')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Whether the user already dismissed an equivalent alert for this symbol earlier today, so a
     * same-day re-trigger should not re-open it. "Equivalent" means the same near-peak condition: the
     * same set of triggered windows AND the same classification (actionable vs info). A genuinely
     * changed condition (a new window reaching peak, or a flip between actionable and info) is not a
     * match, so it still opens a fresh event. Only relevant when no OPEN event exists; an OPEN episode
     * is always refreshed by _upsertEvent.
     *
     * @param int    $userId
     * @param string $symbol
     * @param array  $triggered  window label => exit-zone entry
     * @param array  $class
     *
     * @return bool
     */
    private function _dismissedTodayMatches(int $userId, string $symbol, array $triggered, array $class): bool
    {
        $hasOpen = PeakProximityAlertEvent::where('user_id', $userId)
            ->where('symbol', $symbol)
            ->where('status', PeakProximityAlertEvent::STATUS_OPEN)
            ->exists();

        if ($hasOpen) {
            return false;
        }

        $classification = $class['actionable']
            ? PeakProximityAlertEvent::CLASS_ACTIONABLE
            : PeakProximityAlertEvent::CLASS_INFO;

        $windowsKey = $this->_windowsKey(array_keys($triggered));

        return PeakProximityAlertEvent::where('user_id', $userId)
            ->where('symbol', $symbol)
            ->where('status', PeakProximityAlertEvent::STATUS_DISMISSED)
            ->where('classification', $classification)
            ->where('dismissed_at', '>=', now()->startOfDay())
            ->get()
            ->contains(fn ($e) => $this->_windowsKey(explode(',', (string) $e->triggered_windows)) === $windowsKey);
    }

    /**
     * Canonical comparison key for a set of window labels: trimmed, de-duplicated and sorted, so two
     * triggered-window sets compare equal regardless of order.
     *
     * @param array $windows
     *
     * @return string
     */
    private function _windowsKey(array $windows): string
    {
        $windows = array_values(array_unique(array_filter(array_map('trim', $windows))));
        sort($windows);

        return implode(',', $windows);
    }

    /**
     * Send the peak-proximity alert email and record the audit row.
     * Mirrors AlertService::_sendAlertEmail: record first as SENT, mark FAILED on a send error.
     *
     * @param string $symbol
     * @param array  $quoteData
     * @param array  $triggered   window label => exit-zone entry
     * @param int        $userId
     * @param bool       $isReminder  true for a cadence reminder (vs the first email of the episode)
     * @param float|null $override    uniform --threshold override, else per-window config thresholds
     *
     * @return bool
     */
    private function _sendEmail(
        string $symbol,
        array $quoteData,
        array $triggered,
        int $userId,
        bool $isReminder = false,
        ?float $override = null
    ): bool
    {
        $emailTo = config('alerts.peak_proximity.email_to')
            ?: config('alerts.email_to')
            ?: User::find($userId)?->email;

        if (empty($emailTo)) {
            Log::warning("PeakProximityAlertService: no email address for user {$userId}, skipping {$symbol}");
            return false;
        }

        $notification = PeakProximityNotification::create([
            'user_id'               => $userId,
            'symbol'                => $symbol,
            'current_price'         => isset($quoteData['price']) ? (float) $quoteData['price'] : null,
            'triggered_windows'     => implode(',', array_keys($triggered)),
            'closest_proximity_pct' => $this->_closestProximityPct($triggered),
            'peak_dates'            => $this->_peakDatesString($triggered),
            'sent_at'               => now(),
            'status'                => 'SENT',
        ]);

        // Resolve the trigger threshold for every configured window (not just the fired ones) so the
        // email can show each window's target, i.e. the price at which it would go near peak.
        $windowThresholds = [];
        foreach (config('alerts.peak_proximity.windows', ['3m', '6m', '1y', '2y']) as $window) {
            $windowThresholds[$window] = $this->_windowThreshold($window, $override);
        }

        try {
            Mail::to($emailTo)->send(
                new PeakProximityAlert($symbol, $quoteData, $triggered, $isReminder, $windowThresholds)
            );
        } catch (\Throwable $e) {
            Log::error("PeakProximityAlertService: email send failed for {$symbol}: " . $e->getMessage());
            $notification->update(['status' => 'FAILED', 'error_message' => substr($e->getMessage(), 0, 500)]);
            return false;
        }

        Log::info("PeakProximityAlertService: {$symbol} near peak for user {$userId} => email sent to {$emailTo}");
        return true;
    }

    /**
     * The largest (least negative) proximity_pct among the triggered windows.
     *
     * @param array $triggered
     *
     * @return float|null
     */
    private function _closestProximityPct(array $triggered): ?float
    {
        $values = [];
        foreach ($triggered as $entry) {
            if (isset($entry['proximity_pct'])) {
                $values[] = (float) $entry['proximity_pct'];
            }
        }

        return empty($values) ? null : max($values);
    }

    /**
     * Compact "window:peak_date" list, e.g. "3m:2026-04-01;1y:2025-11-20".
     *
     * @param array $triggered
     *
     * @return string|null
     */
    private function _peakDatesString(array $triggered): ?string
    {
        $parts = [];
        foreach ($triggered as $window => $entry) {
            if (!empty($entry['peak_price_date'])) {
                $parts[] = $window . ':' . $entry['peak_price_date'];
            }
        }

        return empty($parts) ? null : implode(';', $parts);
    }
}
