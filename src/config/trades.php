<?php

return [
    'database_connection' => env('TRADES_DATABASE_CONNECTION', null),
    'database_table'      => env('TRADES_DATABASE_TABLE', 'trades'),

    'actions' => [
        'BUY'  => 'Buy',
        'SELL' => 'Sell',
    ],
    'statuses' => [
        'OPEN'   => 'Open',
        'CLOSED' => 'Closed',
    ],

    // Used for both create and update
    'guiCreateMiddlewareType' => env('TRADES_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('TRADES_GUI_CREATE_MIDDLEWARE', 'trades.create'), // admin, name. ... or perms.name
];

