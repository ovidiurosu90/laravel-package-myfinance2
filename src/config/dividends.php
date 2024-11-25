<?php

return [
    // Used for both create and update
    'guiCreateMiddlewareType' => env('DIVIDENDS_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('DIVIDENDS_GUI_CREATE_MIDDLEWARE', 'dividends.create'), // admin, name. ... or perms.name
];

