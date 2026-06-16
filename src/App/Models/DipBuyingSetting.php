<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user settings for the Dip Buying Plan.
 *
 * One row per user (a single global EUR pool in v1; an account_id can be added later). The default
 * (no row) is DISABLED, so the /positions panel and the daily email stay off until the user sets a
 * pool amount and enables the feature on /dip-buying-alerts. No user scope; user_id is set
 * explicitly by the controller and the engine.
 *
 * `bands` optionally overrides the default reserve ladder (config alerts.dip_buying.bands): a JSON
 * array of {dd, target} entries, cumulative target % deployed at each effective drawdown depth.
 */
class DipBuyingSetting extends Model
{
    public const ENABLED  = 'ENABLED';
    public const DISABLED = 'DISABLED';

    /**
     * @var array
     */
    protected $fillable = [
        'user_id',
        'pool_amount_eur',
        'status',
        'email_enabled',
        'bands',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'id'              => 'integer',
        'user_id'         => 'integer',
        'pool_amount_eur' => 'decimal:2',
        'email_enabled'   => 'boolean',
        'bands'           => 'array',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }

    /**
     * Whether this setting opts the user in to the feature (panel + engine).
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->status === self::ENABLED;
    }
}
