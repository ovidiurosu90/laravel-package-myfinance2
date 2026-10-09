<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;

class AlertFormFields extends MyFormFields
{
    protected function model()
    {
        return PriceAlert::class;
    }

    protected $fieldList = [
        'symbol'               => '',
        'alert_type'           => 'PRICE_ABOVE',
        'target_price'         => '',
        'target_mode'          => 'FIXED',
        'reference_type'       => 'HIGH',
        'reference_window'     => '1y',
        'offset_pct'           => '5',
        'trade_currency_id'    => null,
        'status'               => 'ACTIVE',
        'source'               => 'manual',
        'notification_channel' => 'email',
        'notes'                => '',
        'expires_at'           => '',
    ];

    protected function formFieldData()
    {
        return [
            'tradeCurrencies'  => Currency::where('is_trade_currency', 1)->get(),
            'alertTypes'       => [
                'PRICE_ABOVE' => 'Price Above (Sell signal)',
                'PRICE_BELOW' => 'Price Below (Buy signal)',
            ],
            'statuses'         => [
                'ACTIVE' => 'Active',
                'PAUSED' => 'Paused',
            ],
            'watchlistSymbols' => WatchlistSymbol::orderBy('symbol')->get(),
            'targetModes'      => [
                'FIXED'    => 'Fixed price',
                'RELATIVE' => 'Relative to high/low',
            ],
            'referenceOptions' => PriceAlert::referenceOptions(),
        ];
    }

    protected function fieldsFromModel($id, array $fields)
    {
        $item = PriceAlert::with('tradeCurrencyModel')->findOrFail($id);
        $fieldNames = array_keys($fields);

        $fields = [
            'id'                 => $id,
            'alertModel'         => $item,
            'tradeCurrencyModel' => $item->tradeCurrencyModel,
        ];

        foreach ($fieldNames as $field) {
            if ($field === 'expires_at') {
                $fields[$field] = $item->expires_at
                    ? $item->expires_at->format('Y-m-d H:i:s')
                    : '';
            } elseif ($field === 'offset_pct') {
                $fields[$field] = $item->offset_pct !== null
                    ? MoneyFormat::get_formatted_pct_compact($item->offset_pct)
                    : $this->fieldList[$field];
            } elseif (in_array($field, ['target_mode', 'reference_type', 'reference_window'], true)) {
                // A FIXED alert has no reference yet; keep the defaults so switching it to
                // RELATIVE in the form starts from a sensible choice.
                $fields[$field] = $item->{$field} ?? $this->fieldList[$field];
            } else {
                $fields[$field] = $item->{$field};
            }
        }

        return $fields;
    }
}
