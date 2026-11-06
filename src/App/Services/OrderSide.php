<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The order side (BUY / SELL) a tier reading's action calls for: the alert emails' prefilled order links and the
 * order form's side hint read the same map.
 */
final class OrderSide
{
    /** The side each reading action points to; an action not listed (HOLD, REVIEW) calls for no side. */
    private const _SIDES = [
        'ACCUMULATE'  => 'BUY',
        'ADD'         => 'BUY',
        'BUY'         => 'BUY',
        'REDUCE'      => 'SELL',
        'EXIT'        => 'SELL',
        'TRIM'        => 'SELL',
        'TAKE PROFIT' => 'SELL',
        'SELL'        => 'SELL',
    ];

    /**
     * The order side (BUY / SELL) an action calls for, or null when it calls for none.
     */
    public static function forAction(?string $action): ?string
    {
        return self::_SIDES[$action] ?? null;
    }
}
