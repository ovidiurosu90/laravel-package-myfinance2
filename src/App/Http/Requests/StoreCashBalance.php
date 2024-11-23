<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashBalance extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (config('cashbalances.guiCreateMiddlewareType') == 'role') {
            return $this->user()->hasRole(config('cashbalances.guiCreateMiddleware'));
        }
        if (config('cashbalances.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(
                config('cashbalances.guiCreateMiddleware'));
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
                Rule::in(array_keys(config('general.trade_accounts'))),
            ],
            'account_currency'  => [
                'required',
                Rule::in(array_keys(config('general.ledger_currencies'))),
            ],
            'amount'            => 'required|numeric',
            'description'       => 'nullable|string|max:512',
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
            'amount'            => $this->amount,
            'description'       => $this->description,
        ];
    }
}

