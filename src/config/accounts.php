<?php

return [
    // Used for both create and update
    'guiCreateMiddlewareType' => env('ACCOUNTS_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('ACCOUNTS_GUI_CREATE_MIDDLEWARE', 'accounts.create'), // admin, name. ... or perms.name
];

