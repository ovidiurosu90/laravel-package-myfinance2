<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

class Order extends MyFinance2Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'placed_at',
        'filled_at',
        'expired_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'id'                => 'integer',
        'account_id'        => 'integer',
        'trade_currency_id' => 'integer',
        'trade_id'          => 'integer',
        'quantity'          => 'decimal:8',
        'limit_price'       => 'decimal:4',
        'exchange_rate'     => 'decimal:4',
        'placed_at'         => 'datetime',
        'filled_at'         => 'datetime',
        'expired_at'        => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    protected $fillable = [
        'account_id',
        'trade_currency_id',
        'symbol',
        'action',
        'status',
        'quantity',
        'limit_price',
        'exchange_rate',
        'trade_id',
        'placed_at',
        'filled_at',
        'expired_at',
        'description',
    ];

    /**
     * Get the account associated with the order.
     */
    public function accountModel(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id')
            ->with('currency');
    }

    /**
     * Get the currency associated with the order.
     */
    public function tradeCurrencyModel(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'trade_currency_id', 'id');
    }

    /**
     * Get the linked trade for this order.
     */
    public function tradeModel(): BelongsTo
    {
        return $this->belongsTo(Trade::class, 'trade_id', 'id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['DRAFT', 'PLACED', 'FILLED']);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['FILLED', 'EXPIRED', 'CANCELLED']);
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'DRAFT'     => 'bg-warning text-dark',
            'PLACED'    => 'bg-primary',
            'FILLED'    => 'bg-success',
            'EXPIRED'   => 'bg-secondary',
            'CANCELLED' => 'bg-secondary opacity-75',
            default     => 'bg-secondary',
        };
    }

    public function getShortLabel(): string
    {
        $parts = [$this->action];

        if (!empty($this->quantity)) {
            $parts[] = $this->getCleanQuantity() . 'x';
        }

        $parts[] = $this->symbol;

        if (!empty($this->limit_price) && !empty($this->tradeCurrencyModel)) {
            $price    = MoneyFormat::get_formatted_price_plain($this->limit_price);
            $currency = html_entity_decode(
                $this->tradeCurrencyModel->display_code, ENT_HTML5, 'UTF-8'
            );
            $parts[] = '@ ' . $price . ' ' . $currency;

            if (!empty($this->quantity)) {
                $principal = (float) $this->quantity * (float) $this->limit_price;
                $parts[]   = '≈ ' . number_format($principal, 2) . ' ' . $currency;

                $rate = (float) $this->exchange_rate;
                if ($rate > 0 && $rate !== 1.0 && !empty($this->accountModel)) {
                    $accountCurrency = html_entity_decode(
                        $this->accountModel->currency->display_code, ENT_HTML5, 'UTF-8'
                    );
                    $parts[] = '(~' . number_format($principal / $rate, 2)
                        . ' ' . $accountCurrency . ')';
                }
            }
        }

        return implode(' ', $parts);
    }

    public function getCleanQuantity(): float|int
    {
        if (empty($this->quantity)) {
            return 0;
        }

        $quantity = (float) $this->quantity;

        return round($quantity) == $quantity
            ? (int) round($quantity)
            : (float) round($quantity, 6);
    }

    public function getFormattedLimitPrice(): string
    {
        if (empty($this->limit_price) || empty($this->tradeCurrencyModel)) {
            return '—';
        }

        return MoneyFormat::get_formatted_balance(
            $this->tradeCurrencyModel->display_code,
            $this->limit_price
        );
    }

    public function getFormattedPrincipleAmount(): string
    {
        if (empty($this->quantity) || empty($this->limit_price)
            || empty($this->tradeCurrencyModel)
        ) {
            return '—';
        }

        $amount = $this->quantity * $this->limit_price;

        return MoneyFormat::get_formatted_amount(
            $this->tradeCurrencyModel->display_code,
            $amount,
            strtolower($this->action),
            2
        );
    }
}
