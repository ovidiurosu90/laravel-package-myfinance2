<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail\Concerns;

use Illuminate\Support\Facades\Route;

/**
 * The links at the end of a per-symbol alert email (price alert, peak-proximity alert): a prefilled order, the
 * watchlist and the symbol's quote, built the same way in each.
 */
trait LinksSymbolActions
{
    /** The order side each reading action points to; an action not listed (HOLD, REVIEW) prefills no side. */
    private static array $_orderSides = [
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
    public static function orderSideFor(?string $action): ?string
    {
        return self::$_orderSides[$action] ?? null;
    }

    /**
     * The create-order link, prefilled with the symbol, the side (when known) and the price as the limit.
     *
     * @param string      $symbol
     * @param string|null $side  BUY / SELL
     * @param float|null  $price
     * @param array       $extra more query parameters (e.g. the price alert's source and id)
     */
    private function _orderUrl(string $symbol, ?string $side, ?float $price, array $extra = []): string
    {
        $query = array_filter([
            'symbol'      => $symbol,
            'action'      => $side,
            // A query parameter, so no thousands separator.
            'limit_price' => $price !== null ? number_format($price, 4, '.', '') : null,
        ], fn ($value) => $value !== null);

        return route('myfinance2::orders.create') . '?' . http_build_query(array_merge($query, $extra));
    }

    /**
     * "Create Sell Order" / "Create Buy Order" / "Create Order", as the button reads.
     */
    private static function _orderLabel(?string $side): string
    {
        return '→ Create ' . ($side !== null ? ucfirst(strtolower($side)) . ' ' : '') . 'Order';
    }

    /**
     * The watchlist symbols page, or the symbol's quote when the route is not registered.
     */
    private function _watchlistUrl(string $symbol): string
    {
        return Route::has('myfinance2::watchlist-symbols.index')
            ? route('myfinance2::watchlist-symbols.index')
            : 'https://finance.yahoo.com/quote/' . $symbol;
    }
}
