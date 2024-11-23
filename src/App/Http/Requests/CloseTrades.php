<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseTrades extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (config('trades.guiCreateMiddlewareType') == 'role') {
            return $this->user()->hasRole(config('trades.guiCreateMiddleware'));
        }
        if (config('trades.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(config('trades.guiCreateMiddleware'));
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
            'account'          => [
                'required',
                Rule::in(array_keys(config('general.trade_accounts'))),
            ],
            'account_currency' => [
                'required',
                Rule::in(array_keys(config('general.ledger_currencies'))),
            ],
            'symbol'           => 'required|string|max:16',
        ];
    }
}

