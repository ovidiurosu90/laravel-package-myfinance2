<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user settings for the Portfolio Peak Alerts.
 *
 * One row per user; the default (no row) is DISABLED, so the daily email stays off until the user
 * enables the feature and the email channel on /portfolio-peak-alerts. The two per-metric toggles
 * let either the EUR gain or the return-on-cost trigger be switched off independently. No user
 * scope; user_id is set explicitly by the controller and the service.
 */
class PortfolioPeakSetting extends Model
{
    public const ENABLED  = 'ENABLED';
    public const DISABLED = 'DISABLED';

    /**
     * @var array
     */
    protected $fillable = [
        'user_id',
        'status',
        'email_enabled',
        'change_eur_enabled',
        'change_pct_enabled',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'id'                 => 'integer',
        'user_id'            => 'integer',
        'email_enabled'      => 'boolean',
        'change_eur_enabled' => 'boolean',
        'change_pct_enabled' => 'boolean',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }

    /**
     * Whether this setting opts the user in to the feature.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->status === self::ENABLED;
    }
}
