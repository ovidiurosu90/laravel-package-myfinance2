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

    // Safety net for /positions, /watchlist-symbols and /returns (PositionsReconciliationService,
    // WatchlistReconciliationService, ReturnsReconciliationService). Each cross-checks the figures
    // shown against the parts they are built from and raises an alert when they diverge, so a
    // regression lights up early. Every check compares figures built from the same prices (e.g.
    // the rows vs the headers of the same page load, or the rows repriced at the cron snapshot's
    // prices vs the stored series), so price movement never shows up as a gap and the tolerance
    // only has to absorb rounding.
    'reconciliation' => [
        // Absolute gap allowed per figure, in the figure's currency (EUR for portfolio totals).
        // Stored series keep 4 decimals, so a few cents leaves ample room for rounding while any
        // real computation error stands out.
        'tolerance' => env('MYFINANCE2_RECON_TOLERANCE', 0.05),
        // The cron refreshes the stored series every minute. Past this age they have stopped
        // updating (cron stopped, crashed or stuck) and /positions shows a staleness warning.
        'stale_after_seconds' => env('MYFINANCE2_RECON_STALE_AFTER_SECONDS', 180),
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
        // Yahoo Finance streams US quotes in real time but serves the European exchange feeds
        // (Euronext, XETRA, LSE) with a built-in ~15 minute delay, so a perfectly healthy European
        // price is always around 15 minutes old and would trip the threshold above on every page
        // load. Markets on a European exchange timezone therefore get this larger threshold: below
        // it the age is Yahoo's own delay, above it the feed really has stopped advancing.
        'delayed_feed_threshold_seconds' => env('MYFINANCE2_STALE_QUOTE_DELAYED_THRESHOLD', 1800),
    ],
];

