<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\FinanceAPI;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trade extends MyFinance2Model
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
        'timestamp',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'id'                => 'integer',
        'timestamp'         => 'datetime',
        'account_id'        => 'integer',
        'trade_currency_id' => 'integer',
        'exchange_rate'     => 'decimal:4',
        'quantity'          => 'decimal:8',
        'unit_price'        => 'decimal:4',
        'fee'               => 'decimal:2',
        'is_transfer'       => 'boolean',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    protected $fillable = [
        'timestamp',
        'account_id',
        'trade_currency_id',
        'action',
        'exchange_rate',
        'symbol',
        'quantity',
        'unit_price',
        'fee',
        'description',
        'is_transfer',
    ];

    /**
     * Get the account associated with the trade.
     */
    public function accountModel(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'id')
            ->with('currency');
    }

    /**
     * Get the currency associated with the trade.
     */
    public function tradeCurrencyModel(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'trade_currency_id', 'id');
    }

    /**
     * Get the orders linked to this trade.
     */
    public function linkedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'trade_id', 'id');
    }

    public function getShortLabel(): string
    {
        $date  = $this->timestamp ? $this->timestamp->format('Y-m-d') : '—';
        $parts = [$this->action, $this->getCleanQuantity() . 'x', $this->symbol];

        if (!empty($this->tradeCurrencyModel)) {
            $price    = MoneyFormat::get_formatted_price_plain($this->unit_price);
            $currency = html_entity_decode(
                $this->tradeCurrencyModel->display_code, ENT_HTML5, 'UTF-8'
            );
            $parts[] = '@ ' . $price . ' ' . $currency;
        }

        if (!empty($this->accountModel)) {
            $accountCurrency = html_entity_decode(
                $this->accountModel->currency->display_code, ENT_HTML5, 'UTF-8'
            );
            $parts[] = 'via ' . $this->accountModel->name . ' (' . $accountCurrency . ')';
        }

        $parts[] = 'on ' . $date;

        return implode(' ', $parts);
    }

    public function getCleanQuantity(): float|int
    {
        $quantity = (float) $this->quantity;

        return round($quantity) == $quantity
            ? (int) round($quantity)
            : (float) round($quantity, 6);
    }

    public function getCleanExchangeRate(): float|int
    {
        $rate = (float) $this->exchange_rate;

        return round($rate) == $rate
            ? (int) round($rate)
            : (float) round($rate, 4);
    }

    public function getFormattedSymbol()
    {
        if (FinanceAPI::isUnlisted($this->symbol)) {
            return '<span class="unlisted small">' . $this->symbol . '</span>';
        }

        return $this->symbol;
    }

    public function getFormattedUnitPrice()
    {
        $numDecimals = 0;
        if (round($this->unit_price, 2) == round($this->unit_price, 4)) {
            $numDecimals = 2;
        } else {
            $numDecimals = 4;
        }

        return MoneyFormat::get_formatted_amount(
            $this->tradeCurrencyModel->display_code,
            $this->unit_price,
            strtolower($this->action),
            $numDecimals
        );
    }

    public function getFormattedPrincipleAmount()
    {
        $amount = $this->quantity * $this->unit_price;
        return MoneyFormat::get_formatted_amount(
            $this->tradeCurrencyModel->display_code,
            $amount,
            strtolower($this->action),
            2
        );
    }

    public function getFormattedPrincipleAmountInAccountCurrency()
    {
        $amount = $this->quantity * $this->unit_price / $this->exchange_rate;
        return MoneyFormat::get_formatted_amount(
            $this->accountModel->currency->display_code,
            $amount,
            strtolower($this->action),
            2
        );
    }

    public function getFormattedFee()
    {
        return MoneyFormat::get_formatted_fee(
            $this->accountModel->currency->display_code, $this->fee);
    }
}

