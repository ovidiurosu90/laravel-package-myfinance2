<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;

use ovidiuro\myfinance2\App\Services\RangeBoundPattern;
use ovidiuro\myfinance2\App\Services\TierSignalChip;
use ovidiuro\myfinance2\App\Services\WeakTierProfit;

/**
 * An open peak-proximity alert "episode" for one user + symbol, and the row behind the front-end
 * alerts inbox.
 *
 * One OPEN event represents a symbol that is currently near peak. It is created the first time the
 * symbol triggers and persists until the user dismisses it (it is never auto-closed, even after the
 * symbol drifts off peak), so the inbox keeps showing it until acknowledged. Dismissing within the
 * same day suppresses a re-trigger of the identical near-peak condition (same triggered windows and
 * classification), so a dismissed card does not immediately pop back into the inbox on a same-day
 * re-run. On a later day, or if the condition changes (a new window reaches peak), the symbol
 * re-triggers into a fresh OPEN event (a new episode) and the first email fires again.
 *
 * classification:
 *   - ACTIONABLE: a meaningful window (6M/1Y/2Y) is near peak AND the tier in use is exit-worthy
 *     (these are the ones that email).
 *   - INFO: near peak but not exit-worthy (a strong winner), or only the 3M context window is near
 *     peak. These show in the inbox but never email.
 *
 * A range-bound symbol (signal RANGE_HIGH / RANGE_LOW / RANGE_BUY) is read on its swing instead of near-peak: an
 * episode opens when its price enters the top or bottom of its 52-week range (TRIM / ADD for a held position, BUY for
 * one you exited): ACTIONABLE when the signal is listed in myfinance2.alerts.peak_proximity.range_emails (ADD and BUY
 * by default), else INFO (TRIM by default).
 *
 * A held symbol whose tier in use is weak while its open position is in profit (WeakTierProfit, signal TAKE_PROFIT)
 * gets a TAKE PROFIT episode in place of a near-peak one: always ACTIONABLE, opened while the condition holds,
 * with the near-peak windows kept as context.
 *
 * A tier signal (TierSignalChip, signal RARE_HIGH / RARE_LOW / DETERIORATING) shown with an action (SELL / TRIM /
 * ADD / BUY / REVIEW; not on a range-bound or TAKE PROFIT symbol, whose chip names it instead) gets its own episode,
 * whatever the tier, with the near-peak windows kept as context: INFO (never emailed) unless the signal is listed in
 * myfinance2.alerts.peak_proximity.tier_signal_emails, then ACTIONABLE. A rare-low BUY can fire on a symbol you traded
 * before but no longer hold.
 *
 * No user scope; user_id is set explicitly by the engine and the controller.
 */
class PeakProximityAlertEvent extends Model
{
    public const STATUS_OPEN      = 'OPEN';
    public const STATUS_DISMISSED = 'DISMISSED';

    public const CLASS_ACTIONABLE = 'ACTIONABLE';
    public const CLASS_INFO       = 'INFO';

    // What raised the alert: near a peak (the exit hint), or a range-bound symbol (RangeBoundPattern) near the
    // high of its 52-week range (TRIM, held) or near the low (ADD when held, BUY when you traded it but no longer
    // hold it). One OPEN episode per symbol and signal.
    public const SIGNAL_NEAR_PEAK   = 'NEAR_PEAK';
    public const SIGNAL_RANGE_HIGH  = 'RANGE_HIGH';
    public const SIGNAL_RANGE_LOW   = 'RANGE_LOW';
    public const SIGNAL_RANGE_BUY   = 'RANGE_BUY';
    // A held symbol whose tier in use is weak while the open position is in profit (WeakTierProfit).
    public const SIGNAL_TAKE_PROFIT = 'TAKE_PROFIT';
    // Tier signals (TierSignalChip): today's tier rare against the last 12 months, or a lasting drop.
    public const SIGNAL_RARE_HIGH     = 'RARE_HIGH';
    public const SIGNAL_RARE_LOW      = 'RARE_LOW';
    public const SIGNAL_DETERIORATING = 'DETERIORATING';

    public const SEVERITY_HIGH   = 'HIGH';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_LOW    = 'LOW';

    /**
     * @var array
     */
    protected $fillable = [
        'user_id',
        'symbol',
        'signal',
        'classification',
        'severity',
        'status',
        'effective_tier',
        'today_tier',
        'head_action',
        'triggered_windows',
        'meaningful_windows',
        'closest_proximity_pct',
        'peak_dates',
        'summary',
        'current_price',
        'opened_at',
        'last_seen_at',
        'last_emailed_at',
        'last_emailed_meaningful_count',
        'last_emailed_windows',
        'email_count',
        'dismissed_at',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'id'                            => 'integer',
        'user_id'                       => 'integer',
        'closest_proximity_pct'         => 'decimal:4',
        'summary'                       => 'array',
        'current_price'                 => 'decimal:6',
        'opened_at'                     => 'datetime',
        'last_seen_at'                  => 'datetime',
        'last_emailed_at'               => 'datetime',
        'last_emailed_meaningful_count' => 'integer',
        'email_count'                   => 'integer',
        'dismissed_at'                  => 'datetime',
        'created_at'                    => 'datetime',
        'updated_at'                    => 'datetime',
    ];

    public function isRangeSignal(): bool
    {
        return self::isRange((string) $this->signal);
    }

    public static function isRange(string $signal): bool
    {
        return in_array($signal, [self::SIGNAL_RANGE_HIGH, self::SIGNAL_RANGE_LOW, self::SIGNAL_RANGE_BUY], true);
    }

    public function isTakeProfitSignal(): bool
    {
        return self::isTakeProfit((string) $this->signal);
    }

    public static function isTakeProfit(string $signal): bool
    {
        return $signal === self::SIGNAL_TAKE_PROFIT;
    }

    public function isTierSignal(): bool
    {
        return self::isTier((string) $this->signal);
    }

    public static function isTier(string $signal): bool
    {
        return in_array($signal, [self::SIGNAL_RARE_HIGH, self::SIGNAL_RARE_LOW, self::SIGNAL_DETERIORATING], true);
    }

    /**
     * The signal in words, the chip's wording for a range or a take-profit signal ("Range-bound, near the high:
     * TRIM", "Weak tier in profit: TAKE PROFIT"), the label for a tier signal ("Rare high"; its action varies).
     */
    public static function signalLabel(?string $signal): string
    {
        return match ($signal) {
            self::SIGNAL_RANGE_HIGH  => RangeBoundPattern::label(RangeBoundPattern::ZONE_HIGH) . ': '
                . RangeBoundPattern::ACTION_TRIM,
            self::SIGNAL_RANGE_LOW   => RangeBoundPattern::label(RangeBoundPattern::ZONE_LOW) . ': '
                . RangeBoundPattern::ACTION_ADD,
            self::SIGNAL_RANGE_BUY   => RangeBoundPattern::label(RangeBoundPattern::ZONE_LOW) . ': '
                . RangeBoundPattern::ACTION_BUY,
            self::SIGNAL_TAKE_PROFIT => WeakTierProfit::LABEL . ': ' . WeakTierProfit::ACTION,
            self::SIGNAL_RARE_HIGH, self::SIGNAL_RARE_LOW, self::SIGNAL_DETERIORATING
                                     => TierSignalChip::LABELS[$signal],
            default                  => 'Near peak',
        };
    }

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }
}
