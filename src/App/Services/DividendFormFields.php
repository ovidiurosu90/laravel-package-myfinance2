<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Dividend;

class DividendFormFields extends MyFormFields
{
    protected function model() { return Dividend::class; }
    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'         => '',
        'account'           => '',
        'account_currency'  => '',
        'dividend_currency' => '',
        'exchange_rate'     => '',
        'symbol'            => '',
        'amount'            => '',
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
            'accounts'           => config('general.dividend_accounts'),
            'accountCurrencies'  => config('general.ledger_currencies'),
            'dividendCurrencies' => config('general.dividend_currencies'),
        ];
    }
}

