<?php

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

use Illuminate\Database\Eloquent\Relations\HasOne;

class LedgerTransaction extends MyFinance2Model
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
        'debit_account_id'  => 'integer',
        'credit_account_id' => 'integer',
        'exchange_rate'     => 'decimal:4',
        'amount'            => 'decimal:2',
        'fee'               => 'decimal:2',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
        'parent_id'         => 'integer',
    ];

    protected $fillable = [
        'timestamp',
        'debit_account_id',
        'credit_account_id',
        'type',
        'exchange_rate',
        'amount',
        'fee',
        'description',
        'parent_id',
    ];

    /**
     * Get the debit account associated with the ledger transaction.
     */
    public function debitAccountModel(): HasOne
    {
        return $this->hasOne(Account::class, 'id', 'debit_account_id')
            ->with('currency');
    }

    /**
     * Get the credit account associated with the ledger transaction.
     */
    public function creditAccountModel(): HasOne
    {
        return $this->hasOne(Account::class, 'id', 'credit_account_id')
            ->with('currency');
    }

    public function parent_transaction()
    {
        return $this->belongsTo(LedgerTransaction::class, 'parent_id');
    }

    public function child_transactions()
    {
        return $this->hasMany(LedgerTransaction::class, 'parent_id');
    }

    public function getCleanExchangeRate()
    {
        return round($this->exchange_rate) == $this->exchange_rate
            ? round($this->exchange_rate)
            : round($this->exchange_rate, 4);
    }

    public function getFormattedAmount()
    {
        return MoneyFormat::get_formatted_amount(
            $this->getCurrency(true),
            $this->amount,
            strtolower($this->type),
            2
        );
    }

    public function getFormattedFee()
    {
        return MoneyFormat::get_formatted_fee(
            $this->getCurrency(true),
            $this->fee
        );
    }

    /**
     * @param boolean $isDisplayCurrency
     * @return string $currency
     */
    public function getCurrency($isDisplayCurrency = false)
    {
        $currency = null;

        switch ($this->type) {
            case 'DEBIT':
                $currency = $isDisplayCurrency
                    ? $this->debitAccountModel->currency->display_code
                    : $this->debitAccountModel->currency->iso_code;
                break;
            case 'CREDIT':
                $currency = $isDisplayCurrency
                    ? $this->creditAccountModel->currency->display_code
                    : $this->creditAccountModel->currency->iso_code;
                break;
            default:
                return null;
        }
        return $currency;
    }
}

