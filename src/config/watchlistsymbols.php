<?php

return [
    // Used for both create and update
    'guiCreateMiddlewareType' => env('WATHCHLIST_SYMBOLS_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('WATHCHLIST_SYMBOLS_GUI_CREATE_MIDDLEWARE', 'watchlist.symbols.create'), // admin, name. ... or perms.name
];

