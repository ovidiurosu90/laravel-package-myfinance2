<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Currency;

class GetCurrencyExchangeGainEstimate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return \Illuminate\Auth\Access\Response
     */
    public function authorize()
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'ledger');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $isoCodes = [];
        $ledgerCurrencies = Currency::where('is_ledger_currency', 1)->get();
        foreach ($ledgerCurrencies as $currency) {
            $isoCodes[$currency->iso_code] = 1;
        }

        return [
            'debit_currency'  => [
                'required',
                Rule::in(array_keys($isoCodes)),
            ],
            'credit_currency' => [
                'required',
                Rule::in(array_keys($isoCodes)),
            ],
            'exchange_rate'   => 'required|numeric',
            'amount'          => 'required|numeric',
            'fee'             => 'required|numeric',
        ];
    }
}

