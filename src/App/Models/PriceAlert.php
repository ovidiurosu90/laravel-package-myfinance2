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

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * The status as it should be presented to the user. An ACTIVE alert whose
     * expiry has passed can no longer fire (see canFire()), so it is surfaced as
     * EXPIRED rather than ACTIVE. This keeps it out of the "active" list filter
     * while leaving it visible under the "all" view.
     */
    public function getEffectiveStatus(): string
    {
        if ($this->status === 'ACTIVE' && $this->isExpired()) {
            return 'EXPIRED';
        }

        return $this->status;
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
        return match ($this->getEffectiveStatus()) {
            'ACTIVE'  => 'bg-success',
            'PAUSED'  => 'bg-secondary',
            'EXPIRED' => 'bg-danger',
            default   => 'bg-secondary',
        };
    }

    public function getAlertTypeBadgeClass(): string
    {
        return match ($this->alert_type) {
            'PRICE_ABOVE' => 'bg-success',
            'PRICE_BELOW' => 'bg-danger',
            default       => 'bg-secondary',
        };
    }

    public function getFormattedTargetPrice(): string
    {
        if (empty($this->tradeCurrencyModel)) {
            return MoneyFormat::get_formatted_price((float) $this->target_price, true);
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

    /**
     * Return active, non-expired alerts for the given symbols, keyed by symbol.
     *
     * @param string[] $symbols
     *
     * @return array<string, PriceAlert[]>
     */
    public static function activeBySymbols(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $alerts = static::whereIn('symbol', $symbols)
            ->where('status', 'ACTIVE')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('tradeCurrencyModel')
            ->orderBy('alert_type')
            ->get();

        $bySymbol = [];
        foreach ($alerts as $alert) {
            $bySymbol[$alert->symbol][] = $alert;
        }

        return $bySymbol;
    }
}
