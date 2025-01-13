<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

class MoneyFormat
{
    /**
     * @param string $currency
     *
     * @return string $currencyDisplay
     */
    public static function get_currency_display($currency)
    {
        $currencyDisplay = config('general.currencies_display.unknown');

        if (!$currency) {
            return $currencyDisplay;
        }

        $currenciesDisplay = config('general.currencies_display');
        if (isset($currenciesDisplay[$currency])) {
            $currencyDisplay = $currenciesDisplay[$currency];
        }
        return $currencyDisplay;
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return boolean
     */
    public static function ends_with($haystack, $needle)
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }

    /**
     * @param string $currency
     * @param double $value
     *
     * @return string (formatted value)
     */
    public static function get_formatted_balance($currency, $value)
    {
        $currencyDisplay = self::get_currency_display($currency);

        if ($value == 0) {
            return '0 ' . $currencyDisplay;
        }
        return '<span class="">' . @number_format($value, 2) . ' '
               . $currencyDisplay . '</span>';
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
     * @param string  $currency
     * @param double  $value
     * @param string  $type
     * @param integer $numDecimals
     *
     * @return string (formatted value)
     */
    public static function get_formatted_amount($currency, $value,
        $type = 'credit', $numDecimals = 4
    ) {
        $amountFormats = config('general.row-format-amount');
        $formatAmount = config('general.row-format-amount.unknown');
        if (!empty($amountFormats[$type])) {
            $formatAmount = $amountFormats[$type];
        }

        return '<span class="' . $formatAmount . '">' .
            @number_format($value, $numDecimals) . ' ' .
            MoneyFormat::get_currency_display($currency) . '</span>';
    }

    /**
     * @param string $currency
     * @param double $value
     *
     * @return string (formatted value)
     */
    public static function get_formatted_fee($currency, $value)
    {
        if ($value == 0) {
            return '0 ' . self::get_currency_display($currency);
        }

        return self::get_formatted_gain($currency, -abs($value));
    }

    /**
     * @param string $currency
     * @param double $value
     *
     * @return string (formatted value)
     */
    public static function get_formatted_gain($currency, $value)
    {
        $currencyDisplay = self::get_currency_display($currency);

        if ($value == 0) {
            return '0 ' . $currencyDisplay;
        }
        if ($value < 0) {
            return '<span class="text-danger">- ' . number_format(abs($value), 2)
                   . ' ' . $currencyDisplay . '</span>';
        }
        return '<span class="text-success">+ ' . number_format($value, 2) . ' '
               . $currencyDisplay . '</span>';
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
}

