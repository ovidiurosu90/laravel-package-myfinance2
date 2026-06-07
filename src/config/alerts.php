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
    ],
];
