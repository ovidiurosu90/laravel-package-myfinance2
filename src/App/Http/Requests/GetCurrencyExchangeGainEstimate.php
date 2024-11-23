<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetCurrencyExchangeGainEstimate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (config('ledger.guiCreateNewTransactionMiddlewareType') == 'role') {
            return $this->user()->hasRole(config('ledger.guiCreateNewTransactionMiddleware'));
        }
        if (config('ledger.guiCreateNewTransactionMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(config('ledger.guiCreateNewTransactionMiddleware'));
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
            'debit_currency'  => [
                'required',
                Rule::in(array_keys(config('general.ledger_currencies'))),
            ],
            'credit_currency' => [
                'required',
                Rule::in(array_keys(config('general.ledger_currencies'))),
            ],
            'exchange_rate'   => 'required|numeric',
            'amount'          => 'required|numeric',
            'fee'             => 'required|numeric',
        ];
    }
}

