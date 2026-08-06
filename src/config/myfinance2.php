<?php

return [
    'defaultMigrations' => [
        'enabled' => env('MYFINANCE2_MIGRATION_DEFAULT_ENABLED', true),
    ],

    'db_connection' => env('MYFINANCE2_DB_CONNECTION', 'myfinance2_mysql'),

    'connections' => [
        'myfinance2_mysql' => [
            'driver'         => 'mysql',
            'url'            => env('MYFINANCE2_DATABASE_URL'),
            'host'           => env('MYFINANCE2_DB_HOST', '127.0.0.1'),
            'port'           => env('MYFINANCE2_DB_PORT', '3306'),
            'database'       => env('MYFINANCE2_DB_DATABASE', 'myfinance2'),
            'username'       => env('MYFINANCE2_DB_USERNAME', 'myfinance2_user'),
            'password'       => env('MYFINANCE2_DB_PASSWORD', ''),
            'unix_socket'    => env('MYFINANCE2_DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],

    'myfinance2_invite_token' => env('MYFINANCE2_INVITE_TOKEN'),

    // Safety net for the /positions page. PositionsReconciliationService cross-checks the
    // figures shown against each other and raises an alert when they diverge, so a regression
    // introduced during development lights up early. Tolerances are percentages; start tight
    // so drift is visible, then relax once the expected variation is understood.
    'reconciliation' => [
        // Per account (same currency): live sum of the open-position rows vs the
        // account-overview-summary the card header shows. cost is an exact invariant here;
        // mvalue/gain carry live-vs-snapshot price drift (larger on small, volatile positions),
        // so this is looser and paired with the absolute floor below.
        'account_tolerance_pct' => env('MYFINANCE2_RECON_ACCOUNT_TOLERANCE_PCT', 1.5),
        // Whole portfolio: live positions converted to EUR vs the User Overview total. cost and
        // cash match near-exactly (same rate, no drift); mvalue/change only drift with prices
        // since the last snapshot, smoothed by the large portfolio base, so this can stay tight.
        'user_fx_tolerance_pct' => env('MYFINANCE2_RECON_USER_FX_TOLERANCE_PCT', 0.5),
        // Suppress small absolute drift (e.g. a few euro on a tiny position) so it does not trip
        // the percentage test on a small base. Real regressions produce far larger gaps.
        'absolute_floor' => env('MYFINANCE2_RECON_ABSOLUTE_FLOOR', 5.0),
    ],

    // Stale live-quote detection for the pages that render live positions (/positions,
    // /watchlist-symbols, /overview). When a market should be open but the freshest price
    // timestamp for that market is older than the threshold, a banner warns that prices may be
    // stale so decisions are not made on frozen data. See StaleQuoteService.
    'stale_quote' => [
        'enabled' => env('MYFINANCE2_STALE_QUOTE_ENABLED', true),
        // Age (in seconds) past which an open market's freshest price is treated as stale.
        // Set to 5 minutes; raise toward 10 (600) if it proves too sensitive.
        'threshold_seconds' => env('MYFINANCE2_STALE_QUOTE_THRESHOLD', 300),
    ],
];

