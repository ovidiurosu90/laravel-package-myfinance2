<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

class AccountFormFields extends MyFormFields
{
    protected function model()
    {
        return Account::class;
    }

    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'currency_id'         => null,
        'name'                => '',
        'description'         => '',
        'is_ledger_account'   => true,
        'is_trade_account'    => true,
        'is_dividend_account' => true,
        'funding_role'        => null,
    ];

    /**
     * Get the additonal form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [
            'currencies' => Currency::get(),
        ];
    }
}

