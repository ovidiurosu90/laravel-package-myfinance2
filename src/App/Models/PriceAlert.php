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

    /**
     * Short countdown label for the expiry (e.g. "in 2d", "today", "expired"),
     * or null when the alert has no expiry. Shared by every view that surfaces
     * an alert so the wording stays identical.
     */
    public function getExpiryLabel(): ?string
    {
        if ($this->expires_at === null) {
            return null;
        }

        $expLocal = $this->expires_at->timezone(config('app.timezone'));
        if ($expLocal->isPast()) {
            return 'expired';
        }

        $daysLeft = (int) now(config('app.timezone'))->diffInDays($expLocal);

        return match (true) {
            $daysLeft === 0 => 'today',
            $daysLeft === 1 => 'tmrw',
            default         => "in {$daysLeft}d",
        };
    }

    /**
     * Bootstrap badge class for the expiry countdown, or null when there is no
     * expiry. Red once past, amber within three days, muted otherwise.
     */
    public function getExpiryBadgeClass(): ?string
    {
        if ($this->expires_at === null) {
            return null;
        }

        $expLocal = $this->expires_at->timezone(config('app.timezone'));
        if ($expLocal->isPast()) {
            return 'bg-danger';
        }

        $daysLeft = (int) now(config('app.timezone'))->diffInDays($expLocal);

        return $daysLeft <= 3 ? 'bg-warning text-dark' : 'bg-secondary';
    }

    /**
     * Human phrase for the expiry, ready for a tooltip (e.g. "Expires in 2d
     * (2026-07-07)" or "Expired (2026-07-01)"), or null when there is no expiry.
     */
    public function getExpiryTooltip(): ?string
    {
        $label = $this->getExpiryLabel();
        if ($label === null) {
            return null;
        }

        $date = $this->expires_at->timezone(config('app.timezone'))->format('Y-m-d');

        return $label === 'expired'
            ? "Expired ({$date})"
            : "Expires {$label} ({$date})";
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
