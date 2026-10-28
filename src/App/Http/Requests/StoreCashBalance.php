<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Rules\OwnRecord;

class StoreCashBalance extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return \Illuminate\Auth\Access\Response
     */
    public function authorize()
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'cashbalances');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'timestamp'   => 'required|date_format:Y-m-d H:i:s',
            'account_id'  => ['required', 'integer', OwnRecord::of(Account::class)],
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

