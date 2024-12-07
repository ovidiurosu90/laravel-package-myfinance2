<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Currency;

class CurrencyFormFields extends MyFormFields
{
    protected function model()
    {
        return Currency::class;
    }

    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'iso_code'             => '',
        'display_code'         => '',
        'name'                 => '',
        'is_ledger_currency'   => true,
        'is_trade_currency'    => true,
        'is_dividend_currency' => true,
    ];

    /**
     * Get the additonal form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [];
    }
}

