<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\WatchlistSymbol;

class WatchlistSymbolFormFields extends MyFormFields
{
    protected function model() { return WatchlistSymbol::class; }
    /**
     * List of fields and default value for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'timestamp'         => '',
        'symbol'            => '',
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
        ];
    }
}

