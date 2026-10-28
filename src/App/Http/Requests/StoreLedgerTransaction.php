<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Rules\OwnRecord;

class StoreLedgerTransaction extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return \Illuminate\Auth\Access\Response
     */
    public function authorize()
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'ledger');
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
            'debit_account_id'  => ['required', 'integer', OwnRecord::of(Account::class)],
            'credit_account_id' => ['required', 'integer', OwnRecord::of(Account::class)],
            'type'              => [
                'required',
                Rule::in(array_keys(config('myfinance2.ledger.transaction_types'))),
            ],
            'parent_id'         => ['nullable', 'integer', OwnRecord::of(LedgerTransaction::class)],
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

