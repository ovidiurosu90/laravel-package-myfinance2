<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Trade;

class TradeFormFields extends MyFormFields
{
    protected function model() { return Trade::class; }

    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'        => '',
        'action'           => '',
        'account'          => '',
        'account_currency' => '',
        'trade_currency'   => '',
        'exchange_rate'    => '',
        'symbol'           => '',
        'quantity'         => '',
        'unit_price'       => '',
        'fee'              => '',
        'description'      => '',
    ];

    /**
     * Get the additonal form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [
            'actions'           => config('trades.actions'),
            'accounts'          => config('general.trade_accounts'),
            'accountCurrencies' => config('general.ledger_currencies'),
            'tradeCurrencies'   => config('general.trade_currencies'),
        ];
    }
}

