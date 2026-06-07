<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user, per-symbol opt-in flag for the daily peak-proximity exit-hint alerts.
 *
 * The default (no row) is DISABLED, so nothing fires until the user enables a symbol on the
 * /peak-proximity-alerts page. No user scope; user_id is set explicitly by the controller and the
 * engine. Named PeakProximityAlertSetting (not PeakProximityAlert) to avoid colliding with the
 * existing Mailable Mail\PeakProximityAlert.
 *
 * Temporary states use the nullable `until` date:
 *   - status ENABLED  + until X = "enable until X" (alerts while today <= X, then reverts to DISABLED)
 *   - status DISABLED + until Y = "pause until Y"  (no alerts while today <= Y, then reverts to ENABLED)
 *   - until null = permanent.
 * Expired rows are normalized lazily by normalizeExpired(), called wherever settings are read.
 */
class PeakProximityAlertSetting extends Model
{
    public const ENABLED  = 'ENABLED';
    public const DISABLED = 'DISABLED';

    /**
     * @var array
     */
    protected $fillable = [
        'user_id',
        'symbol',
        'status',
        'until',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'id'         => 'integer',
        'user_id'    => 'integer',
        'until'      => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }

    /**
     * Apply any due auto-reverts for a user, then leave callers free to read `status` directly.
     *
     * Order matters: the first update nulls `until` on the expired ENABLED rows, so they drop out of
     * the second query's whereNotNull('until') and are not flipped back the same day.
     *
     * @param int $userId
     *
     * @return void
     */
    public static function normalizeExpired(int $userId): void
    {
        $base = static::where('user_id', $userId)
            ->whereNotNull('until')
            ->whereDate('until', '<', today());

        // ENABLED + expired -> permanently DISABLED
        (clone $base)->where('status', self::ENABLED)
            ->update(['status' => self::DISABLED, 'until' => null]);

        // DISABLED + expired -> permanently ENABLED
        (clone $base)->where('status', self::DISABLED)
            ->update(['status' => self::ENABLED, 'until' => null]);
    }
}
