<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

use ovidiuro\myfinance2\App\Rules\TradeQuantityIsAvailable;
use ovidiuro\myfinance2\App\Rules\OwnRecord;

class StoreTrade extends FormRequest
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
            'timestamp'         => 'required|date_format:Y-m-d H:i:s',
            'account_id'        => ['required', 'integer', OwnRecord::of(Account::class)],
            'trade_currency_id' => ['required', 'integer', OwnRecord::of(Currency::class)],
            'action'            => [
                'required',
                Rule::in(array_keys(config('myfinance2.trades.actions'))),
            ],
            'exchange_rate'     => 'required|numeric',
            'symbol'            => 'required|string|max:16',
            'quantity'          => [
                'required',
                'numeric',
                new TradeQuantityIsAvailable($this->id, $this->timestamp,
                    $this->action, $this->account_id, $this->symbol),
            ],
            'unit_price'        => 'required|numeric',
            'fee'               => 'required|numeric',
            'description'          => 'nullable|string|max:512',
            'is_transfer'          => 'nullable|boolean',
            'disable_auto_fx_rate' => 'nullable|boolean',
            'paired_account_id'    => [
                Rule::requiredIf(fn () => (bool) $this->disable_auto_fx_rate),
                'nullable',
                'integer',
                OwnRecord::of(Account::class),
            ],
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
            'account_id'        => $this->account_id,
            'trade_currency_id' => $this->trade_currency_id,
            'action'            => $this->action,
            'exchange_rate'     => $this->exchange_rate,
            'symbol'            => $this->symbol,
            'quantity'          => $this->quantity,
            'unit_price'        => $this->unit_price,
            'fee'               => $this->fee,
            'description'       => $this->description,
            'is_transfer'       => (bool) $this->is_transfer,
        ];
    }
}

