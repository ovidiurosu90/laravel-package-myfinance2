<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

class PriceAlert extends MyFinance2Model
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
        'id'                => 'integer',
        'trade_currency_id' => 'integer',
        'target_price'      => 'decimal:6',
        'trigger_count'     => 'integer',
        'last_triggered_at' => 'datetime',
        'expires_at'        => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    protected $fillable = [
        'symbol',
        'alert_type',
        'target_price',
        'trade_currency_id',
        'status',
        'source',
        'notification_channel',
        'notes',
        'last_triggered_at',
        'trigger_count',
        'expires_at',
    ];

    /**
     * Get the currency associated with the alert.
     */
    public function tradeCurrencyModel(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'trade_currency_id', 'id');
    }

    /**
     * Get the notifications for this alert.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(PriceAlertNotification::class, 'price_alert_id', 'id');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function canFire(): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'ACTIVE' => 'bg-success',
            'PAUSED' => 'bg-secondary',
            default  => 'bg-secondary',
        };
    }

    public function getAlertTypeBadgeClass(): string
    {
        return match ($this->alert_type) {
            'PRICE_ABOVE' => 'bg-danger',
            'PRICE_BELOW' => 'bg-primary',
            default       => 'bg-secondary',
        };
    }

    public function getFormattedTargetPrice(): string
    {
        if (empty($this->tradeCurrencyModel)) {
            return number_format((float) $this->target_price, 4);
        }

        return MoneyFormat::get_formatted_balance(
            $this->tradeCurrencyModel->display_code,
            $this->target_price
        );
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }
}
