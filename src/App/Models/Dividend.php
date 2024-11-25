<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

class Dividend extends MyFinance2Model
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
        'amount'        => 'decimal:4',
        'fee'           => 'decimal:2',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    protected $fillable = [
        'timestamp',
        'account',
        'account_currency',
        'dividend_currency',
        'exchange_rate',
        'symbol',
        'amount',
        'fee',
        'description',
    ];

    public function getAccount()
    {
        return $this->account . ' ' . $this->account_currency;
    }

    public function getFormattedAmount()
    {
        return MoneyFormat::get_formatted_amount($this->dividend_currency, $this->amount);
    }

    public function getFormattedAmountInAccountCurrency()
    {
        //NOTE We use the inversed exchange rate
        $amount = $this->amount * 1 / $this->exchange_rate;
        return MoneyFormat::get_formatted_amount($this->account_currency, $amount);
    }

    public function getFormattedFee()
    {
        return MoneyFormat::get_formatted_fee($this->account_currency, $this->fee);
    }
}

