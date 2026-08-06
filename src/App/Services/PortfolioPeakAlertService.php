<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use ovidiuro\myfinance2\App\Models\PortfolioPeakNotification;
use ovidiuro\myfinance2\App\Models\PortfolioPeakSetting;
use ovidiuro\myfinance2\App\Services\Concerns\LoadsBenchmarkPrices;
use ovidiuro\myfinance2\Mail\PortfolioPeakAlert;

/**
 * Portfolio Peak Alerts engine: email once the portfolio's EUR gain or return on cost has rallied
 * to within N% of its rolling 6M/1Y/2Y high, a portfolio-level "review a full exit / rebalance"
 * hint complementary to the per-symbol peak-proximity alerts.
 *
 * Modelled after the Dip Buying alert flow: opt-in per user (settings row), at most one email per
 * user per day, repeated every reminder_days calendar days (1 by default, so daily) for as long as
 * a window stays inside its threshold. Negative window peaks are in scope, so
 * change_EUR proximity is measured against |peak| (with a magnitude floor) and change_pct on the
 * value index (1 + cp/100), the same transform DipBuyingPlanService uses for portfolio drawdown.
 */
final class PortfolioPeakAlertService
{
    use LoadsBenchmarkPrices;

    private const WINDOW_DAYS = ['3m' => 91, '6m' => 182, '1y' => 365, '2y' => 730];

    // Display labels for a breakdown row. Shared so the email subject, its intro and its table can
    // never name the same (metric, window) pair differently.
    public const METRIC_LABELS = ['change_eur' => 'EUR gain', 'change_pct' => 'Return %'];
    public const WINDOW_LABELS = ['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'];

    // The nav badge renders on every page, so the count is cached rather than rebuilt each time.
    // The TTL matches ChartsBuilder::CHART_CACHE_TTL, the series the count is derived from, so the
    // badge is never staler than its own input.
    private const FIRES_CACHE_PREFIX = 'MYFINANCE2_PORTFOLIO_PEAK_FIRES_';
    private const FIRES_CACHE_TTL = 120;

    /**
     * User IDs with the feature ENABLED, the email channel on, and at least one metric enabled.
     *
     * @return array<int, int>
     */
    public function getUserIdsWithEmailEnabled(): array
    {
        return PortfolioPeakSetting::where('status', PortfolioPeakSetting::ENABLED)
            ->where('email_enabled', true)
            ->where(function ($query)
            {
                $query->where('change_eur_enabled', true)
                      ->orWhere('change_pct_enabled', true);
            })
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * Evaluate and (unless dry-run) send the alert for one user. The setting is re-checked here
     * so the command's --user-id path cannot email a user who never opted in.
     * The caller must already have set the acting user (auth()->setUser).
     *
     * @param int  $userId
     * @param bool $dryRun
     *
     * @return string  'sent' | 'skipped'
     */
    public function evaluateForUser(int $userId, bool $dryRun = false): string
    {
        $setting = PortfolioPeakSetting::where('user_id', $userId)->first();
        if ($setting === null || !$setting->isEnabled() || !$setting->email_enabled) {
            return 'skipped';
        }

        if ($this->_sentToday($userId)) {
            return 'skipped';
        }

        $changeEurSeries = $setting->change_eur_enabled
            ? $this->_loadSeries($userId, 'change_EUR')
            : [];
        $changePctSeries = $setting->change_pct_enabled
            ? $this->_loadSeries($userId, 'changePercentage_EUR')
            : [];

        // The full per-window breakdown (every display window, including the 3M context row) is the
        // single source of truth: the fire decision is just its triggered rows, and the whole table
        // rides along to the email so the reader can calibrate proximity against each threshold.
        $breakdown = $this->_buildBreakdown($changeEurSeries, $changePctSeries);
        $pairs     = $this->_triggeredPairs($breakdown);
        if (empty($pairs)) {
            return 'skipped';
        }

        if (!$this->_reminderDue($userId)) {
            return 'skipped';
        }

        if ($dryRun) {
            Log::info("PortfolioPeakAlertService: [dry-run] user {$userId} => "
                . count($pairs) . ' pair(s) triggered');
            return 'sent';
        }

        return $this->_send($userId, $pairs, $breakdown, $changePctSeries, $changeEurSeries)
            ? 'sent'
            : 'skipped';
    }

    /**
     * Compute the full per-window breakdown for both metrics for display (the settings page's live
     * "current standing" table), without sending or persisting anything. Both metrics are always
     * loaded so the table shows the whole picture, independent of the per-metric alert toggles.
     *
     * @param int $userId
     *
     * @return array{breakdown: array, change_eur_current: float|null, change_pct_current: float|null}
     */
    public function previewForUser(int $userId): array
    {
        $eur = $this->_loadSeries($userId, 'change_EUR');
        $pct = $this->_loadSeries($userId, 'changePercentage_EUR');

        return [
            'breakdown'          => $this->_buildBreakdown($eur, $pct),
            'change_eur_current' => !empty($eur) ? (float) end($eur) : null,
            'change_pct_current' => !empty($pct) ? (float) end($pct) : null,
        ];
    }

    /**
     * One breakdown row named the way the email table names it, e.g. "EUR gain 6M".
     *
     * @param array $row
     *
     * @return string
     */
    public static function pairLabel(array $row): string
    {
        $metric = self::METRIC_LABELS[$row['metric']] ?? (string) $row['metric'];
        $window = self::WINDOW_LABELS[$row['window']] ?? strtoupper((string) $row['window']);

        return $metric . ' ' . $window;
    }

    /**
     * How many windows are inside their threshold right now, i.e. the "yes" rows of the settings
     * page's Fires? column. Context rows (3M) and skipped ones never count, so this is exactly the
     * set that would send an email on the next run.
     *
     * @param int $userId
     *
     * @return int
     */
    public function getTriggeredCount(int $userId): int
    {
        return (int) Cache::remember(
            self::FIRES_CACHE_PREFIX . $userId,
            self::FIRES_CACHE_TTL,
            function () use ($userId)
            {
                $breakdown = $this->previewForUser($userId)['breakdown'];
                return count(array_filter(
                    $breakdown,
                    fn ($row) => !empty($row['triggered'])
                ));
            }
        );
    }

    /**
     * Stored overview chart series as date => float, ascending (shared ChartsBuilder parser).
     *
     * @param int    $userId
     * @param string $metric
     *
     * @return array<string, float>
     */
    private function _loadSeries(int $userId, string $metric): array
    {
        return ChartsBuilder::getChartOverviewUserAsArray($userId, $metric);
    }

    /**
     * Slice a date-keyed series to the last $days calendar days.
     *
     * @param array<string, float> $series
     * @param int                  $days
     *
     * @return array<string, float>
     */
    private function _sliceWindow(array $series, int $days): array
    {
        $cutoff = now()->subDays($days)->format('Y-m-d');
        return array_filter($series, fn ($date) => $date >= $cutoff, ARRAY_FILTER_USE_KEY);
    }

    /**
     * The full per-window diagnostic for both metrics: one row per (metric, display window) with the
     * window peak, its date, the current value, the proximity and the window's threshold, plus flags
     * for whether the window can fire (3M is context only) and whether it did. This is the single
     * source of truth for both the fire gate (_triggeredPairs) and the email's calibration table.
     *
     * change_eur: proximity relative to |peak| so negative peaks work (selling winners can leave
     * the remaining book negative; rallying back to a negative 1Y high is still the exit signal).
     * Windows whose |peak| is under the min_peak_abs_eur floor are marked skipped (noise).
     *
     * change_pct: proximity on the value index (1 + cp/100), the same transform
     * DipBuyingPlanService::_portfolioDrawdown uses, so both features agree on "drawdown from
     * the window high" even when the whole window is underwater.
     *
     * @param array<string, float> $changeEurSeries
     * @param array<string, float> $changePctSeries
     *
     * @return array
     */
    private function _buildBreakdown(array $changeEurSeries, array $changePctSeries): array
    {
        $minPeakAbs = (float) config('alerts.portfolio_peak.min_peak_abs_eur', 1000);

        $eur = $this->_metricBreakdown(
            $changeEurSeries,
            'change_eur',
            fn (float $current, float $peak): ?float => abs($peak) >= $minPeakAbs
                ? ($current - $peak) / abs($peak) * 100.0
                : null
        );

        $pct = $this->_metricBreakdown(
            $changePctSeries,
            'change_pct',
            function (float $current, float $peak): ?float
            {
                $currentIdx = 1.0 + $current / 100.0;
                $peakIdx    = 1.0 + $peak / 100.0;
                return $peakIdx > 1e-9
                    ? ($currentIdx - $peakIdx) / $peakIdx * 100.0
                    : null;
            }
        );

        return array_merge($eur, $pct);
    }

    /**
     * The rows of a breakdown that actually fire the alert: a trigger window (not the 3M context
     * row) whose proximity is within its threshold.
     *
     * @param array $breakdown
     *
     * @return array  entries keep ['metric', 'window', 'proximity_pct', 'peak', 'current', ...]
     */
    private function _triggeredPairs(array $breakdown): array
    {
        return array_values(array_filter($breakdown, fn (array $row) => $row['triggered']));
    }

    /**
     * Run one metric's series through every display window. $proximity maps (current, peak) to a
     * proximity % or null to skip the window (e.g. the change_eur magnitude floor). A window can
     * fire only when it is one of the configured trigger windows (3M is display/context only).
     *
     * @param array<string, float> $series
     * @param string               $metricKey
     * @param \Closure             $proximity
     *
     * @return array
     */
    private function _metricBreakdown(array $series, string $metricKey, \Closure $proximity): array
    {
        if (empty($series)) {
            return [];
        }

        $current      = (float) end($series);
        $triggerWins  = (array) config('alerts.portfolio_peak.windows', ['6m', '1y', '2y']);
        $displayWins  = (array) config('alerts.portfolio_peak.display_windows', ['3m', '6m', '1y', '2y']);
        $rows         = [];

        foreach ($displayWins as $window) {
            $days = self::WINDOW_DAYS[$window] ?? null;
            if ($days === null) {
                continue;
            }

            $slice = $this->_sliceWindow($series, $days);
            if (empty($slice)) {
                continue;
            }

            $peakVal  = max($slice);
            $peakDate = (string) array_search($peakVal, $slice, true); // first date at the peak
            $prox     = $proximity($current, (float) $peakVal);
            $isTrig   = in_array($window, $triggerWins, true);
            $threshold = (float) config(
                "alerts.portfolio_peak.window_thresholds.{$metricKey}.{$window}", 5.0
            );

            $rows[] = [
                'metric'        => $metricKey,
                'window'        => $window,
                'current'       => round($current, 4),
                'peak'          => round((float) $peakVal, 4),
                'peak_date'     => $peakDate,
                'proximity_pct' => $prox !== null ? round($prox, 2) : null,
                'threshold_pct' => $threshold,
                'is_trigger'    => $isTrig,
                'skipped'       => $prox === null,
                'triggered'     => $prox !== null && $isTrig && $prox >= -$threshold,
            ];
        }

        return $rows;
    }

    /**
     * True when a reminder may be sent: no prior SENT notification, or the last one went out at
     * least reminder_days calendar days ago.
     *
     * The comparison is on calendar days, not elapsed hours, so a daily cadence (reminder_days = 1)
     * stays anchored to the first hourly run of each day. Comparing raw timestamps would push each
     * send an hour later than the previous one until a day was skipped entirely.
     *
     * @param int $userId
     *
     * @return bool
     */
    private function _reminderDue(int $userId): bool
    {
        $last = PortfolioPeakNotification::where('user_id', $userId)
            ->where('status', 'SENT')
            ->orderBy('sent_at', 'desc')
            ->first();

        if ($last === null) {
            return true;
        }

        $reminderDays = (int) config('alerts.portfolio_peak.reminder_days', 1);
        return $last->sent_at->copy()->startOfDay()
            ->lte(now()->startOfDay()->subDays($reminderDays));
    }

    /**
     * True when a SENT notification already went out today.
     *
     * @param int $userId
     *
     * @return bool
     */
    private function _sentToday(int $userId): bool
    {
        return PortfolioPeakNotification::where('user_id', $userId)
            ->where('status', 'SENT')
            ->where('sent_at', '>=', now()->startOfDay())
            ->exists();
    }

    /**
     * Send the email and record the audit row (SENT first, FAILED on error, mirroring the
     * dip-buying alert flow).
     *
     * @param int   $userId
     * @param array $pairs
     * @param array $breakdown
     * @param array $changePctSeries
     * @param array $changeEurSeries
     *
     * @return bool
     */
    private function _send(
        int $userId,
        array $pairs,
        array $breakdown,
        array $changePctSeries,
        array $changeEurSeries
    ): bool
    {
        $emailTo = config('alerts.portfolio_peak.email_to')
            ?: config('alerts.email_to')
            ?: User::find($userId)?->email;

        if (empty($emailTo)) {
            Log::warning("PortfolioPeakAlertService: no email for user {$userId}, skipping.");
            return false;
        }

        $changePctCurrent = !empty($changePctSeries) ? (float) end($changePctSeries) : null;
        $changeEurCurrent = !empty($changeEurSeries) ? (float) end($changeEurSeries) : null;
        $vusaChangePct    = $this->_vusaChangePct();

        $notification = PortfolioPeakNotification::create([
            'user_id'               => $userId,
            'triggered_metrics'     => implode(',', array_unique(array_column($pairs, 'metric'))),
            'triggered_windows'     => implode(',', array_unique(array_column($pairs, 'window'))),
            'closest_proximity_pct' => max(array_column($pairs, 'proximity_pct')),
            'change_eur_current'    => $changeEurCurrent,
            'change_pct_current'    => $changePctCurrent,
            'vusa_change_pct'       => $vusaChangePct,
            'sent_at'               => now(),
            'status'                => 'SENT',
        ]);

        try {
            Mail::to($emailTo)->send(
                new PortfolioPeakAlert(
                    $pairs, $changePctCurrent, $changeEurCurrent, $vusaChangePct, $breakdown
                )
            );
        } catch (\Throwable $e) {
            Log::error("PortfolioPeakAlertService: email failed for user {$userId}: "
                . $e->getMessage());
            $notification->update([
                'status'        => 'FAILED',
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);
            return false;
        }

        Log::info("PortfolioPeakAlertService: alert sent to {$emailTo} for user {$userId}");
        return true;
    }

    /**
     * Current VUSA.AS distance from its 2Y peak, for email context only. Uses the shared
     * LoadsBenchmarkPrices series (stats_historical + live intraday point), the same source the
     * Dip Buying engine reads. Null when unavailable.
     *
     * @return float|null
     */
    private function _vusaChangePct(): ?float
    {
        $prices = $this->_loadBenchmarkPrices(
            now()->subDays(self::WINDOW_DAYS['2y'])->format('Y-m-d')
        );
        if (count($prices) < 2) {
            return null;
        }

        ksort($prices);
        $peak    = (float) max($prices);
        $current = (float) end($prices);

        return $peak > 0.0 ? round(($current - $peak) / $peak * 100.0, 2) : null;
    }
}
