<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Rules\TradeQuantityIsAvailable;
use ovidiuro\myfinance2\App\Rules\OwnRecord;

class StoreOrder extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return Response
     */
    public function authorize(): Response
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'orders');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'symbol'            => 'required|string|max:16',
            'action'            => [
                'required',
                Rule::in(['BUY', 'SELL']),
            ],
            'status'            => [
                'required',
                Rule::in(['DRAFT', 'PLACED', 'FILLED', 'EXPIRED', 'CANCELLED']),
            ],
            'account_id'        => ['nullable', 'integer', OwnRecord::of(Account::class)],
            'trade_currency_id' => ['nullable', 'integer', OwnRecord::of(Currency::class)],
            'exchange_rate'     => 'nullable|numeric',
            'quantity'          => array_merge(
                ['nullable', 'numeric'],
                ($this->action === 'SELL' && !empty($this->account_id) && !empty($this->symbol))
                    ? [new TradeQuantityIsAvailable(null, null, 'SELL', (int) $this->account_id, $this->symbol)]
                    : []
            ),
            'limit_price'       => 'nullable|numeric',
            'trade_id'          => ['nullable', 'integer', OwnRecord::of(Trade::class)],
            'placed_at'         => 'nullable|date',
            'filled_at'         => 'nullable|date',
            'expired_at'        => 'nullable|date',
            'description'       => 'nullable|string|max:512',
        ];
    }

    /**
     * Return the fields and values to create a new order.
     *
     * @return array
     */
    public function fillData(): array
    {
        return [
            'symbol'            => $this->symbol,
            'action'            => $this->action,
            'status'            => $this->status ?? 'DRAFT',
            'account_id'        => $this->account_id,
            'trade_currency_id' => $this->trade_currency_id,
            'exchange_rate'     => $this->exchange_rate,
            'quantity'          => $this->quantity,
            'limit_price'       => $this->limit_price,
            'trade_id'          => $this->trade_id,
            'placed_at'         => $this->placed_at,
            'description'       => $this->description,
        ];
    }
}
