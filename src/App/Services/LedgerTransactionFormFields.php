<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;

class LedgerTransactionFormFields extends MyFormFields
{
    protected function model() { return LedgerTransaction::class; }

    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'            => '',
        'type'                 => '',
        'debit_account'        => '',
        'credit_account'       => '',
        'debit_currency'       => '',
        'credit_currency'      => '',
        'exchange_rate'        => '',
        'amount'               => '',
        'fee'                  => '',
        'description'          => '',
        'parent_id'            => null,
    ];

    /**
     * Return the field values from the parent.
     *
     * @param int   $parentId
     *
     * @return array
     */
    protected function fieldsFromParent($parentId)
    {
        $item = LedgerTransaction::findOrFail($parentId);

        return array_merge($this->fieldList, [
            'parent_id'       => $item->id,
            'debit_account'   => $item->debit_account,
            'debit_currency'  => $item->debit_currency,
            'credit_account'  => $item->credit_account,
            'credit_currency' => $item->credit_currency,
            'exchange_rate'   => $item->exchange_rate,
            'timestamp'       => $item->timestamp->add(new \DateInterval('PT1M'))
        ]);
    }

    /**
     * Get the additonal form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [
            'transactionTypes' => config('ledger.transaction_types'),
            'debitAccounts'    => config('general.ledger_accounts'),
            'creditAccounts'   => config('general.ledger_accounts'),
            'debitCurrencies'  => config('general.ledger_currencies'),
            'creditCurrencies' => config('general.ledger_currencies'),
            'rootTransactions' => LedgerTransaction::whereNull('parent_id')->get(),
        ];
    }
}

