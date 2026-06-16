<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

class MoneyFormat
{
    /**
     * Format a price with decimal handling.
     * Default ($highPrecision = false): 0 decimals >= 1,000 / 2 decimals otherwise.
     * With $highPrecision = true: 4-tier logic for stock/crypto per-share prices.
     *   - Prices >= 1,000 : 0 decimals
     *   - Prices >= 1.0   : 2 decimals (normal stocks)
     *   - Prices >= 0.01  : 4 decimals (penny stocks)
     *   - Prices < 0.01   : 6 decimals (micro-cap / cheap crypto)
     * Handles negative values correctly.
     *
     * @param mixed $value
     * @param bool  $highPrecision Pass true for per-share stock/crypto prices
     *
     * @return string
     */
    public static function get_formatted_price($value, bool $highPrecision = false): string
    {
        $floatValue = (float) $value;
        $abs = abs($floatValue);

        if ($abs >= 1000.0) {
            return number_format($floatValue, 0);
        }
        if (!$highPrecision) {
            return number_format($floatValue, 2);
        }
        if ($abs >= 1.0) {
            return number_format($floatValue, 2);
        }
        if ($abs >= 0.01) {
            return number_format($floatValue, 4);
        }
        $formatted = number_format($floatValue, 6);
        return ($formatted === '0.000000' || $formatted === '-0.000000') ? '0' : $formatted;
    }

    /**
     * Minimum price display format: "1,289 €" (>= 1,000) or "894.20 €" (< 1,000).
     * Plain string — no HTML span. Use {!! !!} in Blade when the currency code
     * contains HTML entities (e.g. "&euro;").
     * Extends get_formatted_price with a currency suffix.
     *
     * @param string $currencyDisplayCode
     * @param mixed  $value
     * @param bool   $highPrecision Pass true for per-share stock/crypto prices
     *
     * @return string
     */
    public static function get_formatted_price_display(
        $currencyDisplayCode, $value, bool $highPrecision = false
    ): string {
        return self::get_formatted_price($value, $highPrecision) . ' ' . $currencyDisplayCode;
    }

    /**
     * Format a monetary amount (fees, totals, dividends) — always max 2 decimals.
     * Unlike get_formatted_price, this never uses 4 or 6 decimals: those are for
     * stock prices where penny-stock precision matters. Monetary amounts like fees
     * are already expressed in the account currency and need at most cent precision.
     * - abs >= 1,000 : 0 decimals  e.g. "1,234 €"
     * - abs <  1,000 : 2 decimals  e.g. "49.09 €" or "0.50 €"
     * Plain string — no HTML span. Use {!! !!} in Blade for HTML entities.
     *
     * @param string $currencyDisplayCode
     * @param mixed  $value
     *
     * @return string
     */
    public static function get_formatted_monetary_display($currencyDisplayCode, $value, int $decimals = 2): string
    {
        $floatValue = (float) $value;
        $formatted = abs($floatValue) >= 1000.0
            ? number_format($floatValue, 0)
            : number_format($floatValue, $decimals);
        return $formatted . ' ' . $currencyDisplayCode;
    }

    /**
     * Format a percentage with threshold-aware decimal handling (plain, no HTML, no sign, no %).
     * - abs >= 100 : 0 decimals
     * - abs < 100  : 2 decimals
     * Returns just the numeric string; callers prepend sign and append "%".
     *
     * @param mixed $value
     *
     * @return string e.g. "89.88" or "145" or "31.77"
     */
    public static function get_formatted_pct($value): string
    {
        $floatValue = (float) $value;
        $abs = abs($floatValue);
        return number_format($floatValue, $abs >= 100 ? 0 : 2);
    }

    /**
     * @param string $currencyDisplayCode
     * @param double $value
     *
     * @return string (formatted value)
     */
    public static function get_formatted_balance($currencyDisplayCode, $value)
    {
        if ($value == 0) {
            return '0 ' . $currencyDisplayCode;
        }
        $floatValue = (float) $value;
        $abs = abs($floatValue);
        $formatted = $abs >= 1000.0
            ? number_format($floatValue, 0)
            : number_format($floatValue, 2);
        return '<span class="">' . $formatted . ' ' . $currencyDisplayCode . '</span>';
    }

    /**
     * @param string  $currencyDisplayCode
     * @param double  $value
     * @param string  $type
     * @param integer $numDecimals
     *
     * @return string (formatted value)
     */
    public static function get_formatted_amount($currencyDisplayCode, $value,
        $type = 'credit', $numDecimals = 4
    ) {
        $amountFormats = config('general.row-format-amount');
        $formatAmount = config('general.row-format-amount.unknown');
        if (!empty($amountFormats[$type])) {
            $formatAmount = $amountFormats[$type];
        }

        return '<span class="' . $formatAmount . '">' .
            @number_format($value, $numDecimals) . ' ' .
            $currencyDisplayCode . '</span>';
    }

    /**
     * @param string $currencyDisplayCode
     * @param double $value
     *
     * @return string (formatted value)
     */
    public static function get_formatted_fee($currencyDisplayCode, $value)
    {
        if ($value == 0) {
            return '0 ' . $currencyDisplayCode;
        }

        $abs = abs((float) $value);
        $formatted = $abs >= 1000.0
            ? number_format($abs, 0)
            : number_format($abs, 2);
        return '<span class="text-danger">- ' . $formatted . ' ' . $currencyDisplayCode . '</span>';
    }

    /**
     * @param string $currencyDisplayCode
     * @param double $value
     *
     * @return string (formatted value)
     */
    //NOTE This is used a lot
    public static function get_formatted_gain($currencyDisplayCode, $value, ?int $decimals = null)
    {
        if ($value == 0) {
            return '0 ' . $currencyDisplayCode;
        }
        if ($decimals !== null) {
            $formatted = number_format(abs((float) $value), $decimals);
        } elseif ($currencyDisplayCode === '%') {
            $formatted = self::get_formatted_pct(abs((float) $value));
        } else {
            $formatted = self::get_formatted_price(abs((float) $value));
        }
        if ($formatted === '0') {
            return '0 ' . $currencyDisplayCode;
        }
        if ($value < 0) {
            return '<span class="text-danger">- ' . $formatted
                   . ' ' . $currencyDisplayCode . '</span>';
        }
        return '<span class="text-success">+ ' . $formatted . ' '
               . $currencyDisplayCode . '</span>';
    }



    /**
     * @param double $value
     *
     * @return string (formatted value)
     */
    public static function get_formatted_balance_percentage($value)
    {
        if ($value == 0) {
            return '0 %';
        }
        if ($value < 0) {
            return '<span>- ' . self::get_formatted_pct(abs($value)) . ' %</span>';
        }
        return '<span>+ ' . self::get_formatted_pct($value) . ' %</span>';
    }

    /**
     * @param double $value
     *
     * @return string (formatted value)
     */
    public static function get_formatted_gain_percentage($value)
    {
        if ($value == 0) {
            return '0 %';
        }
        if ($value < 0) {
            return '<span class="text-danger">- ' . self::get_formatted_pct(abs($value))
                   . ' %</span>';
        }
        return '<span class="text-success">+ ' . self::get_formatted_pct($value)
               . ' %</span>';
    }

    /**
     * @param double $value
     *
     * @return string (formatted value)
     */
    public static function get_formatted_52wk_low_percentage($value)
    {
        if ($value == 0) {
            return '0 %';
        }

        $class = '';
        if ($value < 15) {
            $class = 'text-danger';
        }

        if ($value < 0) {
            return '<span class="' . $class . '">- '
                   . self::get_formatted_pct(abs($value)) . ' %</span>';
        }
        return '<span class="' . $class . '">+ '
               . self::get_formatted_pct($value) . ' %</span>';
    }

    /**
     * @param double $value
     * @param boolean $hasOpenPositions
     *
     * @return string (formatted value)
     */
    public static function get_formatted_52wk_high_percentage(
        $value, $hasOpenPositions = false
    ) {
        if ($value == 0) {
            return '0 %';
        }

        $class = '';
        if ($value < 5 && $hasOpenPositions) {
            $class = 'text-success font-weight-bolder';
        }

        if ($value < 0) {
            return '<span class="' . $class . '">- '
                   . self::get_formatted_pct(abs($value)) . ' %</span>';
        }
        return '<span class="' . $class . '">+ '
                   . self::get_formatted_pct($value) . ' %</span>';
    }

    /**
     * Format a plain number without HTML or currency wrapper.
     * Used for tooltip content, comparisons, and internal values.
     *
     * @param double $value
     * @param integer $numDecimals
     *
     * @return string (formatted number without HTML)
     */
    public static function get_formatted_number_plain($value, $numDecimals = 2)
    {
        return number_format($value, $numDecimals);
    }

    /**
     * EUR amount for a number input or data attribute: no thousands separator (so the raw value
     * parses), cents dropped at >= 1000 where they add no precision, and kept (trailing zeros
     * trimmed) below it. Routes the separator-free formatting through MoneyFormat so views never
     * call number_format() directly.
     *
     * @param mixed $value
     *
     * @return string
     */
    public static function get_formatted_amount_input_plain($value)
    {
        $floatValue = (float) $value;
        if ($floatValue >= 1000.0) {
            return number_format($floatValue, 0, '.', '');
        }

        return rtrim(rtrim(number_format($floatValue, 2, '.', ''), '0'), '.');
    }

    /**
     * Format a price — plain, no HTML, no currency.
     * Delegates to get_formatted_price for consistent threshold behaviour.
     *
     * @param double $value
     * @param bool   $highPrecision Pass true for per-share stock/crypto prices
     *
     * @return string
     */
    public static function get_formatted_price_plain($value, bool $highPrecision = false)
    {
        return self::get_formatted_price($value, $highPrecision);
    }

    /**
     * Format an exchange rate with intelligent decimal handling (plain, no HTML).
     * Uses 0 decimals for whole numbers, 4 decimals for detailed rates.
     * Safely casts string values from database.
     * Used for tooltips and internal comparisons.
     *
     * @param mixed $value (string or float from database)
     *
     * @return string (formatted rate without HTML or currency)
     */
    public static function get_formatted_rate_plain($value)
    {
        $floatValue = (float)$value;
        $numDecimals = self::get_rate_decimals($floatValue);
        return number_format($floatValue, $numDecimals);
    }

    /**
     * Get optimal decimal places for a price.
     * Returns 2 decimals if the price rounds to 2 decimals, otherwise 4.
     *
     * @param mixed $value (string or float from database)
     *
     * @return integer (number of decimals to use)
     */
    public static function get_price_decimals($value)
    {
        $floatValue = (float)$value;
        return round($floatValue, 2) == round($floatValue, 4) ? 2 : 4;
    }

    /**
     * Get optimal decimal places for a quantity.
     * Returns 0 decimals if whole number, otherwise 6.
     *
     * @param mixed $value (string or float from database)
     *
     * @return integer (number of decimals to use)
     */
    public static function get_quantity_decimals($value)
    {
        $floatValue = (float)$value;
        return round($floatValue) == $floatValue ? 0 : 6;
    }

    /**
     * Get optimal decimal places for an exchange rate.
     * Returns 0 decimals if whole number, otherwise 4.
     *
     * @param mixed $value (string or float from database)
     *
     * @return integer (number of decimals to use)
     */
    public static function get_rate_decimals($value)
    {
        $floatValue = (float)$value;
        return round($floatValue) == $floatValue ? 0 : 4;
    }

    /**
     * Format a quantity with intelligent decimal handling (plain, no HTML).
     * Uses 0 decimals for whole numbers, 6 decimals for fractional quantities.
     * Safely casts string values from database.
     *
     * @param mixed $value (string or float from database)
     *
     * @return string (formatted quantity without HTML)
     */
    public static function get_formatted_quantity_plain($value)
    {
        $floatValue = (float)$value;
        $numDecimals = self::get_quantity_decimals($floatValue);
        return number_format($floatValue, $numDecimals);
    }

    public static function get_partial_gain_tooltip(
        int $quantitySold,
        int $remainingQuantity,
        float $avgCostPerShare,
        string $currencyDisplayCode
    ): string
    {
        $currency = strip_tags($currencyDisplayCode);
        return '<b>Partial position:</b> '
            . $quantitySold . ' shares sold, '
            . $remainingQuantity . ' still held.'
            . '<br><br><b>Cost basis methods:</b>'
            . '<br>&bull; <b>FIFO</b>: sells oldest shares first.'
            . '<br>&bull; <b>LIFO</b>: sells newest shares first.'
            . '<br>&bull; <b>Average Cost</b> &#10003; (chosen):'
            . ' pools all purchases into a weighted average,'
            . ' smoothing cost differences across buys.'
            . '<br><br>Cost basis used: <b>'
            . self::get_formatted_price($avgCostPerShare, true) . ' ' . $currency
            . '/share</b>.';
    }
}
