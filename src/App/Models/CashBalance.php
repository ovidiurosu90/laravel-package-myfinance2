<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

use Illuminate\Database\Eloquent\Relations\HasOne;

class CashBalance extends MyFinance2Model
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
        'account_id'    => 'integer',
        'timestamp'     => 'datetime',
        'amount'        => 'decimal:4',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    protected $fillable = [
        'timestamp',
        'account_id',
        'amount',
        'description',
    ];

    /**
     * Get the account associated with the cash balance.
     */
    public function accountModel(): HasOne
    {
        return $this->hasOne(Account::class, 'id', 'account_id');
    }

    public function getFormattedAmount()
    {
        return MoneyFormat::get_formatted_balance(
            $this->accountModel->currency->iso_code, $this->amount);
    }
}

