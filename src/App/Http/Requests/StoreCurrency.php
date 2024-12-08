<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurrency extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (config('currencies.guiCreateMiddlewareType') == 'role') {
            return $this->user()->hasRole(
                config('currencies.guiCreateMiddleware')
            );
        }
        if (config('currencies.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(
                config('currencies.guiCreateMiddleware')
            );
        }
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'iso_code'             => 'required|string|max:4',
            'display_code'         => 'required|string|max:16',
            'name'                 => 'required|string|max:64',
            'is_ledger_currency'   => 'boolean',
            'is_trade_currency'    => 'boolean',
            'is_dividend_currency' => 'boolean',
        ];
    }

    /**
     * Return the fields and values to create a new transaction.
     *
     * @return array
     */
    public function fillData()
    {
        return [
            'iso_code'             => $this->iso_code,
            'display_code'         => $this->display_code,
            'name'                 => $this->name,
            'is_ledger_currency'   => $this->is_ledger_currency ?
                                        $this->is_ledger_currency : false,
            'is_trade_currency'    => $this->is_trade_currency ?
                                        $this->is_trade_currency : false,
            'is_dividend_currency' => $this->is_dividend_currency ?
                                        $this->is_dividend_currency : false,
        ];
    }
}

