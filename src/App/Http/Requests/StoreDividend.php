<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Rules\OwnRecord;

class StoreDividend extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return \Illuminate\Auth\Access\Response
     */
    public function authorize()
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'dividends');
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
            'account_id'           => ['required', 'integer', OwnRecord::of(Account::class)],
            'dividend_currency_id' => ['required', 'integer', OwnRecord::of(Currency::class)],
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

