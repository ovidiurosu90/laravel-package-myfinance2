<?php

return [
    'connection'              => env('LEDGER_DATABASE_CONNECTION', null),
    'ledgerTransactionsTable' => env('LEDGER_TRANSACTIONS_DATABASE_TABLE', 'ledger_transactions'),

    'transaction_types' => [
        'DEBIT'  => 'Debit',
        'CREDIT' => 'Credit',
    ],

    'guiCreateNewTransactionMiddlewareType' => env('LEDGER_GUI_CREATE_TRANSACTION_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateNewTransactionMiddleware'     => env('LEDGER_GUI_CREATE_TRANSACTION_MIDDLEWARE', 'ledger.create.transaction'), // admin, name. ... or perms.name
];

