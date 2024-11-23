<?php

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

class CashBalance extends Model
{
    use SoftDeletes;

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
        'amount'        => 'decimal:4',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
    ];

    protected $fillable = [
        'timestamp',
        'account',
        'account_currency',
        'amount',
        'description',
    ];

    public function getAccount()
    {
        return $this->account . ' ' . $this->account_currency;
    }

    public function getFormattedAmount()
    {
        return MoneyFormat::get_formatted_balance($this->account_currency, $this->amount);
    }
}

