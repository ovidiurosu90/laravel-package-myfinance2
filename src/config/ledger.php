<?php

return [
    'transaction_types' => [
        'DEBIT'  => 'Debit',
        'CREDIT' => 'Credit',
    ],

    'guiCreateNewTransactionMiddlewareType' => env('LEDGER_GUI_CREATE_TRANSACTION_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateNewTransactionMiddleware'     => env('LEDGER_GUI_CREATE_TRANSACTION_MIDDLEWARE', 'ledger.create.transaction'), // admin, name. ... or perms.name
];

