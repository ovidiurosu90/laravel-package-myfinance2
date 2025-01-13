<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Models\Account;

class LedgerTransactionFormFields extends MyFormFields
{
    protected function model() { return LedgerTransaction::class; }

    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'         => '',
        'debit_account_id'  => null,
        'credit_account_id' => null,
        'type'              => '',
        'exchange_rate'     => '',
        'amount'            => '',
        'fee'               => '',
        'description'       => '',
        'parent_id'         => null,
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
        $item = LedgerTransaction::with('debitAccountModel', 'creditAccountModel')
            ->findOrFail($parentId);

        return array_merge($this->fieldList, [
            'parent_id'          => $item->id,
            'debit_account_id'   => $item->debit_account_id,
            'credit_account_id'  => $item->credit_account_id,
            'debitAccountModel'  => $item->debitAccountModel,
            'creditAccountModel' => $item->creditAccountModel,
            'exchange_rate'      => $item->exchange_rate,
            'timestamp'          => $item->timestamp->add(new \DateInterval('PT1M'))
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
            'accounts'         => Account::with('currency')
                                    ->where('is_ledger_account', 1)
                                    ->get(),
            'rootTransactions' => LedgerTransaction
                                    ::with('debitAccountModel', 'creditAccountModel')
                                    ->whereNull('parent_id')
                                    ->get(),
        ];
    }

    /**
     * Return the field values from the model.
     *
     * @param int   $id
     * @param array $fields
     *
     * @return array
     */
    protected function fieldsFromModel($id, array $fields)
    {
        $item = LedgerTransaction::with('debitAccountModel', 'creditAccountModel')
            ->findOrFail($id);

        $fieldNames = array_keys($fields);

        $fields = [
            'id'                  => $id,
            'debitAccountModel'   => $item->debitAccountModel,
            'creditAccountModel'  => $item->creditAccountModel,
        ];
        foreach ($fieldNames as $field) {
            $fields[$field] = $item->{$field};
        }

        return $fields;
    }
}

