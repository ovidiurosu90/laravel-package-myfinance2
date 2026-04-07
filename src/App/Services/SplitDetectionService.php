<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Centralised stock-split detection logic.
 *
 * Used by AlertService, MoversService and Returns\ReturnsAlerts so that the
 * same ratios, tolerance and detection approach are applied everywhere.
 *
 * Supported split ratios: 3:1, 5:1, 10:1, 20:1, 25:1 (forward and reverse).
 * Tolerance: 25% — accounts for price drift between a trade date and the
 * valuation/comparison date.
 */
class SplitDetectionService
{
    /** Stock split ratios to detect (forward and reverse). */
    public const SPLIT_RATIOS = [3, 5, 10, 20, 25];

    /** Fractional tolerance applied to each ratio when checking proximity. */
    public const RATIO_TOLERANCE = 0.25;

    /**
     * Check if a pre-computed price ratio matches any known split ratio (forward or reverse).
     *
     * Use this when the caller already holds a ratio value (e.g. day-over-day price in
     * MoversService). For a direct two-price comparison use isPriceSplitAnomaly() instead.
     *
     * @param float $ratio price_new / price_old (or any pre-computed ratio)
     *
     * @return bool
     */
    public static function looksLikeSplitRatio(float $ratio): bool
    {
        foreach (self::SPLIT_RATIOS as $sr) {
            if (self::_isClose($ratio, $sr)) {
                return true;
            }
            if (self::_isClose($ratio, 1.0 / $sr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if two prices suggest an unapplied stock split.
     *
     * Returns true when price1 / price2 (or its inverse) is close to a known split ratio.
     * The check is symmetric — argument order does not matter.
     *
     * Typical callers:
     *  - ReturnsAlerts: compare most-recent trade price vs current API price.
     *
     * @param float $price1
     * @param float $price2
     *
     * @return bool
     */
    public static function isPriceSplitAnomaly(float $price1, float $price2): bool
    {
        if ($price1 <= 0 || $price2 <= 0) {
            return false;
        }
        return self::looksLikeSplitRatio($price1 / $price2);
    }

    /**
     * Check if a price alert's target is suspiciously far from the current price,
     * suggesting the alert was set before an unapplied split.
     *
     * Uses a threshold (not proximity matching) so that ANY ratio beyond the smallest
     * known split ratio is flagged — not just values close to a specific split factor.
     * This mirrors AlertService::_isPotentialSplitAnomaly() exactly.
     *
     * @param string $alertType   'PRICE_ABOVE' | 'PRICE_BELOW'
     * @param float  $targetPrice
     * @param float  $currentPrice
     *
     * @return bool
     */
    public static function isAlertTargetStale(string $alertType, float $targetPrice, float $currentPrice): bool
    {
        if ($currentPrice <= 0 || $targetPrice <= 0) {
            return false;
        }

        $threshold = (float) min(self::SPLIT_RATIOS);

        return match ($alertType) {
            'PRICE_ABOVE' => $targetPrice > $currentPrice * $threshold,
            'PRICE_BELOW' => $targetPrice < $currentPrice / $threshold,
            default       => false,
        };
    }

    /**
     * Check if two trade quantities differ by a known split ratio.
     *
     * Only checks forward ratios (> 1) because qty1/qty2 is normalised to max/min.
     * Used in ReturnsAlerts to detect split-adjustment trade pairs (BUY + SELL on same day).
     *
     * @param float $qty1
     * @param float $qty2
     *
     * @return bool
     */
    public static function isQuantitySplitRatio(float $qty1, float $qty2): bool
    {
        if ($qty1 <= 0 || $qty2 <= 0) {
            return false;
        }
        $ratio = $qty1 > $qty2 ? $qty1 / $qty2 : $qty2 / $qty1;
        foreach (self::SPLIT_RATIOS as $sr) {
            if (self::_isClose($ratio, $sr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if $value is within RATIO_TOLERANCE of $target.
     *
     * @param float $value
     * @param float $target
     *
     * @return bool
     */
    private static function _isClose(float $value, float $target): bool
    {
        if ($target <= 0) {
            return false;
        }
        return abs($value - $target) <= $target * self::RATIO_TOLERANCE;
    }
}
