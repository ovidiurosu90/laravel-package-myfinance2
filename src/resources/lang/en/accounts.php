<?php

return [
    'titles' => [
        'dashboard' => 'Accounts',
    ],
    'items-table' => [
        'caption' => '{1} :count account|[2,*] :count accounts',
        'none'    => 'No Accounts',
    ],
    'tooltips' => [
    ],
    'forms' => [
        'item-form' => [
            'currency' => [
                'label'         => 'Currency',
                'placeholder'   => 'Select Currency',
            ],
            'name' => [
                'label'         => 'Name',
                'placeholder'   => 'Input Name',
            ],
            'description' => [
                'label'         => 'Description',
                'placeholder'   => 'Input Description',
            ],
            'is_ledger_account' => [
                'label'         => 'Is Ledger',
            ],
            'is_trade_account' => [
                'label'         => 'Is Trade',
            ],
            'is_dividend_account' => [
                'label'         => 'Is Dividend',
            ],
            'funding_role' => [
                'label'   => 'Funding Role',
                'options' => [
                    'source'       => 'Source',
                    'intermediary' => 'Intermediary',
                    'investment'   => 'Investment',
                    'other'        => 'Other',
                ],
            ],
            'buttons' => [
                'save-item' => [
                    'name'      => 'Save Account',
                    'sr-icon'   => 'Save Account Icon',
                ],
                'update-item' => [
                    'name'      => 'Save Account Changes',
                    'sr-icon'   => 'Save Account Changes Icon',
                ],
            ],
        ],
    ],
];

