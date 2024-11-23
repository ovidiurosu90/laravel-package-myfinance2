<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            return $this->user()->hasRole(config('ledger.guiCreateNewTransactionMiddleware'));
        }
        if (config('ledger.guiCreateNewTransactionMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(config('ledger.guiCreateNewTransactionMiddleware'));
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
            'timestamp'            => 'required|date_format:Y-m-d H:i:s',
            'type'                 => [
                'required',
                Rule::in(array_keys(config('ledger.transaction_types'))),
            ],
            'parent_id'            => 'nullable|integer|exists:' . config('ledger.ledgerTransactionsTable').',id',
            'debit_account'        => [
                'required',
                Rule::in(array_keys(config('general.ledger_accounts'))),
            ],
            'debit_currency'       => [
                'required',
                Rule::in(array_keys(config('general.ledger_currencies'))),
            ],
            'credit_account'        => [
                'required',
                Rule::in(array_keys(config('general.ledger_accounts'))),
            ],
            'credit_currency'       => [
                'required',
                Rule::in(array_keys(config('general.ledger_currencies'))),
            ],
            'exchange_rate'         => 'required|numeric',
            'amount'                => 'required|numeric',
            'fee'                   => 'required|numeric',
            'description'           => 'required|string|max:127',
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
            'type'                 => $this->type,
            'parent_id'            => $this->parent_id,
            'debit_account'        => $this->debit_account,
            'debit_currency'       => $this->debit_currency,
            'credit_account'       => $this->credit_account,
            'credit_currency'      => $this->credit_currency,
            'exchange_rate'        => $this->exchange_rate,
            'amount'               => $this->amount,
            'fee'                  => $this->fee,
            'description'          => $this->description,
        ];
    }
}

