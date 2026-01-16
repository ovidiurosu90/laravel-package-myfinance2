<?php

return [
    'actions' => [
        'BUY'  => 'Buy',
        'SELL' => 'Sell',
    ],
    'statuses' => [
        'OPEN'   => 'Open',
        'CLOSED' => 'Closed',
    ],

    'unlisted' => 'UNLISTED',

    // Symbols that are known to be delisted and should not be fetched from API
    // For these symbols, price overrides will be used instead
    'delisted_symbols' => [
        'ATVI', // Activision Blizzard - delisted (acquired by Microsoft)
    ],

    'unlisted_fmv' => [
        // Unlisted fair market value data loaded from private package
    ],

    // Historical price overrides for specific symbols on specific dates
    // Hierarchical structure: global overrides apply to all accounts,
    // by_account overrides apply only to specific accounts and take precedence
    // Format: 'SYMBOL' => ['YYYY-MM-DD' => price, ...]
    // Note: Use the actual date of the data (e.g., if Jan 1 is not a trading day, use Dec 31)
    'price_overrides' => [
        'global' => [
            // Global price overrides loaded from private package
        ],
        'by_account' => [
            // Account-specific overrides loaded from private package
        ],
    ],

    // Historical exchange rate overrides for specific currency pairs on specific dates
    // Hierarchical structure: global overrides apply to all accounts,
    // by_account overrides apply only to specific accounts and take precedence
    // Format: 'PAIR' => ['YYYY-MM-DD' => rate, ...]
    // Example: EURGBP=X on 2021-12-31 was 0.8408
    'exchange_rate_overrides' => [
        'global' => [
            // Global exchange rate overrides loaded from private package
        ],
        'by_account' => [
            // Account-specific overrides loaded from private package
        ],
    ],

    // Position date overrides for tax reporting adjustments
    // When securities transfer between accounts mid-year, use this to show correct positions on specific dates
    // The system will fetch the price quote for that date, so you only need to specify quantity
    'position_date_overrides' => [
        // Account-specific overrides loaded from private package
    ],

    // Dividend currency tax entity mappings
    // For tax reporting purposes, some symbols may need to be grouped in a different currency bucket
    // than their actual dividend currency. This is useful for tax entity classification exceptions.
    // Hierarchical structure: global mappings apply to all accounts,
    // by_account mappings apply only to specific accounts and take precedence
    // Format: 'SYMBOL' => 'TAX_CURRENCY_ISO_CODE'
    // Example: NXPI dividends are in USD, but for tax purposes should be grouped in EUR bucket
    'dividend_currency_tax_mappings' => [
        'global' => [
            // Global mappings here (if any)
        ],
        'by_account' => [
            // Account-specific mappings loaded from private package
        ],
    ],

    // Total gross dividends overrides for tax reporting
    // Used to match official annual statements when calculated values differ due to rounding or data issues
    // Hierarchical structure: global overrides apply to all accounts,
    // by_account overrides apply only to specific accounts and take precedence
    // Format: 'YEAR' => ['CURRENCY' => amount, ...] or 'YEAR' => amount (backwards compatible)
    // Example: For account X in 2022, override total gross dividends to match annual statement
    'total_gross_dividends_overrides' => [
        'global' => [
            // Global overrides here (if any)
        ],
        'by_account' => [
            // Account-specific overrides loaded from private package
        ],
    ],

    // Purchase and sale fees exclusions for tax reporting
    // Used to exclude hidden/indirect fees (e.g., currency conversion fees) from returns calculations
    // These fees are not part of transaction records but are known to exist
    // Hierarchical structure: global overrides apply to all accounts,
    // by_account overrides apply only to specific accounts and take precedence
    // Format: 'YEAR' => ['CURRENCY' => ['purchases' => amount, 'sales' => amount], ...]
    // Example: For account X in 2022, exclude fees from purchases and sales calculations
    'fees_exclusions' => [
        'global' => [
            // Global overrides here (if any)
        ],
        'by_account' => [
            // Account-specific exclusions loaded from private package
        ],
    ],

    // Exclude specific trades from Returns page purchases & sales sections
    // Use this to hide trades that were received as part of other corporate actions (e.g., WBD from
    // merger/spinoff)
    // Format: [trade_id1, trade_id2, ...] - list of trade IDs to exclude
    // Example: [123, 456]
    'exclude_trades_from_returns' => [
        // Trade IDs loaded from private package
    ],

    // Account returns overrides for specific years
    // Used to adjust returns for accounts with special situations (e.g., in-kind transfers with no economic return)
    // Hierarchical structure: by_account overrides apply only to specific accounts and take precedence
    // Format: 'by_account' => [account_id => [year => ['EUR' => amount, 'USD' => amount, 'reason' => 'message'], ...], ...]
    // Example: For account X in 2022, override return to 0 (in-kind transfer between accounts)
    'returns_overrides' => [
        'by_account' => [
            // Account-specific return overrides loaded from private package
        ],
    ],

    // Used for both create and update
    'guiCreateMiddlewareType' => env(
        'TRADES_GUI_CREATE_MIDDLEWARE_TYPE',
        'permissions'
    ), // permissions or role
    'guiCreateMiddleware' => env(
        'TRADES_GUI_CREATE_MIDDLEWARE',
        'trades.create'
    ), // admin, name. ... or perms.name
];
