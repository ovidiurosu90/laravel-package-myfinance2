<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\CashBalance;
use ovidiuro\myfinance2\App\Models\Account;

class CashBalanceFormFields extends MyFormFields
{
    protected function model() { return CashBalance::class; }

    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'   => '',
        'account_id'  => null,
        'amount'      => '',
        'description' => '',
    ];

    /**
     * Get the additonal form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [
            'accounts' => Account::with('currency')
                            ->where('is_trade_account', 1)->get(),
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
        $item = CashBalance::with('accountModel')->findOrFail($id);

        $fieldNames = array_keys($fields);

        $fields = [
            'id'           => $id,
            'accountModel' => $item->accountModel,
        ];
        foreach ($fieldNames as $field) {
            $fields[$field] = $item->{$field};
        }

        return $fields;
    }
}

