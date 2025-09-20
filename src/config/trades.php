<?php

return [
    'actions' => [
        'BUY'  => 'Buy',
        'SELL' => 'Sell',
    ],
    'statuses' => [
        'OPEN'   => 'Open',
        'CLOSED' => 'Closed',
    ],

    'unlisted' => 'UNLISTED',
    'unlisted_fmv' => [
        'UNLISTED_MIRO' => [
            'symbol_name' => 'Miro',
            'quotes' => [
                [
                    'price'     => 7.36,
                    'timestamp' => '2024-11-01 00:00:01',
                ], [
                    'price'     => 7.28,
                    'timestamp' => '2025-02-09 00:00:01',
                ], [
                    'price'     => 6.94,
                    'timestamp' => '2025-09-01 00:00:01',
                ],
            ],
        ],
    ],

    // Used for both create and update
    'guiCreateMiddlewareType' => env('TRADES_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('TRADES_GUI_CREATE_MIDDLEWARE', 'trades.create'), // admin, name. ... or perms.name
];

