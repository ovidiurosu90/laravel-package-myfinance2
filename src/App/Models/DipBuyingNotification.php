<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log model for the Dip Buying Plan daily-email history and once-per-day throttle.
 * No soft deletes, this is an append-only audit log.
 * No user scope, user_id is set explicitly when the alert is sent.
 */
class DipBuyingNotification extends Model
{
    /**
     * The 'basis' of an alert measured on the current dip: its peak, your cash then and the cash used since
     * (DipBuyingBacktestService::planOnCurrentEpisode). Null: the plan's own basis, VUSA.AS's peak and the
     * settings pool (every row sent before the column existed, and an alert sent without a current dip). Rows
     * are kept as they were sent; the history page says which basis they use.
     */
    public const BASIS_CURRENT_DIP = 'current_dip';

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
        'effective_dd_pct'      => 'decimal:4',
        'vusa_dd_pct'           => 'decimal:4',
        'portfolio_dd_pct'      => 'decimal:4',
        'anchor_date'           => 'date',
        'target_pct'            => 'integer',
        'deployed_pct'          => 'decimal:4',
        'deployed_eur'          => 'decimal:2',
        'pool_amount_eur'       => 'decimal:2',
        'suggested_tranche_eur' => 'decimal:2',
        'sent_at'               => 'datetime',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'effective_dd_pct',
        'vusa_dd_pct',
        'portfolio_dd_pct',
        'driver',
        'anchor_date',
        'basis',
        'target_pct',
        'deployed_pct',
        'deployed_eur',
        'pool_amount_eur',
        'suggested_tranche_eur',
        'verdict',
        'trigger',
        'sent_at',
        'status',
        'error_message',
    ];

    /**
     * Whether this alert measured deployment on the current dip (BASIS_CURRENT_DIP).
     */
    public function isOnCurrentDipBasis(): bool
    {
        return $this->basis === self::BASIS_CURRENT_DIP;
    }

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }
}
