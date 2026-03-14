<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;

class OrderFormFields extends MyFormFields
{
    protected function model()
    {
        return Order::class;
    }

    /**
     * List of fields and default values for each field.
     *
     * @var array
     */
    protected $fieldList = [
        'symbol'            => '',
        'action'            => '',
        'status'            => 'DRAFT',
        'account_id'        => null,
        'trade_currency_id' => null,
        'exchange_rate'     => '',
        'quantity'          => '',
        'limit_price'       => '',
        'trade_id'          => null,
        'placed_at'         => '',
        'description'       => '',
    ];

    /**
     * Get the additional form fields data.
     *
     * @return array
     */
    protected function formFieldData()
    {
        return [
            'accounts'         => Account::with('currency')
                                    ->where('is_trade_account', 1)
                                    ->get(),
            'tradeCurrencies'  => Currency::where('is_trade_currency', 1)
                                    ->get(),
            'statuses'         => [
                'DRAFT'     => 'Draft',
                'PLACED'    => 'Placed',
                'FILLED'    => 'Filled',
                'EXPIRED'   => 'Expired',
                'CANCELLED' => 'Cancelled',
            ],
            'watchlistSymbols' => WatchlistSymbol::orderBy('symbol')->get(),
        ];
    }

    /**
     * Return the field values from the model.
     *
     * @param int   $id
     * @param array $fields
     *
     * @return array
     */
    protected function fieldsFromModel($id, array $fields)
    {
        $item = Order::with('accountModel', 'tradeCurrencyModel')
            ->findOrFail($id);

        $fieldNames = array_keys($fields);

        $fields = [
            'id'                 => $id,
            'orderModel'         => $item,
            'accountModel'       => $item->accountModel,
            'tradeCurrencyModel' => $item->tradeCurrencyModel,
        ];
        foreach ($fieldNames as $field) {
            $fields[$field] = $item->{$field};
        }

        return $fields;
    }
}
