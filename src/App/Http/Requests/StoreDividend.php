<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDividend extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (config('dividends.guiCreateMiddlewareType') == 'role') {
            return $this->user()->hasRole(config('dividends.guiCreateMiddleware'));
        }
        if (config('dividends.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(config('dividends.guiCreateMiddleware'));
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
            'timestamp'         => 'required|date_format:Y-m-d H:i:s',
            'account'           => [
                'required',
                Rule::in(array_keys(config('general.dividend_accounts'))),
            ],
            'account_currency'  => [
                'required',
                Rule::in(array_keys(config('general.ledger_currencies'))),
            ],
            'dividend_currency' => [
                'required',
                Rule::in(array_keys(config('general.dividend_currencies'))),
            ],
            'exchange_rate'     => 'required|numeric',
            'symbol'            => 'required|string|max:16',
            'amount'            => 'required|numeric',
            'fee'               => 'required|numeric',
            'description'       => 'nullable|string|max:127',
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
            'timestamp'         => $this->timestamp,
            'account'           => $this->account,
            'account_currency'  => $this->account_currency,
            'dividend_currency' => $this->dividend_currency,
            'exchange_rate'     => $this->exchange_rate,
            'symbol'            => $this->symbol,
            'amount'            => $this->amount,
            'fee'               => $this->fee,
            'description'       => $this->description,
        ];
    }
}

