<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Rules\OwnRecord;

class CloseTrades extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return \Illuminate\Auth\Access\Response
     */
    public function authorize()
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'trades');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'account_id'        => ['required', 'integer', OwnRecord::of(Account::class)],
            'symbol'            => 'required|string|max:16',
        ];
    }
}

