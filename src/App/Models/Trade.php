<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

use Illuminate\Database\Eloquent\Relations\HasOne;

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
    ];

    /**
     * Get the account associated with the trade.
     */
    public function accountModel(): HasOne
    {
        return $this->hasOne(Account::class, 'id', 'account_id')
            ->with('currency');
    }

    /**
     * Get the currency associated with the trade.
     */
    public function tradeCurrencyModel(): HasOne
    {
        return $this->hasOne(Currency::class, 'id', 'trade_currency_id');
    }

    public function getCleanQuantity()
    {
        return round($this->quantity) == $this->quantity ?
            round($this->quantity) : $this->quantity;
    }

    public function getFormattedUnitPrice()
    {
        return MoneyFormat::get_formatted_amount(
            $this->tradeCurrencyModel->display_code,
            $this->unit_price,
            strtolower($this->action)
        );
    }

    public function getPrincipleAmount()
    {
        return $this->quantity * $this->unit_price . ' ' . $this->trade_currency;
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

