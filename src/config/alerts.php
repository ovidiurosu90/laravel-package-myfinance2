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
];
