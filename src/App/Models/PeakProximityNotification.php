<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log model for peak-proximity exit-hint alert notification history.
 * No soft deletes, this is an append-only audit log.
 * No user scope, user_id is set explicitly when the alert is sent.
 */
class PeakProximityNotification extends Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'id'                    => 'integer',
        'user_id'               => 'integer',
        'current_price'         => 'decimal:6',
        'closest_proximity_pct' => 'decimal:4',
        'sent_at'               => 'datetime',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'symbol',
        'signal',
        'current_price',
        'triggered_windows',
        'closest_proximity_pct',
        'peak_dates',
        'sent_at',
        'status',
        'error_message',
    ];

    /**
     * What the email was about: the near-peak windows ("6m,1y"), a range-bound holding's signal in the chip's
     * wording ("Range-bound, near the high: TRIM"), or a take-profit or tier signal one with any windows near peak
     * at the time ("Weak tier in profit: TAKE PROFIT (near peak 6m)", "Tier signal: Rare high").
     */
    public function triggerLabel(): string
    {
        $signal = (string) $this->signal;
        if (PeakProximityAlertEvent::isTakeProfit($signal) || PeakProximityAlertEvent::isTier($signal)) {
            return (PeakProximityAlertEvent::isTier($signal) ? 'Tier signal: ' : '')
                . PeakProximityAlertEvent::signalLabel($signal)
                . ($this->triggered_windows ? ' (near peak ' . $this->triggered_windows . ')' : '');
        }

        return PeakProximityAlertEvent::isRange($signal)
            ? PeakProximityAlertEvent::signalLabel($signal)
            : (string) $this->triggered_windows;
    }

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }
}
