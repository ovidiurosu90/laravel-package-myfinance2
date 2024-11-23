<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Rules\TradeQuantityIsAvailable;

class StoreTrade extends FormRequest
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
            'timestamp'        => 'required|date_format:Y-m-d H:i:s',
            'action'           => [
                'required',
                Rule::in(array_keys(config('trades.actions'))),
            ],
            'account'          => [
                'required',
                Rule::in(array_keys(config('general.trade_accounts'))),
            ],
            'account_currency' => [
                'required',
                Rule::in(array_keys(config('general.ledger_currencies'))),
            ],
            'trade_currency'   => [
                'required',
                Rule::in(array_keys(config('general.trade_currencies'))),
            ],
            'exchange_rate'    => 'required|numeric',
            'symbol'           => 'required|string|max:16',
            'quantity'         => [
                'required',
                'numeric',
                new TradeQuantityIsAvailable($this->id, $this->timestamp, $this->action,
                    $this->account, $this->account_currency, $this->symbol),
            ],
            'unit_price'       => 'required|numeric',
            'fee'              => 'required|numeric',
            'description'      => 'nullable|string|max:512',
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
            'timestamp'        => $this->timestamp,
            'action'           => $this->action,
            'account'          => $this->account,
            'account_currency' => $this->account_currency,
            'trade_currency'   => $this->trade_currency,
            'exchange_rate'    => $this->exchange_rate,
            'symbol'           => $this->symbol,
            'quantity'         => $this->quantity,
            'unit_price'       => $this->unit_price,
            'fee'              => $this->fee,
            'description'      => $this->description,
        ];
    }
}

