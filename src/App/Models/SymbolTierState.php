<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

/**
 * Per (user, symbol), as of the last cron run: today's tier (`tier`), the settled tier (12-month
 * median), the day that settled tier was first seen, the range-bound reading (`range_bound`, null
 * when the pattern does not hold), the weak-tier-in-profit reading (`take_profit`, null when it does
 * not hold) and the tier signal chip (`tier_signal`, null without a signal; no severity, which needs the
 * dashboard). Lets a later run tell when the settled tier changed, and the alerts page mark range-bound,
 * TAKE PROFIT and tier signal symbols. Written only by the cron (the single writer); the dashboard read
 * path never persists.
 */
class SymbolTierState extends MyFinance2Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'id'            => 'integer',
        'user_id'       => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
        'settled_since' => 'date',
        'range_bound'   => 'array',
        'take_profit'   => 'array',
        'tier_signal'   => 'array',
    ];

    protected $fillable = [
        'symbol',
        'tier',
        'settled_tier',
        'settled_since',
        'range_bound',
        'take_profit',
        'tier_signal',
    ];
}
