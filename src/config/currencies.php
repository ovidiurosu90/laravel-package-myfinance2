<?php

return [
    // Used for both create and update
    'guiCreateMiddlewareType' => env('CURRENCIES_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('CURRENCIES_GUI_CREATE_MIDDLEWARE', 'currencies.create'), // admin, name. ... or perms.name
];

