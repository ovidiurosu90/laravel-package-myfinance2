<?php

return [
    'database_connection' => env('WATHCHLIST_SYMBOLS_DATABASE_CONNECTION', null),
    'database_table'      => env('WATHCHLIST_SYMBOLS_DATABASE_TABLE', 'watchlist_symbols'),

    // Used for both create and update
    'guiCreateMiddlewareType' => env('WATHCHLIST_SYMBOLS_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('WATHCHLIST_SYMBOLS_GUI_CREATE_MIDDLEWARE', 'watchlist.symbols.create'), // admin, name. ... or perms.name
];

