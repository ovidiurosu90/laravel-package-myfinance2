<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

class DividendFormFields extends MyFormFields
{
    protected function model() { return Dividend::class; }
    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'            => '',
        'account_id'           => null,
        'dividend_currency_id' => null,
        'exchange_rate'        => '',
        'symbol'               => '',
        'amount'               => '',
        'fee'                  => '',
        'description'          => '',
    ];

    /**
     * Get the additonal form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [
            'accounts'           => Account::with('currency')
                                        ->where('is_dividend_account', 1)
                                        ->get(),
            'dividendCurrencies' => Currency::where('is_dividend_currency', 1)
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
        $item = Dividend::with('accountModel', 'dividendCurrencyModel')
            ->findOrFail($id);

        $fieldNames = array_keys($fields);

        $fields = [
            'id'                    => $id,
            'accountModel'          => $item->accountModel,
            'dividendCurrencyModel' => $item->dividendCurrencyModel,
        ];
        foreach ($fieldNames as $field) {
            $fields[$field] = $item->{$field};
        }

        return $fields;
    }
}

