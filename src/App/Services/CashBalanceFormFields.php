<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\CashBalance;

class CashBalanceFormFields extends MyFormFields
{
    protected function model() { return CashBalance::class; }

    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'         => '',
        'account'           => '',
        'account_currency'  => '',
        'amount'            => '',
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
            'accounts'           => config('general.trade_accounts'),
            'accountCurrencies'  => config('general.ledger_currencies'),
        ];
    }
}

