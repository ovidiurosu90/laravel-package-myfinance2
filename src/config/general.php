<?php

$allAccounts   = [
    'ING'           => 'ING',
    'ABN AMRO'      => 'ABN AMRO',

    'TD Ameritrade' => 'TD Ameritrade',
    'DEGIRO'        => 'DEGIRO',
    'E-Trade'       => 'E-Trade',

    'Binance'       => 'Binance',
    'Bitvavo'       => 'Bitvavo',
];
$allCurrencies = [
    'USD'           => 'US Dollar',
    'EUR'           => 'Euro',

    'GBX'           => 'Pence sterling',
];

return [
    //NOTE Only used in Database Migrations
    'ledger_accounts'       => array_intersect_key($allAccounts, array_fill_keys(['ING', 'ABN AMRO', 'TD Ameritrade', 'DEGIRO', 'Binance', 'Bitvavo'], 1)),
    'trade_accounts'        => array_intersect_key($allAccounts, array_fill_keys(['TD Ameritrade', 'DEGIRO', 'E-Trade', 'Binance', 'Bitvavo'], 1)),
    'dividend_accounts'     => array_intersect_key($allAccounts, array_fill_keys(['TD Ameritrade', 'DEGIRO', 'E-Trade'], 1)),
    'ledger_currencies'     => array_intersect_key($allCurrencies, array_fill_keys(['USD', 'EUR'], 1)),
    'trade_currencies'      => array_intersect_key($allCurrencies, array_fill_keys(['USD', 'EUR', 'GBX'], 1)),
    'dividend_currencies'   => array_intersect_key($allCurrencies, array_fill_keys(['USD', 'EUR', 'GBX'], 1)),

    'currencies_mapping' => [
        'GBp'     => 'GBX',
    ],
    'currencies_reverse_mapping' => [
        'GBX'     => 'GBp',
    ],

    'row-format-amount' => [
        'unknown' => 'text-primary',
        'buy'     => 'text-danger transaction-debit',
        'debit'   => 'text-danger transaction-debit',
        'fee'     => 'text-danger transaction-debit',
        'sell'    => 'text-success transaction-credit',
        'credit'  => 'text-success transaction-credit',
    ],

    // Skip these symbols. They don't exist anymore
    'obsolete_symbols' => [
        'ATVI', // Activision got acquired by Microsoft on October 13, 2023
    ],
];

