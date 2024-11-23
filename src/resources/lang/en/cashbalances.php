<?php

return [
    'titles' => [
        'dashboard'   => 'Cash',
    ],
    'items-table' => [
        'caption' => '{1} :count balance|[2,*] :count balances',
        'none'    => 'No Cash Balances',
    ],
    'forms' => [
        'item-form' => [
            'timestamp' => [
                'label'         => 'Timestamp',
            ],
            'account' => [
                'label'         => 'Account',
                'placeholder'   => 'Select Account',
            ],
            'account_currency' => [
                'label'         => 'Account Currency',
                'placeholder'   => 'Select Account Currency',
            ],
            'amount' => [
                'label'         => 'Amount',
                'placeholder'   => 'Input Amount',
            ],
            'description' => [
                'label'         => 'Description',
                'placeholder'   => 'Input Description',
            ],
            'buttons' => [
                'save-item' => [
                    'name'      => 'Save Cash Balance',
                    'sr-icon'   => 'Save Cash Balance Icon',
                ],
                'update-item' => [
                    'name'      => 'Save Cash Balance Changes',
                    'sr-icon'   => 'Save Cash Balance Changes Icon',
                ],
            ],
        ],
    ],
];

