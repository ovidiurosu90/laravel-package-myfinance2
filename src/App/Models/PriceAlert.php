<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

class PriceAlert extends MyFinance2Model
{
    public const TARGET_MODES = ['FIXED', 'RELATIVE'];

    public const REFERENCE_TYPES = ['HIGH', 'LOW'];

    /**
     * Rolling windows a relative target can reference, in calendar days. Same windows as the
     * exit-zone peaks (DrawdownService::_computeAllExitZones) and the peak-proximity alerts.
     */
    public const REFERENCE_WINDOW_DAYS = ['3m' => 91, '6m' => 182, '1y' => 365, '2y' => 730];

    public const REFERENCE_WINDOW_LABELS = ['3m' => '3M', '6m' => '6M', '1y' => '52W', '2y' => '2Y'];

    /**
     * Upper bound of the offset per reference type. A HIGH reference only goes below the high
     * (a target at or under 0 makes no sense), a LOW reference only goes above the low.
     */
    public const MAX_OFFSET_PCT = ['HIGH' => 95, 'LOW' => 500];

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
        'trade_currency_id'     => 'integer',
        'target_price'          => 'decimal:6',
        'offset_pct'            => 'decimal:3',
        'reference_price'       => 'decimal:6',
        'reference_date'        => 'date',
        'reference_resolved_at' => 'datetime',
        'trigger_count'         => 'integer',
        'last_triggered_at'     => 'datetime',
        'expires_at'            => 'datetime',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
        'deleted_at'            => 'datetime',
    ];

    protected $fillable = [
        'symbol',
        'alert_type',
        'target_price',
        'target_mode',
        'reference_type',
        'reference_window',
        'offset_pct',
        'reference_price',
        'reference_date',
        'reference_resolved_at',
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

    /**
     * Whether the target follows a rolling closing high / low instead of a fixed price.
     */
    public function isRelative(): bool
    {
        return $this->target_mode === 'RELATIVE';
    }

    /**
     * Long label of a relative target, e.g. "5% below 52W high" or "at 3M low". Null for a
     * FIXED alert. Shared by every surface (tables, badges, emails) so the wording is identical.
     */
    public function getRelativeTargetLabel(): ?string
    {
        if (!$this->isRelative() || empty($this->reference_type) || empty($this->reference_window)) {
            return null;
        }

        return self::buildRelativeLabel(
            (string) $this->reference_type,
            (string) $this->reference_window,
            (float) $this->offset_pct
        );
    }

    /**
     * Compact label for badges, e.g. "52W H -5%", "3M L +10%" or "52W H". Null for a FIXED alert.
     */
    public function getShortRelativeLabel(): ?string
    {
        if (!$this->isRelative() || empty($this->reference_type) || empty($this->reference_window)) {
            return null;
        }

        return self::buildShortRelativeLabel(
            (string) $this->reference_type,
            (string) $this->reference_window,
            (float) $this->offset_pct
        );
    }

    /**
     * Tooltip describing the resolved reference, e.g. "52W closing high 123.45 USD on 12 Mar 2026,
     * as of 09 Oct 2026 14:35". Null for a FIXED alert or before the first resolution.
     */
    public function getReferenceTooltip(): ?string
    {
        if (!$this->isRelative() || $this->reference_price === null) {
            return null;
        }

        $currency = html_entity_decode(
            strip_tags((string) ($this->tradeCurrencyModel?->display_code ?? '')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $text = (self::REFERENCE_WINDOW_LABELS[$this->reference_window] ?? $this->reference_window)
            . ' closing ' . ($this->reference_type === 'HIGH' ? 'high' : 'low') . ' '
            . trim(MoneyFormat::get_formatted_price((float) $this->reference_price, true) . ' ' . $currency);

        if ($this->reference_date !== null) {
            $text .= ' on ' . $this->reference_date->format('d M Y');
        }
        if ($this->reference_resolved_at !== null) {
            $text .= ', as of '
                . $this->reference_resolved_at->timezone(config('app.timezone'))->format('d M Y H:i');
        }

        return $text;
    }

    /**
     * Whether an ACTIVE, non-expired relative alert has gone more than a day without a successful
     * resolution (missing history, e.g. right after a stock split), so its stored target may be
     * outdated. Paused and expired alerts are not re-resolved, so they are never flagged.
     */
    public function isReferenceStale(): bool
    {
        if (!$this->isRelative() || !$this->canFire()) {
            return false;
        }

        return $this->reference_resolved_at === null
            || $this->reference_resolved_at->lt(now()->subDay());
    }

    /**
     * Tooltip lines of a price alert badge (watchlist, orders) for a RELATIVE alert, whose badge
     * shows only the short label: the target price with the long label, a stale-reference warning
     * and the resolved reference. Empty for a FIXED alert. The lines are HTML: everything is
     * escaped except $priceHtml, which is already formatted markup (currency entities).
     *
     * @return string[]
     */
    public function getRelativeBadgeTooltipLines(string $priceHtml): array
    {
        if (!$this->isRelative()) {
            return [];
        }

        $lines = ['Target: ' . $priceHtml . ' (' . e($this->getRelativeTargetLabel()) . ')'];
        if ($this->isReferenceStale()) {
            $lines[] = 'Reference unavailable: the target may be outdated';
        }
        $referenceTooltip = $this->getReferenceTooltip();
        if ($referenceTooltip !== null) {
            $lines[] = e($referenceTooltip);
        }

        return $lines;
    }

    /**
     * Snapshot of the relative target for the notification log, e.g. "5% below 52W high, ref
     * 123.45 on 2026-03-12". Null for a FIXED alert. Capped at the column width (64).
     */
    public function getTargetLabelSnapshot(): ?string
    {
        $label = $this->getRelativeTargetLabel();
        if ($label === null) {
            return null;
        }

        if ($this->reference_price !== null) {
            $label .= ', ref ' . MoneyFormat::get_formatted_price((float) $this->reference_price, true);
            if ($this->reference_date !== null) {
                $label .= ' on ' . $this->reference_date->format('Y-m-d');
            }
        }

        return mb_substr($label, 0, 64);
    }

    /**
     * "5% below 52W high", "10% above 6M low", "at 3M low". The direction is implied by the
     * reference type: a HIGH reference only goes below the high, a LOW one only above the low.
     */
    public static function buildRelativeLabel(string $type, string $window, float $offsetPct): string
    {
        $reference = (self::REFERENCE_WINDOW_LABELS[$window] ?? $window) . ' '
            . ($type === 'HIGH' ? 'high' : 'low');

        if ($offsetPct <= 0.0) {
            return "at {$reference}";
        }

        $direction = $type === 'HIGH' ? 'below' : 'above';

        return MoneyFormat::get_formatted_pct_compact($offsetPct) . "% {$direction} {$reference}";
    }

    /**
     * "52W H -5%", "6M L +10%", "3M L" (offset 0).
     */
    public static function buildShortRelativeLabel(string $type, string $window, float $offsetPct): string
    {
        $label = (self::REFERENCE_WINDOW_LABELS[$window] ?? $window) . ' ' . ($type === 'HIGH' ? 'H' : 'L');

        if ($offsetPct <= 0.0) {
            return $label;
        }

        return $label . ' ' . ($type === 'HIGH' ? '-' : '+')
            . MoneyFormat::get_formatted_pct_compact($offsetPct) . '%';
    }

    /**
     * Options of the form's combined reference select, keyed "TYPE:window" and labelled by the
     * reference only ("52W high"). No "below" / "above" here: the form shows the offset as a
     * formula (52W high - 5%), so the only direction words on the form belong to the alert type.
     *
     * @return array<string, string>
     */
    public static function referenceOptions(): array
    {
        $options = [];
        foreach (self::REFERENCE_TYPES as $type) {
            foreach (array_keys(self::REFERENCE_WINDOW_DAYS) as $window) {
                $options["{$type}:{$window}"] = self::REFERENCE_WINDOW_LABELS[$window] . ' '
                    . ($type === 'HIGH' ? 'high' : 'low');
            }
        }

        return $options;
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
