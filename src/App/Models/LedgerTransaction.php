<?php

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

class LedgerTransaction extends Model
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
        'id'                   => 'integer',
        'timestamp'            => 'datetime',
        'exchange_rate'        => 'decimal:4',
        'amount'               => 'decimal:2',
        'fee'                  => 'decimal:2',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
        'deleted_at'           => 'datetime',
        'parent_id'            => 'integer',
    ];

    protected $fillable = [
        'timestamp',
        'type',
        'debit_account',
        'credit_account',
        'debit_currency',
        'credit_currency',
        'exchange_rate',
        'amount',
        'fee',
        'description',
        'parent_id',
    ];

    public function parent_transaction()
    {
        return $this->belongsTo(LedgerTransaction::class, 'parent_id');
    }

    public function child_transactions()
    {
        return $this->hasMany(LedgerTransaction::class, 'parent_id');
    }

    public function getDebitAccount()
    {
        return $this->debit_account . ' ' . $this->debit_currency;
    }

    public function getCreditAccount()
    {
        return $this->credit_account . ' ' . $this->credit_currency;
    }

    public function getFormattedAmount()
    {
        return MoneyFormat::get_formatted_amount($this->getCurrency(), $this->amount,
                                                 strtolower($this->type), 2);
    }

    public function getFormattedFee()
    {
        return MoneyFormat::get_formatted_fee($this->getCurrency(), $this->fee);
    }

    /**
     * @return string $currency
     */
    public function getCurrency()
    {
        $currency = null;

        switch ($this->type) {
            case 'DEBIT':
                $currency = $this->debit_currency;
                break;
            case 'CREDIT':
                $currency = $this->credit_currency;
                break;
            default:
        }
        return $currency;
    }
}

