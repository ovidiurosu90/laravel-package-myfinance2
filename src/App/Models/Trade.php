<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

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
    public function accountModelNoUser(): HasOne
    {
        if (php_sapi_name() !== 'cli') { // in browser we have 'apache2handler'
            abort(403, 'Access denied in Trade Model');
        }
        return $this->hasOne(Account::class, 'id', 'account_id')
            ->with('currencyNoUser')
            ->withoutGlobalScope(AssignedToUserScope::class);
    }

    /**
     * Get the currency associated with the trade.
     */
    public function tradeCurrencyModel(): HasOne
    {
        return $this->hasOne(Currency::class, 'id', 'trade_currency_id');
    }
    public function tradeCurrencyModelNoUser(): HasOne
    {
        if (php_sapi_name() !== 'cli') { // in browser we have 'apache2handler'
            abort(403, 'Access denied in Trade Model');
        }
        return $this->hasOne(Currency::class, 'id', 'trade_currency_id')
            ->withoutGlobalScope(AssignedToUserScope::class);
    }

    public function getCleanQuantity()
    {
        return round($this->quantity) == $this->quantity
            ? round($this->quantity)
            : round($this->quantity, 6);
    }

    public function getCleanExchangeRate()
    {
        return round($this->exchange_rate) == $this->exchange_rate
            ? round($this->exchange_rate)
            : round($this->exchange_rate, 4);
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

