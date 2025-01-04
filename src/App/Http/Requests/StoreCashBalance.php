<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;

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
            return $this->user()->hasRole(
                config('cashbalances.guiCreateMiddleware'));
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
        $dbConnection = config('myfinance2.db_connection');
        $tableName = $dbConnection . '.' . (new Account())->getTable();

        return [
            'timestamp'   => 'required|date_format:Y-m-d H:i:s',
            'account_id'  => 'required|integer|exists:' . $tableName . ',id',
            'amount'      => 'required|numeric',
            'description' => 'nullable|string|max:512',
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
            'timestamp'   => $this->timestamp,
            'account_id'  => $this->account_id,
            'amount'      => $this->amount,
            'description' => $this->description,
        ];
    }
}

