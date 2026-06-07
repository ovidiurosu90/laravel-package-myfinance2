<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        $stats = ['processed' => 0, 'triggered' => 0, 'skipped' => 0, 'failed' => 0, 'symbols' => []];

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

            $stats['processed']++;

            if (in_array($symbol, $notifiedToday, true)) {
                $stats['skipped']++;
                continue;
            }

            $triggered = $this->_triggeredWindows($quoteData, $thresholdPct);
            if (empty($triggered)) {
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                $stats['triggered']++;
                $stats['symbols'][] = $symbol;
                Log::info(
                    "PeakProximityAlertService: [dry-run] user {$userId} {$symbol} near peak"
                    . ' (' . implode(',', array_keys($triggered)) . ')'
                );
                continue;
            }

            if ($this->_sendEmail($symbol, $quoteData, $triggered, $userId)) {
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
     * Send the peak-proximity alert email and record the audit row.
     * Mirrors AlertService::_sendAlertEmail: record first as SENT, mark FAILED on a send error.
     *
     * @param string $symbol
     * @param array  $quoteData
     * @param array  $triggered  window label => exit-zone entry
     * @param int    $userId
     *
     * @return bool
     */
    private function _sendEmail(
        string $symbol,
        array $quoteData,
        array $triggered,
        int $userId
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

        try {
            Mail::to($emailTo)->send(new PeakProximityAlert($symbol, $quoteData, $triggered));
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
