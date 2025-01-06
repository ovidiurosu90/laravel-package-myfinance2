<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

class TradeFormFields extends MyFormFields
{
    protected function model() { return Trade::class; }

    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'         => '',
        'account_id'        => null,
        'trade_currency_id' => null,
        'action'            => '',
        'exchange_rate'     => '',
        'symbol'            => '',
        'quantity'          => '',
        'unit_price'        => '',
        'fee'               => '',
        'description'       => '',
    ];

    /**
     * Get the additonal form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [
            'actions'         => config('trades.actions'),
            'accounts'        => Account::with('currency')
                                    ->where('is_trade_account', 1)
                                    ->get(),
            'tradeCurrencies' => Currency::where('is_trade_currency', 1)
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
        $item = Trade::with('accountModel', 'tradeCurrencyModel')
            ->findOrFail($id);

        $fieldNames = array_keys($fields);

        $fields = [
            'id'                 => $id,
            'accountModel'       => $item->accountModel,
            'tradeCurrencyModel' => $item->tradeCurrencyModel,
        ];
        foreach ($fieldNames as $field) {
            $fields[$field] = $item->{$field};
        }

        return $fields;
    }
}

