<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;

class StoreLedgerTransaction extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (config('ledger.guiCreateNewTransactionMiddlewareType') == 'role') {
            return $this->user()->hasRole(
                config('ledger.guiCreateNewTransactionMiddleware'));
        }
        if (config('ledger.guiCreateNewTransactionMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(
                config('ledger.guiCreateNewTransactionMiddleware'));
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
        $tableName = $dbConnection . '.' . (new LedgerTransaction())->getTable();
        $accountsTableName = $dbConnection . '.' . (new Account())->getTable();

        return [
            'timestamp'         => 'required|date_format:Y-m-d H:i:s',
            'debit_account_id'  => 'required|integer|exists:' .
                                        $accountsTableName . ',id',
            'credit_account_id' => 'required|integer|exists:' .
                                        $accountsTableName . ',id',
            'type'              => [
                'required',
                Rule::in(array_keys(config('ledger.transaction_types'))),
            ],
            'parent_id'         => 'nullable|integer|exists:' .
                                        $tableName . ',id',
            'exchange_rate'     => 'required|numeric',
            'amount'            => 'required|numeric',
            'fee'               => 'required|numeric',
            'description'       => 'required|string|max:127',
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
            'debit_account_id'  => $this->debit_account_id,
            'credit_account_id' => $this->credit_account_id,
            'type'              => $this->type,
            'parent_id'         => $this->parent_id,
            'exchange_rate'     => $this->exchange_rate,
            'amount'            => $this->amount,
            'fee'               => $this->fee,
            'description'       => $this->description,
        ];
    }
}

