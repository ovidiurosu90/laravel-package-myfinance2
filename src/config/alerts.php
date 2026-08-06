<?php

return [
    'guiCreateMiddlewareType' => env('ALERTS_GUI_CREATE_MIDDLEWARE_TYPE', 'permissions'),
    'guiCreateMiddleware'     => env('ALERTS_GUI_CREATE_MIDDLEWARE', 'alerts.create'),

    'enabled'                  => env('MYFINANCE2_ALERTS_ENABLED', true),
    'email_to'                 => env('MYFINANCE2_ALERTS_EMAIL_TO', null),
    'eval_interval_minutes'    => env('MYFINANCE2_ALERTS_EVAL_INTERVAL_MINUTES', 5),
    'eval_max_seconds'         => env('MYFINANCE2_ALERTS_EVAL_MAX_SECONDS', 20),
    'throttle_hours'           => env('MYFINANCE2_ALERTS_THROTTLE_HOURS', 24),
    'suggestion_threshold_pct' => env('MYFINANCE2_SUGGESTION_THRESHOLD_PCT', 3),
    'market_hours_only'        => env('MYFINANCE2_ALERTS_MARKET_HOURS_ONLY', false),

    'peak_proximity' => [
        'enabled'       => env('MYFINANCE2_PEAK_PROXIMITY_ENABLED', true),
        // Global fallback used when a window has no specific threshold and the --threshold
        // override is not supplied.
        'threshold_pct' => env('MYFINANCE2_PEAK_PROXIMITY_THRESHOLD_PCT', 5),
        'windows'       => ['3m', '6m', '1y', '2y'],
        // Per-window proximity threshold (%): the nearer-term peaks are tighter, the longer-term
        // peaks are looser, so a 2Y all-time-high run still fires while a 3M wobble must be very
        // close. A position fires when it is within these many % of that window's peak.
        'window_thresholds' => [
            '3m' => env('MYFINANCE2_PEAK_PROXIMITY_THRESHOLD_3M', 2),
            '6m' => env('MYFINANCE2_PEAK_PROXIMITY_THRESHOLD_6M', 5),
            '1y' => env('MYFINANCE2_PEAK_PROXIMITY_THRESHOLD_1Y', 8),
            '2y' => env('MYFINANCE2_PEAK_PROXIMITY_THRESHOLD_2Y', 10),
        ],
        'email_to'      => env('MYFINANCE2_PEAK_PROXIMITY_EMAIL_TO', null),

        // Exit-focused gating. "Near peak" is an exit aid, so an email only fires for a position you
        // would actually consider trimming, i.e. one whose gain-based effective_tier is weak. The
        // HOLD/EXIT action is shown for context but is never a gate. When exit_focused is false the
        // gate is just "any window near peak" (the older, noisier behavior).
        'exit_focused'   => env('MYFINANCE2_PEAK_PROXIMITY_EXIT_FOCUSED', true),
        // Gain-based tiers (TierCalculationService constants) considered exit-worthy.
        'exit_tiers'     => ['RUST', 'BRONZE'],
        // A 3M-only trigger is context only and never emails on its own; an email needs at least one
        // of these longer windows near peak.
        'meaningful_windows' => ['6m', '1y', '2y'],
        'short_windows'      => ['3m'],
        // RSI at or above this counts as overbought and bumps an alert's severity to HIGH.
        'rsi_overbought' => env('MYFINANCE2_PEAK_PROXIMITY_RSI_OVERBOUGHT', 70),

        // Escalating cadence. The reminder interval (days) shrinks as more long windows are near peak
        // at once (more confluence = closer to a real top). A new long window crossing into near-peak
        // emails immediately, regardless of the interval. No reminder cap: it keeps going while the
        // alert stays open and actionable, until the user dismisses it.
        'reminder_days_by_confluence' => [1 => 7, 2 => 3, 3 => 1],
        'reminder_days_default'       => env('MYFINANCE2_PEAK_PROXIMITY_REMINDER_DAYS', 7),
    ],

    // Dip Buying Plan: paced cash deployment through drawdowns. Behavioral damage-control for a
    // dip-buying cash pool, not an alpha bet. One engine (DipBuyingPlanService) feeds the
    // /positions panel, the opt-in daily email, the settings page and the self-validation backtest.
    'dip_buying' => [
        'enabled' => env('MYFINANCE2_DIP_BUYING_ENABLED', true),

        // Front-loaded reserve ladder: cumulative % of the pool to have deployed at each effective
        // drawdown depth. Calibrated to participate hard in the common -10% to -25% corrections,
        // fully deployed by a -30% drop (~75% in by the worst real low of ~-23.5%) while keeping a
        // small reserve for a deeper leg, rather than holding back for a -35%+ crash that is rare.
        // Each entry is [min_drawdown_pct, cumulative_target_pct]; the deepest matching band wins.
        // A per-user override (DipBuyingSetting.bands JSON) replaces this default when present.
        'bands' => [
            ['dd' => 0,  'target' => 0],   // under 10%: keep powder dry
            ['dd' => 10, 'target' => 30],
            ['dd' => 15, 'target' => 55],
            ['dd' => 20, 'target' => 75],
            ['dd' => 25, 'target' => 90],
            ['dd' => 30, 'target' => 100], // all in
        ],

        // Gap tolerance (percentage points) before the verdict flips to behind / ahead of plan.
        'tolerance_pct' => env('MYFINANCE2_DIP_BUYING_TOLERANCE_PCT', 5),

        // Trailing-peak lookback (years) for the VUSA.AS drawdown anchor.
        'peak_lookback_years' => env('MYFINANCE2_DIP_BUYING_PEAK_LOOKBACK_YEARS', 3),

        // Moving-average period (days) for the informational trend rail (VUSA above/below its MA).
        // The 200-day (10-month) MA is the only timing rule with robust long-run evidence; it is
        // context here, never a gate. Tranches deploy on schedule regardless of the trend.
        'ma_trend_period' => env('MYFINANCE2_DIP_BUYING_MA_TREND_PERIOD', 200),

        // RSI(14) oversold note: an optional, off-by-default descriptive add-on for the trend rail.
        'rsi_note_enabled'   => env('MYFINANCE2_DIP_BUYING_RSI_NOTE_ENABLED', false),
        'rsi_oversold_level' => env('MYFINANCE2_DIP_BUYING_RSI_OVERSOLD_LEVEL', 30),

        // Stall backstop: once an episode has been active (effective_dd >= MIN_EPISODE_DD) for this
        // many months without entering a new, deeper band, release the remaining pool on a plain
        // monthly schedule over the following this-many months. A fresh deeper band resets the clock.
        'stall_backstop_months' => env('MYFINANCE2_DIP_BUYING_STALL_BACKSTOP_MONTHS', 6),

        // Effective drawdown (%) at or above which an episode is considered active (band 1 floor).
        // Drives the live ladder/stall backstop; keep aligned with the first band's drawdown floor.
        'min_episode_dd_pct' => env('MYFINANCE2_DIP_BUYING_MIN_EPISODE_DD_PCT', 10),

        // Default depth (%) at or above which the backtest/chart isolate a "drop" episode. Distinct
        // from min_episode_dd_pct (the ladder's active floor): this only controls which dips are
        // surfaced and shaded, and is overridable per request from the chart's drop-config input.
        'min_drop_pct' => env('MYFINANCE2_DIP_BUYING_MIN_DROP_PCT', 5),

        'email_to' => env('MYFINANCE2_DIP_BUYING_EMAIL_TO', null),
    ],

    // Portfolio Peak Alerts: email when the portfolio's EUR gain or return on cost is within N% of
    // its 6M/1Y/2Y high. Portfolio-level "consider exiting / starting over" hint, complementary to
    // the per-symbol peak-proximity alerts. Negative window peaks are in scope (a book that keeps
    // selling winners can be underwater at its best); change_EUR proximity is measured against
    // |peak| with a magnitude floor, change_pct on the value index (1 + cp/100).
    'portfolio_peak' => [
        'enabled' => env('MYFINANCE2_PORTFOLIO_PEAK_ENABLED', true),
        // Windows that can actually fire the alert. 3M is deliberately excluded (too short for a
        // "consider a full exit" signal), but it is still shown in the email as context (see
        // display_windows), mirroring how the per-symbol peak-proximity alert treats 3M.
        'windows' => ['6m', '1y', '2y'],
        // Windows rendered in the email's per-window breakdown table, in display order. 3M is
        // context only here; it never gates a send.
        'display_windows' => ['3m', '6m', '1y', '2y'],
        // Per-metric, per-window proximity thresholds (%). They differ by metric on purpose: EUR gain
        // proximity is measured against |peak| and is contribution-sensitive (adding capital moves the
        // absolute euro P&L independently of performance), so its bands are wide; Return % proximity is
        // on the normalized value index, so its bands are tight. 3M is a reference line only (never
        // fires; see windows vs display_windows).
        'window_thresholds' => [
            'change_eur' => [
                '3m' => env('MYFINANCE2_PORTFOLIO_PEAK_EUR_THRESHOLD_3M', 5),
                '6m' => env('MYFINANCE2_PORTFOLIO_PEAK_EUR_THRESHOLD_6M', 10),
                '1y' => env('MYFINANCE2_PORTFOLIO_PEAK_EUR_THRESHOLD_1Y', 20),
                '2y' => env('MYFINANCE2_PORTFOLIO_PEAK_EUR_THRESHOLD_2Y', 30),
            ],
            'change_pct' => [
                '3m' => env('MYFINANCE2_PORTFOLIO_PEAK_PCT_THRESHOLD_3M', 0.5),
                '6m' => env('MYFINANCE2_PORTFOLIO_PEAK_PCT_THRESHOLD_6M', 1),
                '1y' => env('MYFINANCE2_PORTFOLIO_PEAK_PCT_THRESHOLD_1Y', 3),
                '2y' => env('MYFINANCE2_PORTFOLIO_PEAK_PCT_THRESHOLD_2Y', 5),
            ],
        ],
        // change_EUR windows whose peak magnitude is under this floor (EUR) are skipped; a
        // threshold band around a near-zero peak would flip on noise.
        'min_peak_abs_eur' => env('MYFINANCE2_PORTFOLIO_PEAK_MIN_PEAK_ABS_EUR', 1000),
        // Calendar days between emails while the condition keeps holding. 1 = one per day, the
        // first hourly run of each day that still has a triggered window. Raise it (e.g. 7) for a
        // weekly reminder instead.
        'reminder_days'    => env('MYFINANCE2_PORTFOLIO_PEAK_REMINDER_DAYS', 1),
        'email_to'         => env('MYFINANCE2_PORTFOLIO_PEAK_EMAIL_TO', null),
    ],
];
