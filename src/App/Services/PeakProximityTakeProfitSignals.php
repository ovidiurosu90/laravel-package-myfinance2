<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;
use ovidiuro\myfinance2\App\Services\Concerns\TracksPeakProximityEpisodes;
use ovidiuro\myfinance2\Mail\PeakProximityAlert;

/**
 * The weak-tier-in-profit side of the peak-proximity alerts: an opted-in, held symbol whose tier in use is weak while
 * its open position is in profit (WeakTierProfit) gets a TAKE PROFIT alert (signal TAKE_PROFIT) in place of its
 * near-peak alert. Always ACTIONABLE, whether or not the price is near a peak; the near-peak windows are kept as
 * context and raise the severity. One email when the episode opens, at
 * once when a long window (6M / 1Y / 2Y) newly comes near its peak, else a reminder every take_profit_reminder_days,
 * until dismissed. Shares the inbox, the opt-in, the history and the one-email-per-symbol-per-day guard with the other
 * alerts.
 */
final class PeakProximityTakeProfitSignals
{
    use TracksPeakProximityEpisodes;

    /**
     * Evaluate one opted-in, held symbol whose categorization carries a weak-tier-in-profit reading, updating the
     * run's stats (processed, triggered, skipped, failed, symbols) and the symbols already emailed today.
     *
     * @param array $triggered        window key => exit-zone entry, the windows near peak today (3M included)
     * @param array $windowThresholds window key => proximity threshold (%), for the email's and the card's table
     */
    public function evaluate(
        int $userId,
        string $symbol,
        array $quoteData,
        array $triggered,
        array $windowThresholds,
        bool $dryRun,
        array &$notifiedToday,
        array &$stats
    ): void
    {
        $signal = PeakProximityAlertEvent::SIGNAL_TAKE_PROFIT;
        $stats['processed']++;

        if ($this->_dismissedToday($userId, $symbol, $signal)) {
            $stats['skipped']++;
            return;
        }
        $meaningful = self::meaningfulWindows($triggered);
        $event      = $dryRun
            ? null
            : $this->_upsertEvent($userId, $symbol, $quoteData, $triggered, $meaningful, $windowThresholds);

        if (in_array($symbol, $notifiedToday, true) || !$this->_isDue($event, $meaningful)) {
            $stats['skipped']++; // daily double-send guard, or the reminder is not due yet
            return;
        }
        if ($dryRun) {
            $stats['triggered']++;
            $stats['symbols'][] = $symbol;
            Log::info("PeakProximityTakeProfitSignals: [dry-run] user {$userId} {$symbol} "
                . PeakProximityAlertEvent::signalLabel($signal));
            return;
        }

        $isReminder = (int) $event->email_count > 0;
        $mail       = new PeakProximityAlert($symbol, $quoteData, $triggered, $isReminder, $windowThresholds, $signal);
        $record     = [
            'signal'                => $signal,
            'current_price'         => isset($quoteData['price']) ? (float) $quoteData['price'] : null,
            'triggered_windows'     => implode(',', array_keys($triggered)),
            'closest_proximity_pct' => PeakProximityAlertService::closestProximityPct($triggered),
            'peak_dates'            => PeakProximityAlertService::peakDatesString($triggered),
        ];
        if (!PeakProximityAlertService::deliver($userId, $symbol, $record, $mail)) {
            $stats['failed']++;
            return;
        }
        $event->last_emailed_at               = now();
        $event->email_count                   = (int) $event->email_count + 1;
        $event->last_emailed_meaningful_count = count($meaningful);
        $event->last_emailed_windows          = implode(',', $meaningful) ?: null;
        $event->save();
        $stats['triggered']++;
        $stats['symbols'][] = $symbol;
        $notifiedToday[]    = $symbol;
    }

    /**
     * The long windows (myfinance2.alerts.peak_proximity.meaningful_windows) among the ones near peak.
     *
     * @return string[]
     */
    public static function meaningfulWindows(array $triggered): array
    {
        return array_values(array_intersect(
            array_keys($triggered),
            config('myfinance2.alerts.peak_proximity.meaningful_windows', ['6m', '1y', '2y'])
        ));
    }

    /**
     * The OPEN episode, created or refreshed with today's snapshot: tiers, the TAKE PROFIT action, the near-peak
     * windows, the reading and the position figures the inbox card shows without live quotes.
     */
    private function _upsertEvent(
        int $userId,
        string $symbol,
        array $quoteData,
        array $triggered,
        array $meaningful,
        array $windowThresholds
    ): PeakProximityAlertEvent
    {
        $cat   = $quoteData['categorization'] ?? [];
        $price = isset($quoteData['price']) ? (float) $quoteData['price'] : null;

        return $this->_saveEpisode($userId, $symbol, PeakProximityAlertEvent::SIGNAL_TAKE_PROFIT, [
            'classification'        => PeakProximityAlertEvent::CLASS_ACTIONABLE,
            'severity'              => $this->_severity($meaningful, $quoteData['technical_indicators']['rsi'] ?? null),
            'effective_tier'        => $cat['effective_tier'] ?? null,
            'today_tier'            => $cat['today_tier'] ?? null,
            'head_action'           => WeakTierProfit::ACTION,
            'triggered_windows'     => implode(',', array_keys($triggered)),
            'meaningful_windows'    => implode(',', $meaningful) ?: null,
            'closest_proximity_pct' => PeakProximityAlertService::closestProximityPct($triggered),
            'peak_dates'            => PeakProximityAlertService::peakDatesString($triggered),
            'current_price'         => $price,
            'summary'               => [
                'take_profit' => $cat['take_profit'],
                'windows'     => PeakProximityAlertService::buildSummaryWindows(
                    $price, $cat['exit_zones'] ?? [], array_keys($triggered), $windowThresholds
                ),
                'near_count'  => count($triggered),
            ] + $this->_positionSnapshot($quoteData),
            'last_seen_at'          => now(),
        ]);
    }

    /**
     * The first email of an episode goes at once, and so does one when a long window newly comes near its peak
     * (not near peak at the last email); otherwise a reminder every take_profit_reminder_days.
     *
     * @param string[] $meaningful the long windows near peak today
     */
    private function _isDue(?PeakProximityAlertEvent $event, array $meaningful): bool
    {
        if ($event === null || $event->last_emailed_at === null) {
            return true;
        }
        $previous = array_filter(explode(',', (string) $event->last_emailed_windows));
        if (!empty(array_diff($meaningful, $previous))) {
            return true;
        }
        $days = (int) config('myfinance2.alerts.peak_proximity.take_profit_reminder_days', 7);

        return $event->last_emailed_at->lte(now()->subDays($days));
    }

    /**
     * HIGH when the price backs the sale (a long window near its peak, or RSI overbought), MEDIUM otherwise.
     *
     * @param string[] $meaningful
     */
    private function _severity(array $meaningful, mixed $rsi): string
    {
        $overbought = $rsi !== null
            && (float) $rsi >= (float) config('myfinance2.alerts.peak_proximity.rsi_overbought', 70);

        return !empty($meaningful) || $overbought
            ? PeakProximityAlertEvent::SEVERITY_HIGH
            : PeakProximityAlertEvent::SEVERITY_MEDIUM;
    }
}
