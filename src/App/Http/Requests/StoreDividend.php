<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

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
            return $this->user()->hasRole(
                config('dividends.guiCreateMiddleware'));
        }
        if (config('dividends.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(
                config('dividends.guiCreateMiddleware'));
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
            'timestamp'            => 'required|date_format:Y-m-d H:i:s',
            'account_id'           => 'required|integer|exists:' .
                                      $accountsTableName . ',id',
            'dividend_currency_id' => 'required|integer|exists:' .
                                      $currenciesTableName . ',id',
            'exchange_rate'        => 'required|numeric',
            'symbol'               => 'required|string|max:16',
            'amount'               => 'required|numeric',
            'fee'                  => 'required|numeric',
            'description'          => 'nullable|string|max:127',
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
            'timestamp'            => $this->timestamp,
            'account_id'           => $this->account_id,
            'dividend_currency_id' => $this->dividend_currency_id,
            'exchange_rate'        => $this->exchange_rate,
            'symbol'               => $this->symbol,
            'amount'               => $this->amount,
            'fee'                  => $this->fee,
            'description'          => $this->description,
        ];
    }
}

