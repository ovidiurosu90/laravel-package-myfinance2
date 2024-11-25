<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

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
        'id'            => 'integer',
        'timestamp'     => 'datetime',
        'exchange_rate' => 'decimal:4',
        'quantity'      => 'decimal:8',
        'unit_price'    => 'decimal:4',
        'fee'           => 'decimal:2',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    protected $fillable = [
        'timestamp',
        'action',
        'account',
        'account_currency',
        'trade_currency',
        'exchange_rate',
        'symbol',
        'quantity',
        'unit_price',
        'fee',
        'description',
    ];

    public function getAccount()
    {
        return $this->account . ' ' . $this->account_currency;
    }

    public function getCleanQuantity()
    {
        return round($this->quantity) == $this->quantity ?
            round($this->quantity) : $this->quantity;
    }

    public function getFormattedUnitPrice()
    {
        return MoneyFormat::get_formatted_amount(
            $this->trade_currency,
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
            $this->trade_currency,
            $amount,
            strtolower($this->action),
            2
        );
    }

    public function getFormattedPrincipleAmountInAccountCurrency()
    {
        $amount = $this->quantity * $this->unit_price / $this->exchange_rate;
        return MoneyFormat::get_formatted_amount(
            $this->account_currency,
            $amount,
            strtolower($this->action),
            2
        );
    }

    public function getFormattedFee()
    {
        return MoneyFormat::get_formatted_fee($this->account_currency, $this->fee);
    }
}

