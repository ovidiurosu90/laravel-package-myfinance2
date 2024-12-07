<?php

return [
    'titles' => [
        'dashboard' => 'Currencies',
    ],
    'items-table' => [
        'caption' => '{1} :count currency|[2,*] :count currencies',
        'none'    => 'No Currencies',
    ],
    'tooltips' => [
    ],
    'forms' => [
        'item-form' => [
            'iso_code' => [
                'label'         => 'ISO Code',
                'placeholder'   => 'Input ISO Code',
            ],
            'display_code' => [
                'label'         => 'Display Code',
                'placeholder'   => 'Input Display Code',
            ],
            'name' => [
                'label'         => 'Name',
                'placeholder'   => 'Input Name',
            ],
            'is_ledger_currency' => [
                'label'         => 'Is Ledger Currency',
            ],
            'is_trade_currency' => [
                'label'         => 'Is Trade Currency',
            ],
            'is_dividend_currency' => [
                'label'         => 'Is Dividend Currency',
            ],
            'buttons' => [
                'save-item' => [
                    'name'      => 'Save Currency',
                    'sr-icon'   => 'Save Currency Icon',
                ],
                'update-item' => [
                    'name'      => 'Save Currency Changes',
                    'sr-icon'   => 'Save Currency Changes Icon',
                ],
            ],
        ],
    ],
];

