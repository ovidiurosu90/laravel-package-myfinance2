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
    'ledger_accounts'       => array_intersect_key($allAccounts, array_fill_keys(['ING', 'ABN AMRO', 'TD Ameritrade', 'DEGIRO', 'Binance', 'Bitvavo'], 1)),
    'trade_accounts'        => array_intersect_key($allAccounts, array_fill_keys(['TD Ameritrade', 'DEGIRO', 'E-Trade', 'Binance', 'Bitvavo'], 1)),
    'dividend_accounts'     => array_intersect_key($allAccounts, array_fill_keys(['TD Ameritrade', 'DEGIRO', 'E-Trade'], 1)),
    'ledger_currencies'     => array_intersect_key($allCurrencies, array_fill_keys(['USD', 'EUR'], 1)),
    'trade_currencies'      => array_intersect_key($allCurrencies, array_fill_keys(['USD', 'EUR', 'GBX'], 1)),
    'dividend_currencies'   => array_intersect_key($allCurrencies, array_fill_keys(['USD', 'EUR', 'GBX'], 1)),

    'account_currency_defaults' => [
        'ING'           => 'EUR',
        'ABN AMRO'      => 'EUR',

        'TD Ameritrade' => 'USD',
        'DEGIRO'        => 'EUR',
        'E-Trade'       => 'USD',

        'Binance'       => 'USD',
        'Bitvavo'       => 'EUR',
    ],

    'currencies_display' => [
        'unknown' => '&curren;',
        'EUR'     => '&euro;',
        'USD'     => '$',
        'GBX'     => 'GBX', // No symbol
        'GBp'     => 'GBX', // No symbol
    ],

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
];

