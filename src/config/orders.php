<?php

return [
    // Used for both create and update
    'guiCreateMiddlewareType' => env(
        'ORDERS_GUI_CREATE_MIDDLEWARE_TYPE',
        'permissions'
    ), // permissions or role
    'guiCreateMiddleware' => env(
        'ORDERS_GUI_CREATE_MIDDLEWARE',
        'orders.create'
    ), // admin, name. ... or perms.name
];
