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

    // The app's layout every MyFinance2 page renders in (e.g. 'layouts.app'), and the names of its sections that take
    // the page's title, styles (<head>), content and scripts (end of <body>). Empty: the package's own minimal page
    // (myfinance2::layouts.standalone), whose sections are these defaults.
    'layout'          => null,
    'layout_sections' => [
        'title'   => 'title',
        'styles'  => 'styles',
        'content' => 'content',
        'scripts' => 'scripts',
    ],

    // Every page loads the package's prebuilt JS / CSS (public/vendor/myfinance2/myfinance2.js / .css: charts, date
    // pickers, toggles), which use the page's jQuery. With an app layout, turn this on when that layout does not load
    // jQuery, Bootstrap, DataTables, selectize and Font Awesome itself: the package then loads its own
    // (myfinance2-base.js / .css). The standalone layout always does.
    'base_assets' => false,

    // Show the outcome of the last action (saved, deleted, failed) and validation errors above each page. Turn it off
    // when the app's layout already shows the session's 'success' / 'error' messages and $errors.
    'flash_messages' => true,

    // The host's config key holding its list of navbar menu partials (e.g. 'navigation.menus'); when set, the package
    // adds 'myfinance2::partials.nav' to that list. Empty: the host includes the partial itself, or not at all.
    'navbar_registry' => null,

    // URL prefix of every MyFinance2 page and AJAX endpoint, e.g. 'finance' for /finance/positions; empty: at the root
    // (/positions). Links are built from the route names (myfinance2::...), which never change.
    'route_prefix' => env('MYFINANCE2_ROUTE_PREFIX', ''),

    // Extra middleware for every MyFinance2 page and AJAX endpoint, e.g. the app's activity log or blocker; set it in
    // the app's config/myfinance2.php. They run after 'web' and before the access gate (can:myfinance2.access), which
    // are always applied and cannot be configured away (README, "Authorization").
    'middleware' => [],

    // The app's user model (MyFinance2 records belong to its users); empty: the model of the app's 'users' auth
    // provider (App\Services\AppUser).
    'user_model' => env('MYFINANCE2_USER_MODEL'),

    // The migrations' user_id columns reference the users table with a cross-database foreign key, which needs both
    // databases on the same MySQL server. Set to false before the first migrate when the users live elsewhere: a plain
    // index then (AppUser::addUserIdKey()).
    'users_foreign_key' => env('MYFINANCE2_USERS_FOREIGN_KEY', true),

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

