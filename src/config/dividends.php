<?php

return [
    'database_connection' => env('DIVIDENDS_DATABASE_CONNECTION', null),
    'database_table'      => env('DIVIDENDS_DATABASE_TABLE', 'dividends'),

    // Used for both create and update
    'guiCreateMiddlewareType' => env('DIVIDENDS_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('DIVIDENDS_GUI_CREATE_MIDDLEWARE', 'dividends.create'), // admin, name. ... or perms.name
];

