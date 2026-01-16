<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

class MoneyFormat
{
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
        return '<span class="">' . @number_format($value, 2) . ' '
               . $currencyDisplayCode . '</span>';
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

        return self::get_formatted_gain($currencyDisplayCode, -abs($value));
    }

    /**
     * @param string $currencyDisplayCode
     * @param double $value
     *
     * @return string (formatted value)
     */
    //NOTE This is used a lot
    public static function get_formatted_gain($currencyDisplayCode, $value)
    {
        if ($value == 0) {
            return '0 ' . $currencyDisplayCode;
        }
        if ($value < 0) {
            return '<span class="text-danger">- ' . number_format(abs($value), 2)
                   . ' ' . $currencyDisplayCode . '</span>';
        }
        return '<span class="text-success">+ ' . number_format($value, 2) . ' '
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
            return '<span>- ' . number_format(abs($value), 2) . ' %</span>';
        }
        return '<span>+ ' . number_format($value, 2) . ' %</span>';
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
            return '<span class="text-danger">- ' . number_format(abs($value), 2)
                   . ' %</span>';
        }
        return '<span class="text-success">+ ' . number_format($value, 2)
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
                   . number_format(abs($value), 2) . ' %</span>';
        }
        return '<span class="' . $class . '">+ '
               . number_format($value, 2) . ' %</span>';
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
                   . number_format(abs($value), 2) . ' %</span>';
        }
        return '<span class="' . $class . '">+ '
                   . number_format($value, 2) . ' %</span>';
    }

    /**
     * Format a plain number without HTML or currency wrapper
     * Used for tooltip content, comparisons, and internal values
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
     * Format a price with intelligent decimal handling (plain, no HTML)
     * Uses 2 decimals for round numbers, 4 decimals for detailed prices
     * Used for tooltips and internal comparisons
     *
     * @param double $value
     *
     * @return string (formatted price without HTML or currency)
     */
    public static function get_formatted_price_plain($value)
    {
        $floatValue = (float)$value;
        $numDecimals = self::get_price_decimals($floatValue);
        return number_format($floatValue, $numDecimals);
    }

    /**
     * Format an exchange rate with intelligent decimal handling (plain, no HTML)
     * Uses 0 decimals for whole numbers, 4 decimals for detailed rates
     * Safely casts string values from database
     * Used for tooltips and internal comparisons
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
     * Get optimal decimal places for a price
     * Returns 2 decimals if the price rounds to 2 decimals, otherwise 4
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
     * Get optimal decimal places for a quantity
     * Returns 0 decimals if whole number, otherwise 6
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
     * Get optimal decimal places for an exchange rate
     * Returns 0 decimals if whole number, otherwise 4
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
     * Format a quantity with intelligent decimal handling (plain, no HTML)
     * Uses 0 decimals for whole numbers, 6 decimals for fractional quantities
     * Safely casts string values from database
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
}

