<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Rules\TradeQuantityIsAvailable;

class StoreOrder extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if (config('orders.guiCreateMiddlewareType') == 'role') {
            return $this->user()->hasRole(
                config('orders.guiCreateMiddleware'));
        }
        if (config('orders.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(
                config('orders.guiCreateMiddleware'));
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $dbConnection = config('myfinance2.db_connection');
        $accountsTableName = $dbConnection . '.' . (new Account())->getTable();
        $currenciesTableName = $dbConnection . '.' . (new Currency())->getTable();

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
            'account_id'        => 'nullable|integer|exists:' .
                                        $accountsTableName . ',id',
            'trade_currency_id' => 'nullable|integer|exists:' .
                                        $currenciesTableName . ',id',
            'exchange_rate'     => 'nullable|numeric',
            'quantity'          => array_merge(
                ['nullable', 'numeric'],
                ($this->action === 'SELL' && !empty($this->account_id) && !empty($this->symbol))
                    ? [new TradeQuantityIsAvailable(null, null, 'SELL', (int) $this->account_id, $this->symbol)]
                    : []
            ),
            'limit_price'       => 'nullable|numeric',
            'trade_id'          => 'nullable|integer',
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
