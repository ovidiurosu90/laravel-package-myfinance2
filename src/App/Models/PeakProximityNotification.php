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
        'current_price',
        'triggered_windows',
        'closest_proximity_pct',
        'peak_dates',
        'sent_at',
        'status',
        'error_message',
    ];

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }
}
