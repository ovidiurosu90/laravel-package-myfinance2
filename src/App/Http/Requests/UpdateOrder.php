<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Requests;

class UpdateOrder extends StoreOrder
{
    /**
     * Override rules — status is not editable via the form (changed via actions only).
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['status']);

        return $rules;
    }

    /**
     * Return the fields and values to update an order.
     *
     * @param int $id
     *
     * @return array
     */
    public function fillData(?int $id = null): array
    {
        return [
            'symbol'            => $this->symbol,
            'action'            => $this->action,
            'account_id'        => $this->account_id,
            'trade_currency_id' => $this->trade_currency_id,
            'exchange_rate'     => $this->exchange_rate,
            'quantity'          => $this->quantity,
            'limit_price'       => $this->limit_price,
            'placed_at'         => $this->placed_at,
            'description'       => $this->description,
        ];
    }
}
