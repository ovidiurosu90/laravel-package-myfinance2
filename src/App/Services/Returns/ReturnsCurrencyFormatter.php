<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Currency Formatter Service
 *
 * Centralizes all currency formatting logic to eliminate code duplication.
 * Handles formatting of portfolio values, transactions, and trades.
 */
class ReturnsCurrencyFormatter
{
    /**
     * Fields that require simple balance formatting
     */
    private const SIMPLE_BALANCE_FIELDS = [
        'jan1Value',
        'dec31Value',
        'jan1PositionsValue',
        'dec31PositionsValue',
        'jan1CashValue',
        'dec31CashValue',
        'totalDeposits',
        'totalWithdrawals',
        'totalGrossDividends',
        'totalPurchases',
        'totalPurchasesFees',
        'totalPurchasesExcludedFees',
        'totalPurchasesNet',
        'totalSales',
        'totalSalesFees',
        'totalSalesExcludedFees',
        'totalSalesNet',
        'totalDepositsFees',
        'totalWithdrawalsFees',
    ];

    /**
     * Format all values in converted returns data
     *
     * @param array $converted The converted data array (passed by reference)
     * @param string $displayCode Currency display code (€, $, etc.)
     */
    public function formatConvertedValues(array &$converted, string $displayCode): void
    {
        // Format simple balance fields
        $this->_formatSimpleBalanceFields($converted, $displayCode);

        // Format conditional fields
        $this->_formatConditionalFields($converted, $displayCode);

        // Format individual transactions (purchases and sales)
        $this->_formatTradeTransactions($converted, $displayCode);

        // Format deposit/withdrawal transaction fees
        $this->_formatDepositWithdrawalFees($converted, $displayCode);
    }

    /**
     * Format simple balance fields that always exist
     */
    private function _formatSimpleBalanceFields(array &$converted, string $displayCode): void
    {
        foreach (self::SIMPLE_BALANCE_FIELDS as $field) {
            if (isset($converted[$field])) {
                $converted["{$field}Formatted"] = MoneyFormat::get_formatted_balance(
                    $displayCode,
                    $converted[$field]
                );
            }
        }
    }

    /**
     * Format conditional fields that may or may not exist
     */
    private function _formatConditionalFields(array &$converted, string $displayCode): void
    {
        // Format withdrawals calculated value if it exists
        if (isset($converted['totalWithdrawalsCalculated'])) {
            $converted['totalWithdrawalsCalculatedFormatted'] =
                MoneyFormat::get_formatted_balance(
                    $displayCode,
                    $converted['totalWithdrawalsCalculated']
                );
        }

        // Format dividends calculated value if it exists
        if (isset($converted['totalGrossDividendsCalculated'])) {
            $converted['totalGrossDividendsCalculatedFormatted'] =
                MoneyFormat::get_formatted_balance(
                    $displayCode,
                    $converted['totalGrossDividendsCalculated']
                );
        }

        // Format dividends override value if it exists
        if (isset($converted['totalGrossDividendsOverride'])) {
            $converted['totalGrossDividendsOverrideFormatted'] =
                MoneyFormat::get_formatted_balance(
                    $displayCode,
                    $converted['totalGrossDividendsOverride']
                );
        } else {
            $converted['totalGrossDividendsOverrideFormatted'] = null;
        }
    }

    /**
     * Format individual trade transactions (purchases and sales)
     */
    private function _formatTradeTransactions(array &$converted, string $displayCode): void
    {
        // Format purchases
        if (!empty($converted['purchases'])) {
            $this->_formatTradeList($converted['purchases'], $displayCode);
        }

        // Format sales
        if (!empty($converted['sales'])) {
            $this->_formatTradeList($converted['sales'], $displayCode);
        }
    }

    /**
     * Format a list of trades (purchases or sales)
     *
     * @param array $trades Trade list (passed by reference)
     * @param string $displayCode Currency display code
     */
    private function _formatTradeList(array &$trades, string $displayCode): void
    {
        foreach ($trades as &$trade) {
            $amountToFormat = $trade['principal_amount'] ?? 0;
            $trade['principalAmountFormatted'] = MoneyFormat::get_formatted_balance(
                $displayCode,
                $amountToFormat
            );

            // Format converted fee if it exists
            if (!empty($trade['fee'])) {
                $trade['feeFormatted'] = MoneyFormat::get_formatted_balance(
                    $displayCode,
                    $trade['fee']
                );
            }
        }
        unset($trade); // Break reference
    }

    /**
     * Format fees on individual deposit/withdrawal items
     */
    private function _formatDepositWithdrawalFees(array &$converted, string $displayCode): void
    {
        foreach (['deposits', 'withdrawals'] as $type) {
            if (!empty($converted[$type])) {
                foreach ($converted[$type] as &$item) {
                    if (!empty($item['fee'])) {
                        $item['feeFormatted'] = MoneyFormat::get_formatted_balance(
                            $displayCode,
                            $item['fee']
                        );
                    } else {
                        $item['feeFormatted'] = '';
                    }
                }
                unset($item);
            }
        }
    }

    /**
     * Format a single monetary value
     *
     * @param string $displayCode Currency display code
     * @param float $value Value to format
     * @return string Formatted value
     */
    public function formatBalance(string $displayCode, float $value): string
    {
        return MoneyFormat::get_formatted_balance($displayCode, $value);
    }

    /**
     * Format a gain/return value (with color formatting for positive/negative)
     *
     * @param string $displayCode Currency display code
     * @param float $value Value to format
     * @return string Formatted value with color
     */
    public function formatGain(string $displayCode, float $value): string
    {
        return MoneyFormat::get_formatted_gain($displayCode, $value);
    }
}
