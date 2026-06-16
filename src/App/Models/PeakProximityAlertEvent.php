<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An open peak-proximity alert "episode" for one user + symbol, and the row behind the front-end
 * alerts inbox.
 *
 * One OPEN event represents a symbol that is currently near peak. It is created the first time the
 * symbol triggers and persists until the user dismisses it (it is never auto-closed, even after the
 * symbol drifts off peak), so the inbox keeps showing it until acknowledged. A DISMISSED event does
 * not block a new one: if the symbol later re-triggers, a fresh OPEN event (a new episode) is opened
 * and the first email fires again.
 *
 * classification:
 *   - ACTIONABLE: a meaningful window (6M/1Y/2Y) is near peak AND the gain-based tier is exit-worthy
 *     (these are the ones that email).
 *   - INFO: near peak but not exit-worthy (a strong winner), or only the 3M context window is near
 *     peak. These show in the inbox but never email.
 *
 * No user scope; user_id is set explicitly by the engine and the controller.
 */
class PeakProximityAlertEvent extends Model
{
    public const STATUS_OPEN      = 'OPEN';
    public const STATUS_DISMISSED = 'DISMISSED';

    public const CLASS_ACTIONABLE = 'ACTIONABLE';
    public const CLASS_INFO       = 'INFO';

    public const SEVERITY_HIGH   = 'HIGH';
    public const SEVERITY_MEDIUM = 'MEDIUM';
    public const SEVERITY_LOW    = 'LOW';

    /**
     * @var array
     */
    protected $fillable = [
        'user_id',
        'symbol',
        'classification',
        'severity',
        'status',
        'effective_tier',
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

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }
}
