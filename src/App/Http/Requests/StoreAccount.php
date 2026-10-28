<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Enums\FundingRole;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Rules\OwnRecord;

class StoreAccount extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return \Illuminate\Auth\Access\Response
     */
    public function authorize()
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'accounts');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'currency_id'         => ['required', 'integer', OwnRecord::of(Currency::class)],
            'name'                => 'required|string|max:64',
            'description'         => 'required|string|max:512',
            'is_ledger_account'   => 'boolean',
            'is_trade_account'    => 'boolean',
            'is_dividend_account' => 'boolean',
            'funding_role'        => ['nullable', new Enum(FundingRole::class)],
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
            'funding_role'        => $this->funding_role ?: null,
        ];
    }
}

