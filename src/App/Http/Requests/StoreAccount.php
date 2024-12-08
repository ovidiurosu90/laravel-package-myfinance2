<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Currency;

class StoreAccount extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (config('accounts.guiCreateMiddlewareType') == 'role') {
            return $this->user()->hasRole(
                config('accounts.guiCreateMiddleware')
            );
        }
        if (config('accounts.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(
                config('accounts.guiCreateMiddleware')
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
        $dbConnection = config('myfinance2.db_connection');
        $tableName = $dbConnection . '.' . (new Currency())->getTable();

        return [
            'currency_id'         => 'required|integer|exists:' . $tableName . ',id',
            'name'                => 'required|string|max:64',
            'description'         => 'required|string|max:512',
            'is_ledger_account'   => 'boolean',
            'is_trade_account'    => 'boolean',
            'is_dividend_account' => 'boolean',
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
            'currency_id'         => $this->currency_id,
            'name'                => $this->name,
            'description'         => $this->description,
            'is_ledger_account'   => $this->is_ledger_account ?
                                        $this->is_ledger_account : false,
            'is_trade_account'    => $this->is_trade_account ?
                                        $this->is_trade_account : false,
            'is_dividend_account' => $this->is_dividend_account ?
                                        $this->is_dividend_account : false,
        ];
    }
}

