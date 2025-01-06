<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

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
            return $this->user()->hasRole(
                config('trades.guiCreateMiddleware'));
        }
        if (config('trades.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(
                config('trades.guiCreateMiddleware'));
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
        $dbConnection = config('myfinance2.db_connection');
        $accountsTableName = $dbConnection . '.' . (new Account())->getTable();
        $currenciesTableName = $dbConnection . '.' . (new Currency())->getTable();

        return [
            'timestamp'         => 'required|date_format:Y-m-d H:i:s',
            'account_id'        => 'required|integer|exists:' .
                                        $accountsTableName . ',id',
            'trade_currency_id' => 'required|integer|exists:' .
                                        $currenciesTableName . ',id',
            'action'            => [
                'required',
                Rule::in(array_keys(config('trades.actions'))),
            ],
            'exchange_rate'     => 'required|numeric',
            'symbol'            => 'required|string|max:16',
            'quantity'          => [
                'required',
                'numeric',
                new TradeQuantityIsAvailable($this->id, $this->timestamp,
                    $this->action, $this->account_id, $this->symbol),
            ],
            'unit_price'        => 'required|numeric',
            'fee'               => 'required|numeric',
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
            'account_id'        => $this->account_id,
            'trade_currency_id' => $this->trade_currency_id,
            'action'            => $this->action,
            'exchange_rate'     => $this->exchange_rate,
            'symbol'            => $this->symbol,
            'quantity'          => $this->quantity,
            'unit_price'        => $this->unit_price,
            'fee'               => $this->fee,
            'description'       => $this->description,
        ];
    }
}

