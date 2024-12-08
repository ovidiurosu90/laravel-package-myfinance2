<?php

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends MyFinance2Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'accounts';

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
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'id'                  => 'integer',
        'currency_id'         => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'deleted_at'          => 'datetime',
        'is_ledger_account'   => 'boolean',
        'is_trade_account'    => 'boolean',
        'is_dividend_account' => 'boolean',
    ];

    protected $fillable = [
        'currency_id',
        'name',
        'description',
        'is_ledger_account',
        'is_trade_account',
        'is_dividend_account',
    ];

    /**
     * Get the currency associated with the account.
     */
    public function currency(): HasOne
    {
        return $this->hasOne(Currency::class, 'id', 'currency_id');
    }
}

