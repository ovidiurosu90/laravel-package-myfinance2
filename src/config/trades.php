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

    // Symbols known to have Yahoo API price issues (e.g., stock splits not properly adjusted)
    // For these symbols, if you hold a position at year-end, you should have price overrides defined
    // This helps catch missing overrides across multiple years
    'api_price_issue_symbols' => [
        // Symbols loaded from private package
    ],

    // Keywords in trade descriptions that indicate the broker has already adjusted for stock splits
    // If any trade for a symbol contains these keywords, no price override alert will be shown
    // (case-insensitive matching)
    'api_price_issue_suppression_keywords' => [
        'split',
    ],

    // Alert rules for returns page
    // These rules check trades for specific keywords and verify that required overrides are defined
    // If a trade matches a rule but the required overrides are missing, an alert is shown
    //
    // Structure for keyword-based rules:
    //   'rule_name' => [
    //     'keywords' => ['keyword1', 'keyword2'],  // Trade description keywords (case-insensitive)
    //     'required_overrides' => ['override_type1'],  // Required config keys
    //     'message' => 'Alert message to display',
    //   ]
    //
    // Special rule types:
    //   'delisted' - checks against delisted_symbols list (no keywords needed)
    //   'api_price_issues' - checks against api_price_issue_symbols list
    //     - supports 'suppression_keywords': alert is suppressed if trade description contains these
    //     - useful for symbols with API price issues where broker may have already fixed the data
    //
    // Supported override types: price_overrides, position_date_overrides,
    //                           withdrawals_overrides, exclude_trades_from_returns
    'alert_rules' => [
        // Alert rules loaded from private package
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

    // Exchange rate pairs that require overrides for year start/end valuations
    // Hierarchical structure: global pairs are required for all accounts,
    // by_account pairs are required only for specific accounts
    // Alert is shown if any account is missing a required override for start or end value
    // This ensures accurate EUR conversions for portfolio valuations and tax reporting
    'required_exchange_rate_overrides' => [
        'global' => [
            'EURUSD=X',  // USD to EUR conversion (required for all accounts)
        ],
        'by_account' => [
            // Account-specific required pairs loaded from private package
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

    // Keywords in trade descriptions that indicate positions may need date overrides
    // If a position has trades with these keywords, an alert will be shown to remind you
    // to add position_date_overrides for correct account attribution on specific dates
    // Examples: 'vested' (stock grants), 'moved' or 'transferred' (account transfers)
    // (case-insensitive matching)
    'position_date_override_keywords' => [
        // Keywords loaded from private package
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
    // Use this to hide trades that are accounting adjustments, not real economic transactions
    // Common cases:
    // - Stock split adjustments: BUY+SELL pairs on the same day to rebalance shares after a split
    //   (detected automatically by Override Reminders when quantities differ by a split ratio)
    // - Corporate actions: Spin-offs, mergers, divestments where shares were received without payment
    // Format: [trade_id1, trade_id2, ...] - list of trade IDs to exclude
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

    // Account withdrawals overrides for specific years
    // Used to adjust total withdrawals for accounts with special situations (e.g., in-kind transfers)
    // Hierarchical structure: by_account overrides apply only to specific accounts and take precedence
    // Format: 'by_account' => [account_id => [year => ['EUR' => amount, 'USD' => amount, 'reason' => 'message'], ...], ...]
    // Example: For account X in 2022, override withdrawals to neutralize in-kind transfer value
    'withdrawals_overrides' => [
        'by_account' => [
            // Account-specific withdrawal overrides loaded from private package
        ],
    ],

    // Account deposits overrides for specific years
    // Used to adjust total deposits for accounts with special situations (e.g., vested shares)
    // Hierarchical structure: by_account overrides apply only to specific accounts and take precedence
    // Format: 'by_account' => [account_id => [year => ['EUR' => amount, 'USD' => amount, 'reason' => 'message'], ...], ...]
    // Example: For account X in 2019, override deposits to include vested shares value
    'deposits_overrides' => [
        'by_account' => [
            // Account-specific deposit overrides loaded from private package
        ],
    ],

    // Gains annotations for finance-home "Gains per year" card
    // Marks specific year/account/symbol gains as originating from transferred positions
    // These gains are still included in totals but annotated in the UI for transparency
    // Format: year => [account_id => [symbol => reason]]
    'gains_annotations' => [],

    // Virtual accounts for return adjustments (e.g., cross-account share transfers)
    // NOT real brokerage accounts — they exist only to adjust total returns in the overview
    // Individual real account returns remain unchanged (for tax reporting)
    // Format: 'id' => ['name' => '...', 'returns' => [year => ['EUR' => x, 'USD' => y, 'reason' => '...']]]
    'virtual_accounts' => [],

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
