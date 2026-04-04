<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log model for price alert notification history.
 * No soft deletes — this is an append-only audit log.
 * No user scope — user_id is set explicitly from alert data.
 */
class PriceAlertNotification extends Model
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
        'id'                 => 'integer',
        'price_alert_id'     => 'integer',
        'user_id'            => 'integer',
        'current_price'      => 'decimal:6',
        'target_price'       => 'decimal:6',
        'projected_gain_eur' => 'decimal:2',
        'projected_gain_pct' => 'decimal:4',
        'sent_at'            => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    protected $fillable = [
        'price_alert_id',
        'user_id',
        'symbol',
        'notification_channel',
        'current_price',
        'target_price',
        'alert_type',
        'projected_gain_eur',
        'projected_gain_pct',
        'sent_at',
        'status',
        'error_message',
    ];

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }

    /**
     * Get the price alert associated with this notification.
     */
    public function priceAlertModel(): BelongsTo
    {
        return $this->belongsTo(PriceAlert::class, 'price_alert_id', 'id');
    }
}
