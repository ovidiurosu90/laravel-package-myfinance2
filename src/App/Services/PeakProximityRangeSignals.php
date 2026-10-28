<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;
use ovidiuro\myfinance2\App\Services\Concerns\TracksPeakProximityEpisodes;
use ovidiuro\myfinance2\Mail\PeakProximityAlert;

/**
 * The range-bound side of the peak-proximity alerts: an opted-in symbol that is range-bound (RangeBoundPattern) is
 * read on its swing instead of near-peak. Held: when its price enters the top of the 52-week range an alert opens
 * with signal RANGE_HIGH (TRIM, take the swing profit); the bottom, RANGE_LOW (ADD on the dip). Traded but no longer
 * held: the bottom opens RANGE_BUY (a good point to re-enter); the top (WAIT) raises nothing yet. Mid-range raises
 * nothing. Whatever the tier, a signal listed in myfinance2.alerts.peak_proximity.range_emails (by default ADD and BUY)
 * is ACTIONABLE and emails: once when an episode opens, then a reminder every range_reminder_days while the price stays
 * in that zone, until dismissed. The others (by default TRIM) are inbox cards for your awareness (INFO), never
 * emailed. They share the inbox, the opt-in, the history and the one-email-per-symbol-per-day guard with the
 * near-peak alerts.
 *
 * The first backtest (2026-10-13) supported the ADD and BUY alerts and found no edge for TRIM, so TRIM became an inbox
 * card. Figures and what to change: the note in RangeBoundPattern.
 */
final class PeakProximityRangeSignals
{
    use TracksPeakProximityEpisodes;

    /** The range action => the alert's signal. HOLD, WAIT and WATCH raise nothing. */
    private const SIGNALS = [
        RangeBoundPattern::ACTION_TRIM => PeakProximityAlertEvent::SIGNAL_RANGE_HIGH,
        RangeBoundPattern::ACTION_ADD  => PeakProximityAlertEvent::SIGNAL_RANGE_LOW,
        RangeBoundPattern::ACTION_BUY  => PeakProximityAlertEvent::SIGNAL_RANGE_BUY,
    ];

    /**
     * The signal a range-bound reading raises (RANGE_HIGH / RANGE_LOW / RANGE_BUY), or null for none (mid-range,
     * or near the high without a position).
     */
    public static function signalFor(?array $range): ?string
    {
        return self::SIGNALS[$range['action'] ?? ''] ?? null;
    }

    /**
     * Evaluate one opted-in symbol (held or exited) whose categorization carries a range-bound reading, updating the
     * run's stats (processed, triggered, skipped, failed, symbols) and the symbols already emailed today.
     */
    public function evaluate(
        int $userId,
        string $symbol,
        array $quoteData,
        bool $dryRun,
        array &$notifiedToday,
        array &$stats
    ): void
    {
        $signal = self::signalFor($quoteData['categorization']['range_bound'] ?? null);
        if ($signal === null) {
            return; // mid-range, or near the high without a position: nothing to raise
        }
        $stats['processed']++;

        if ($this->_dismissedToday($userId, $symbol, $signal)) {
            $stats['skipped']++;
            return;
        }
        $event = $dryRun ? null : $this->_upsertEvent($userId, $symbol, $signal, $quoteData);

        if (!self::emails($signal)) {
            $stats['info']++; // an inbox card for your awareness, never emailed
            return;
        }
        if (in_array($symbol, $notifiedToday, true) || !$this->_isDue($event)) {
            $stats['skipped']++; // daily double-send guard, or the reminder is not due yet
            return;
        }
        if ($dryRun) {
            $stats['triggered']++;
            $stats['symbols'][] = $symbol;
            Log::info("PeakProximityRangeSignals: [dry-run] user {$userId} {$symbol} "
                . PeakProximityAlertEvent::signalLabel($signal));
            return;
        }

        $isReminder = $event !== null && (int) $event->email_count > 0;
        $mail       = new PeakProximityAlert($symbol, $quoteData, [], $isReminder, [], $signal);
        $record     = [
            'signal'            => $signal,
            'current_price'     => isset($quoteData['price']) ? (float) $quoteData['price'] : null,
            'triggered_windows' => '',
        ];
        if (!PeakProximityAlertService::deliver($userId, $symbol, $record, $mail)) {
            $stats['failed']++;
            return;
        }
        $event->last_emailed_at = now();
        $event->email_count     = (int) $event->email_count + 1;
        $event->save();
        $stats['triggered']++;
        $stats['symbols'][] = $symbol;
        $notifiedToday[]    = $symbol;
    }

    /**
     * The OPEN episode for this symbol and signal, created or refreshed with today's snapshot (tiers, action,
     * price, the range reading and the position figures the inbox card shows without live quotes).
     */
    private function _upsertEvent(
        int $userId,
        string $symbol,
        string $signal,
        array $quoteData
    ): PeakProximityAlertEvent
    {
        $cat   = $quoteData['categorization'] ?? [];
        $range = $cat['range_bound'];

        return $this->_saveEpisode($userId, $symbol, $signal, [
            'classification'    => self::emails($signal)
                ? PeakProximityAlertEvent::CLASS_ACTIONABLE
                : PeakProximityAlertEvent::CLASS_INFO,
            'severity'          => $this->_severity($signal, $quoteData['technical_indicators']['rsi'] ?? null),
            'effective_tier'    => $cat['effective_tier'] ?? null,
            'today_tier'        => $cat['today_tier'] ?? null,
            'head_action'       => $range['action'],
            'triggered_windows' => '',
            'current_price'     => isset($quoteData['price']) ? (float) $quoteData['price'] : null,
            'summary'           => ['range_bound' => $range] + $this->_positionSnapshot($quoteData),
            'last_seen_at'      => now(),
        ]);
    }

    /**
     * Whether alerts of this signal email (listed in myfinance2.alerts.peak_proximity.range_emails); the others are
     * inbox cards.
     */
    public static function emails(string $signal): bool
    {
        return in_array($signal, (array) config('myfinance2.alerts.peak_proximity.range_emails', []), true);
    }

    /**
     * The first email of an episode goes at once; reminders every range_reminder_days.
     */
    private function _isDue(?PeakProximityAlertEvent $event): bool
    {
        if ($event === null || $event->last_emailed_at === null) {
            return true;
        }
        $days = (int) config('myfinance2.alerts.peak_proximity.range_reminder_days', 7);

        return $event->last_emailed_at->lte(now()->subDays($days));
    }

    /**
     * HIGH when RSI agrees with the swing (overbought for a TRIM, oversold for an ADD or a BUY), MEDIUM otherwise.
     */
    private function _severity(string $signal, mixed $rsi): string
    {
        if ($rsi === null) {
            return PeakProximityAlertEvent::SEVERITY_MEDIUM;
        }
        $agrees = $signal === PeakProximityAlertEvent::SIGNAL_RANGE_HIGH
            ? (float) $rsi >= (float) config('myfinance2.alerts.peak_proximity.rsi_overbought', 70)
            : (float) $rsi <= (float) config('myfinance2.alerts.peak_proximity.rsi_oversold', 30);

        return $agrees ? PeakProximityAlertEvent::SEVERITY_HIGH : PeakProximityAlertEvent::SEVERITY_MEDIUM;
    }
}
