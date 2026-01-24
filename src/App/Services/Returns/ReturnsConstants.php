<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

/**
 * Constants used throughout the Returns functionality
 *
 * Centralizes magic numbers to improve maintainability and clarity.
 */
class ReturnsConstants
{
    /**
     * Maximum number of days to look back when searching for historical data
     * Used for weekend/holiday fallback when fetching quotes and exchange rates
     */
    public const MAX_FALLBACK_DAYS = 7;

    /**
     * Cache TTL for quote and exchange rate data (in seconds)
     * 1 hour = 3600 seconds
     */
    public const QUOTE_CACHE_TTL = 3600;

    /**
     * Threshold for determining if a portfolio value is significant (non-zero)
     * Values below this threshold are considered effectively zero
     */
    public const SIGNIFICANT_VALUE_THRESHOLD = 0.01;

    /**
     * Epsilon for floating point comparison when checking excluded fees
     * Used to handle floating point precision issues
     */
    public const EPSILON = 0.001;

    /**
     * Minimum year supported for returns calculation
     * Historical data reliability begins from this year
     */
    public const MIN_YEAR = 2016;

    /**
     * Number formatting decimal places for prices
     */
    public const PRICE_DECIMALS = 4;

    /**
     * Number formatting decimal places for quantities
     */
    public const QUANTITY_DECIMALS = 6;

    /**
     * Number formatting decimal places for exchange rates
     */
    public const EXCHANGE_RATE_DECIMALS = 4;

    /**
     * Number formatting decimal places for monetary amounts
     */
    public const MONEY_DECIMALS = 2;
}

