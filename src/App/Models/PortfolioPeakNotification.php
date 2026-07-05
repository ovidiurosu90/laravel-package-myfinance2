<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log model for the Portfolio Peak Alerts daily-email history and the once-per-day / reminder guards.
 * No soft deletes, this is an append-only audit log.
 * No user scope, user_id is set explicitly when the alert is sent.
 */
class PortfolioPeakNotification extends Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'id'                    => 'integer',
        'user_id'               => 'integer',
        'closest_proximity_pct' => 'decimal:4',
        'change_eur_current'    => 'decimal:2',
        'change_pct_current'    => 'decimal:4',
        'vusa_change_pct'       => 'decimal:4',
        'sent_at'               => 'datetime',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }
}
