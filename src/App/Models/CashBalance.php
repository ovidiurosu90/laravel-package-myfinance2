<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

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
        'timestamp'     => 'datetime',
        'account_id'    => 'integer',
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
        return $this->hasOne(Account::class, 'id', 'account_id')
            ->with('currency');
    }
    public function accountModelNoUser(): HasOne
    {
        if (php_sapi_name() !== 'cli') { // in browser we have 'apache2handler'
            abort(403, 'Access denied in CashBalance Model');
        }
        return $this->hasOne(Account::class, 'id', 'account_id')
            ->with('currencyNoUser')
            ->withoutGlobalScope(AssignedToUserScope::class);
    }

    public function getFormattedAmount()
    {
        return MoneyFormat::get_formatted_balance(
            $this->accountModel->currency->display_code,
            $this->amount
        );
    }
}

