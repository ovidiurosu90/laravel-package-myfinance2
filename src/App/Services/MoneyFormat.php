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
}

