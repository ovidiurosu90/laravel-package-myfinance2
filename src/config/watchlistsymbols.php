<?php

return [
    // Used for both create and update
    'guiCreateMiddlewareType' => env('WATHCHLIST_SYMBOLS_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'), // permissions or role
    'guiCreateMiddleware'     => env('WATHCHLIST_SYMBOLS_GUI_CREATE_MIDDLEWARE', 'watchlist.symbols.create'), // admin, name. ... or perms.name

    // What decides a symbol's tier. 'market': every symbol, owned or not, is tiered on its trailing 1Y
    // market return (owned positions fall back to their own return only when that figure is missing or
    // untrustworthy). 'own': the legacy rule, owned positions are tiered on your own overall return.
    // After changing it, clear the cache and re-run finance:tier-history-backfill.
    'tier_basis' => env('MYFINANCE2_TIER_BASIS', 'market'),

    // Daily tier history (symbol_tier_history), the time-in-tier shares and the settled tier derived
    // from it. The backfill command and the hourly cron write the history; the dashboard reads it.
    'tier_history' => [
        // First weekday the backfill reconstructs (same start as the other stored histories).
        'start_date'               => env('MYFINANCE2_TIER_HISTORY_START_DATE', '2025-01-01'),
        // Calendar days of history behind the time-in-tier shares and the settled tier.
        'lookback_days'            => (int) env('MYFINANCE2_TIER_HISTORY_LOOKBACK_DAYS', 365),
        // The settled tier is the tier the symbol held or beat on at least this % of the lookback days
        // (50 = the median daily reading).
        'consistency_pct'          => (float) env('MYFINANCE2_TIER_HISTORY_CONSISTENCY_PCT', 50),
        // Fewer stored weekdays than this: no settled tier and no signals (history too short).
        'min_history_days'         => (int) env('MYFINANCE2_TIER_HISTORY_MIN_HISTORY_DAYS', 60),
        // Today's tier is "rare" when the symbol was at (or beyond) it on fewer than this % of days. The first
        // backtest (10 / 20 / 30, 2026-10-13) found no setting that turned rare highs / lows into an edge
        // (TierSignalChip).
        'rare_share_pct'           => (float) env('MYFINANCE2_TIER_HISTORY_RARE_SHARE_PCT', 20),
        // Weekdays a two-tier drop must persist before the settled tier follows today's tier.
        'deteriorate_persist_days' => (int) env('MYFINANCE2_TIER_HISTORY_DETERIORATE_PERSIST_DAYS', 20),
        // "Split year" note on the Tier line: the settled tier held on fewer than this % of the days (a median
        // between long spells above and below it).
        'split_share_pct'          => (float) env('MYFINANCE2_TIER_HISTORY_SPLIT_SHARE_PCT', 15),
        // A rare low is a buy / add signal only on symbols settled in one of these tiers.
        'buy_settled_tiers'        => ['GOLD', 'PLATINUM'],
        // Severity context for sell / buy signals (phase 3).
        'size_high_pct'            => (float) env('MYFINANCE2_TIER_HISTORY_SIZE_HIGH_PCT', 5),
        'max_position_pct'         => (float) env('MYFINANCE2_TIER_HISTORY_MAX_POSITION_PCT', 10),
        // Weekdays the hourly cron rewrites (idempotent), so price corrections from the daily
        // historical refresh are picked up.
        'recent_rewrite_days'      => (int) env('MYFINANCE2_TIER_HISTORY_RECENT_REWRITE_DAYS', 7),
    ],

    // Range-bound pattern (RangeBoundPattern): a stock you trade well that swings widely but ends the year about
    // where it started. Read on its swing (TRIM near the high, ADD near the low) instead of its weak tier; opted in
    // to peak-proximity alerts it gets TRIM / ADD alerts when held (instead of near-peak exit alerts), BUY near the
    // low once exited.
    'range_bound' => [
        // The 1Y market return is under this either way.
        'max_abs_1y_pct'   => (float) env('MYFINANCE2_RANGE_BOUND_MAX_ABS_1Y_PCT', 10),
        // The 52-week closing low is under this % of the closing high.
        'max_low_high_pct' => (float) env('MYFINANCE2_RANGE_BOUND_MAX_LOW_HIGH_PCT', 80),
        // Your own results: Own Lifetime at this tier or better, or at least min_completed completed trades with
        // min_win_rate_pct or more of them won.
        'min_own_tier'     => env('MYFINANCE2_RANGE_BOUND_MIN_OWN_TIER', 'GOLD'),
        'min_completed'    => (int) env('MYFINANCE2_RANGE_BOUND_MIN_COMPLETED', 2),
        'min_win_rate_pct' => (float) env('MYFINANCE2_RANGE_BOUND_MIN_WIN_RATE_PCT', 75),
        // Where today's price sits in the 52-week range (0 % the low, 100 % the high): near the high at or above
        // this, near the low at or below near_low_pct, mid-range in between.
        'near_high_pct'    => (float) env('MYFINANCE2_RANGE_BOUND_NEAR_HIGH_PCT', 70),
        'near_low_pct'     => (float) env('MYFINANCE2_RANGE_BOUND_NEAR_LOW_PCT', 30),
    ],

    // Weak tier in profit (WeakTierProfit): an open position in profit on a stock whose tier in use is weak reads
    // TAKE PROFIT (a chip on the watchlist row; opted in to peak-proximity alerts, a TAKE PROFIT alert in place of
    // the near-peak one). Range-bound symbols are left out: they read TRIM near the high.
    'weak_tier_profit' => [
        // Tiers (TierCalculationService constants) that count as weak.
        'tiers'        => ['RUST', 'BRONZE'],
        // The open position's unrealized gain must be above this (%); 0 means any profit.
        'min_gain_pct' => (float) env('MYFINANCE2_WEAK_TIER_PROFIT_MIN_GAIN_PCT', 0),
    ],

    // Warnings on the order create / edit form (OrderTierWarnings) when an order goes against the symbol's tier
    // reading.
    // serious: a confirmation on save; soft: a banner only; off: not raised. Set from the calibration command
    // (finance:order-warning-review), not by intuition: a code is serious only if its trades did clearly worse than
    // the baseline, it has enough trades and it fires on a minority of them. A code left out uses its default
    // (OrderTierWarnings::DEFAULT_LEVELS).
    'order_warnings' => [
        // From the first calibration (2026-10-24, 222 trades since 2025-01-01, judged at 126 trading days): a
        // deteriorating BUY did clearly worse than the baseline, so serious. The review suggested soft for a weak-tier
        // BUY (it did better than the baseline) and TAKE PROFIT (too few trades); both kept serious for now, by choice.
        // Rerun the review as history grows.
        'levels' => [
            'BUY_WEAK_TIER'           => 'serious',
            'BUY_SILVER'              => 'soft',
            'BUY_TAKE_PROFIT'         => 'serious',
            'BUY_DETERIORATING'       => 'serious',
            'BUY_OVER_MAX_POSITION'   => 'serious',
            'BUY_RARE_HIGH'           => 'soft',
            'BUY_NEAR_PEAK'           => 'soft',
            'BUY_HEALTH_WEIGHED_DOWN' => 'soft',
            'SELL_SUPERSTAR'          => 'serious',
            'SELL_RARE_LOW'           => 'soft',
            'SELL_RANGE_LOW'          => 'soft',
            'NOT_ON_WATCHLIST'        => 'soft',
            'STALE_QUOTE'             => 'soft',
        ],
        // When on, a weak-tier BUY is soft when Index Gap Settled is Silver or better: the symbol falls with the market
        // (a broad downturn, when the Dip Buying Plan says buy), not on its own. Off for now: in the first calibration
        // the softened weak-tier BUYs did worse than the ones that stayed serious (few, mostly one position averaged
        // down). The review keeps measuring both groups; turn it back on if the softened ones catch up.
        'soften_weak_tier_on_index_gap' => (bool) env('MYFINANCE2_ORDER_WARNINGS_SOFTEN_WEAK_TIER', false),
        // Seconds the live tier reading of the order form is kept per user, so the banner, the save and the
        // re-check read the same figures (cleared when a trade changes).
        'live_cache_seconds' => (int) env('MYFINANCE2_ORDER_WARNINGS_LIVE_CACHE_SECONDS', 180),
    ],
];
