<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;
use ovidiuro\myfinance2\App\Services\Concerns\TracksPeakProximityEpisodes;
use ovidiuro\myfinance2\Mail\PeakProximityAlert;

/**
 * The tier-signal side of the peak-proximity alerts: an opted-in symbol whose tier signal chip (TierSignalChip)
 * shows with an action gets an alert of that signal (RARE_HIGH: SELL / TRIM, RARE_LOW: ADD / BUY, DETERIORATING:
 * REVIEW). Never on a range-bound or TAKE PROFIT symbol (the signal defers to their chip, which names it); a rare-low
 * BUY can fire on a symbol you traded before but no longer hold. Whatever the tier; the near-peak windows are kept
 * as context. Severity is the chip's (TierSignalSeverity).
 *
 * By default every signal is an inbox card for your awareness (INFO), never emailed: since the production rehearsal
 * (2026-10-13), as the first backtest found no edge for the rare highs and the rare lows the wrong way (figures in
 * TierSignalChip). A signal listed in myfinance2.alerts.peak_proximity.tier_signal_emails is ACTIONABLE and emails: a
 * rare high or low (it fades by design, in weeks) once when the episode opens, for a SELL / TRIM at once again when a
 * long window (6M / 1Y / 2Y) newly comes near its peak, else a reminder every tier_signal_reminder_days, until
 * dismissed; a deterioration (it can last months) once, when it opens, with no reminders. A dismissed deterioration
 * stays dismissed while the same drop lasts (it opened on or after the drop's first day); a new drop opens a new card.
 * Shares the inbox, the opt-in, the history and the one-email-per-symbol-per-day guard with the other alerts (an
 * inbox-only signal takes no email, so a near-peak alert of the same symbol still goes that day).
 */
final class PeakProximityTierSignals
{
    use TracksPeakProximityEpisodes;

    /**
     * Evaluate one opted-in symbol whose categorization carries an alertable tier signal chip, updating the run's
     * stats (processed, triggered, skipped, failed, symbols) and the symbols already emailed today.
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
        $chip   = TierSignalChip::alertable($quoteData['categorization'] ?? null);
        if ($chip === null) {
            return;
        }
        $signal = (string) $chip['signal'];
        $stats['processed']++;

        if ($this->_dismissedToday($userId, $symbol, $signal) || $this->_dropDismissed($userId, $symbol, $chip)) {
            $stats['skipped']++;
            return;
        }
        $meaningful = PeakProximityTakeProfitSignals::meaningfulWindows($triggered);
        $event      = $dryRun
            ? null
            : $this->_upsertEvent($userId, $symbol, $quoteData, $chip, $triggered, $meaningful, $windowThresholds);

        if (!self::emails($signal)) {
            $stats['info']++; // an inbox card for your awareness, never emailed
            return;
        }
        if (in_array($symbol, $notifiedToday, true) || !$this->_isDue($event, $chip, $meaningful)) {
            $stats['skipped']++; // daily double-send guard, or the reminder is not due yet
            return;
        }
        if ($dryRun) {
            $stats['triggered']++;
            $stats['symbols'][] = $symbol;
            Log::info("PeakProximityTierSignals: [dry-run] user {$userId} {$symbol} {$chip['label']}: "
                . $chip['action']);
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
     * The OPEN episode, created or refreshed with today's snapshot: tiers, the chip's action and severity, the
     * near-peak windows, the chip and the position figures the inbox card shows without live quotes.
     */
    private function _upsertEvent(
        int $userId,
        string $symbol,
        array $quoteData,
        array $chip,
        array $triggered,
        array $meaningful,
        array $windowThresholds
    ): PeakProximityAlertEvent
    {
        $cat   = $quoteData['categorization'] ?? [];
        $price = isset($quoteData['price']) ? (float) $quoteData['price'] : null;

        return $this->_saveEpisode($userId, $symbol, (string) $chip['signal'], [
            'classification'        => self::emails((string) $chip['signal'])
                ? PeakProximityAlertEvent::CLASS_ACTIONABLE
                : PeakProximityAlertEvent::CLASS_INFO,
            'severity'              => $chip['severity'] ?? PeakProximityAlertEvent::SEVERITY_MEDIUM,
            'effective_tier'        => $cat['effective_tier'] ?? null,
            'today_tier'            => $cat['today_tier'] ?? null,
            'head_action'           => $chip['action'],
            'triggered_windows'     => implode(',', array_keys($triggered)),
            'meaningful_windows'    => implode(',', $meaningful) ?: null,
            'closest_proximity_pct' => PeakProximityAlertService::closestProximityPct($triggered),
            'peak_dates'            => PeakProximityAlertService::peakDatesString($triggered),
            'current_price'         => $price,
            'summary'               => [
                'tier_signal' => $chip,
                // The day the drop began, for a deterioration (what a dismissal holds for).
                'drop_since'  => self::isDrop($chip) ? self::dropSince($chip)->toDateString() : null,
                'windows'     => PeakProximityAlertService::buildSummaryWindows(
                    $price, $cat['exit_zones'] ?? [], array_keys($triggered), $windowThresholds
                ),
                'near_count'  => count($triggered),
            ] + $this->_positionSnapshot($quoteData),
            'last_seen_at'          => now(),
        ]);
    }

    /**
     * Whether alerts of this signal email (listed in myfinance2.alerts.peak_proximity.tier_signal_emails); the others
     * are inbox cards for your awareness.
     */
    public static function emails(string $signal): bool
    {
        return in_array($signal, (array) config('myfinance2.alerts.peak_proximity.tier_signal_emails', []), true);
    }

    public static function isDrop(array $chip): bool
    {
        return ($chip['signal'] ?? null) === SettledTierResolver::SIGNAL_DETERIORATING;
    }

    /**
     * The first day of the current drop: its trading days count today, so it began that many weekdays back, less
     * one (the stored history has a row per weekday).
     */
    public static function dropSince(array $chip, ?Carbon $today = null): Carbon
    {
        return TierHistoryRecorder::weekdaysBefore(
            $today ?? Carbon::today(), max(0, (int) ($chip['deteriorating_days'] ?? 0) - 1)
        );
    }

    /**
     * Whether a deterioration alert opened during this same drop was already dismissed (and none is open): the
     * dismissal holds until the drop ends, so a months-long deterioration is not re-raised every day.
     */
    private function _dropDismissed(int $userId, string $symbol, array $chip): bool
    {
        $signal = (string) $chip['signal'];
        if (!self::isDrop($chip) || $this->_openEpisode($userId, $symbol, $signal) !== null) {
            return false;
        }

        return $this->_episodeQuery($userId, $symbol, $signal)
            ->where('status', PeakProximityAlertEvent::STATUS_DISMISSED)
            ->where('opened_at', '>=', self::dropSince($chip))
            ->exists();
    }

    /**
     * The first email of an episode goes at once. A deterioration gets no more. For a sell (SELL / TRIM) one goes
     * at once when a long window newly comes near its peak (not near peak at the last email: the price now backs
     * the sale); otherwise a reminder every tier_signal_reminder_days.
     *
     * @param string[] $meaningful the long windows near peak today
     */
    private function _isDue(?PeakProximityAlertEvent $event, array $chip, array $meaningful): bool
    {
        if ($event === null || $event->last_emailed_at === null) {
            return true;
        }
        if (self::isDrop($chip)) {
            return false; // a deterioration emails once, when it opens
        }
        $isSell   = in_array(
            $chip['action'], [SettledTierResolver::ACTION_SELL, SettledTierResolver::ACTION_TRIM], true
        );
        $previous = array_filter(explode(',', (string) $event->last_emailed_windows));
        if ($isSell && !empty(array_diff($meaningful, $previous))) {
            return true;
        }
        $days = (int) config('myfinance2.alerts.peak_proximity.tier_signal_reminder_days', 7);

        return $event->last_emailed_at->lte(now()->subDays($days));
    }
}
