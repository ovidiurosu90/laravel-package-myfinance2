<?php

return [
    'titles' => [
        'dashboard'   => 'Trades',
    ],
    'items-table' => [
        'caption' => '{1} :count trade|[2,*] :count trades',
        'none'    => 'No Trades',
    ],
    'flash-messages' => [
        'trade-closed'  => 'Successfully Closed Trade with id :id',
        'trades-closed' => '{1} Sucessfully Closed :count trade|[2,*] Successfully Closed :count trades',
    ],
    'tooltips' => [
        'get-finance-data' => 'Get Finance Data',
        'close-trade'      => 'Put trade in CLOSED status',
        'close-symbol'     => 'Put all trades from account :account with symbol :symbol in CLOSED status',
    ],
    'modals' => [
        'close_modal_title'          => 'Close Trade with id :id',
        'close_modal_message'        => 'Are you sure you want to close Trade with id :id?',
        'close-symbol_modal_title'   => 'Close Trades from account :account',
        'close-symbol_modal_message' => 'Are you sure you want to close all Trades from account :account with symbol :symbol?',
    ],
    'buttons' => [
        'close-trade'  => '<span class="hidden-xs hidden-sm">Close </span><i class="fa fa-window-close fa-fw" aria-hidden="true"></i>',
    ],
    'forms' => [
        'item-form' => [
            'timestamp' => [
                'label'         => 'Timestamp',
            ],
            'action' => [
                'label'         => 'Action',
                'placeholder'   => 'Select Action',
            ],
            'account' => [
                'label'         => 'Account',
                'placeholder'   => 'Select Account',
            ],
            'account_currency' => [
                'label'         => 'Account Currency',
                'placeholder'   => 'Select Account Currency',
            ],
            'trade_currency' => [
                'label'         => 'Trade Currency',
                'placeholder'   => 'Select Trade Currency',
            ],
            'exchange_rate' => [
                'label'         => 'Exchange Rate',
                'placeholder'   => 'Input Exchange Rate',
            ],
            'symbol' => [
                'label'         => 'Symbol',
                'placeholder'   => 'Input Symbol',
            ],
            'quantity' => [
                'label'         => 'Quantity',
                'placeholder'   => 'Input Quantity',
            ],
            'unit_price' => [
                'label'         => 'Unit Price',
                'placeholder'   => 'Input Unit Price',
            ],
            'fee' => [
                'label'         => 'Fee',
                'placeholder'   => 'Input Fee',
            ],
            'description' => [
                'label'         => 'Description',
                'placeholder'   => 'Input Description',
            ],
            'buttons' => [
                'save-item' => [
                    'name'      => 'Save Trade',
                    'sr-icon'   => 'Save Trade Icon',
                ],
                'update-item' => [
                    'name'      => 'Save Trade Changes',
                    'sr-icon'   => 'Save Trade Changes Icon',
                ],
            ],
        ],
    ],
];

