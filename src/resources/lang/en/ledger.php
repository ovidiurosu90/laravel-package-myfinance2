<?php

return [
    'titles' => [
        'dashboard' => 'Ledger',
    ],
    'buttons' => [
        'create-child-transaction' => '<span class="hidden-xs hidden-sm">Create </span><i class="fa fa-plus fa-fw" aria-hidden="true"></i>',
    ],
    'transactions-table' => [
        'caption' => '{1} :count transaction|[2,*] :count transactions',
        'none'    => 'No Transactions',
    ],
    'tooltips' => [
        'create-child-transaction' => 'Create Child Transaction',
        'enable-transaction-debit_account-select'   => 'Enable Debit Account',
        'enable-transaction-debit_currency-select'  => 'Enable Debit Currency',
        'enable-transaction-credit_account-select'  => 'Enable Credit Account',
        'enable-transaction-credit_currency-select' => 'Enable Credit Currency',
        'enable-transaction-exchange_rate-input'    => 'Enable Exchange Rate',
        'disable-transaction-debit_account-select'   => 'Disable Debit Account',
        'disable-transaction-debit_currency-select'  => 'Disable Debit Currency',
        'disable-transaction-credit_account-select'  => 'Disable Credit Account',
        'disable-transaction-credit_currency-select' => 'Disable Credit Currency',
        'disable-transaction-exchange_rate-input'    => 'Disable Exchange Rate',
    ],
    'forms' => [
        'transaction-form' => [
            'timestamp' => [
                'label'         => 'Timestamp',
            ],
            'type' => [
                'label'         => 'Type',
                'placeholder'   => 'Select Type',
            ],
            'debit_account' => [
                'label'         => 'Debit Account',
                'placeholder'   => 'Select Debit Account',
            ],
            'credit_account' => [
                'label'         => 'Credit Account',
                'placeholder'   => 'Select Credit Account',
            ],
            'debit_currency' => [
                'label'         => 'Debit Currency',
                'placeholder'   => 'Select Debit Currency',
            ],
            'credit_currency' => [
                'label'         => 'Credit Currency',
                'placeholder'   => 'Select Credit Currency',
            ],
            'exchange_rate' => [
                'label'         => 'Exchange Rate',
                'placeholder'   => 'Input Exchange Rate',
            ],
            'amount' => [
                'label'         => 'Amount',
                'placeholder'   => 'Input Amount',
            ],
            'fee' => [
                'label'         => 'Fee',
                'placeholder'   => 'Input Fee',
            ],
            'description' => [
                'label'         => 'Description',
                'placeholder'   => 'Input Description',
            ],
            'parent' => [
                'label'         => 'Parent',
                'placeholder'   => 'Select Parent if any',
            ],
            'buttons' => [
                'save-transaction' => [
                    'name'      => 'Save Ledger Transaction',
                    'sr-icon'   => 'Save Ledger Transaction Icon',
                ],
                'update-transaction' => [
                    'name'      => 'Save Ledger Transaction Changes',
                    'sr-icon'   => 'Save Ledger Transaction Changes Icon',
                ],
            ],
        ],
    ],
];

