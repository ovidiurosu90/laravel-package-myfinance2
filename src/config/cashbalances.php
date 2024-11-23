<?php

return [
    'database_connection' => env('CASH_BALANCES_DATABASE_CONNECTION', null),
    'database_table'      => env('CASH_BALANCES_DATABASE_TABLE', 'cash_balances'),

    // Used for both create and update
    'guiCreateMiddlewareType' => env('CASH_BALANCES_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('CASH_BALANCES_GUI_CREATE_MIDDLEWARE', 'cashbalances.create'), // admin, name. ... or perms.name
];

