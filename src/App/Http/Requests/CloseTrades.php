<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

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
            'account_id'        => 'required|integer|exists:' .
                                        $accountsTableName . ',id',
            'symbol'            => 'required|string|max:16',
        ];
    }
}

