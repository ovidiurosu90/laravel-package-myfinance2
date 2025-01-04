<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'id'                   => 'integer',
        'timestamp'            => 'datetime',
        'account_id'           => 'integer',
        'dividend_currency_id' => 'integer',
        'exchange_rate'        => 'decimal:4',
        'amount'               => 'decimal:4',
        'fee'                  => 'decimal:2',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
        'deleted_at'           => 'datetime',
    ];

    protected $fillable = [
        'timestamp',
        'account_id',
        'dividend_currency_id',
        'exchange_rate',
        'symbol',
        'amount',
        'fee',
        'description',
    ];

    /**
     * Get the account associated with the dividend.
     */
    public function accountModel(): HasOne
    {
        return $this->hasOne(Account::class, 'id', 'account_id')
            ->with('currency');
    }

    /**
     * Get the currency associated with the dividend.
     */
    public function dividendCurrencyModel(): HasOne
    {
        return $this->hasOne(Currency::class, 'id', 'dividend_currency_id');
    }

    public function getFormattedAmount()
    {
        return MoneyFormat::get_formatted_amount(
            $this->dividendCurrencyModel->iso_code, $this->amount);
    }

    public function getFormattedAmountInAccountCurrency()
    {
        //NOTE We use the inversed exchange rate
        $amount = $this->amount * 1 / $this->exchange_rate;

        return MoneyFormat::get_formatted_amount(
            $this->accountModel->currency->iso_code, $amount);
    }

    public function getFormattedFee()
    {
        return MoneyFormat::get_formatted_fee(
            $this->accountModel->currency->iso_code, $this->fee);
    }
}

