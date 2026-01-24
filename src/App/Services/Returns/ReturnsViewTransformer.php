<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Transforms Returns service data into view-ready format
 *
 * This service takes raw returns data (with EUR/USD separated) and transforms it
 * into a structure where:
 * - All calculations are pre-computed for both currencies
 * - Views only need to display and toggle between currencies
 * - No business logic remains in the view layer
 *
 * Now refactored to delegate to specialized transformers for better maintainability.
 */
class ReturnsViewTransformer
{
    private ReturnsValueTransformer $_valueTransformer;
    private ReturnsPositionTransformer $_positionTransformer;
    private ReturnsTransactionTransformer $_transactionTransformer;
    private ReturnsTradeTransformer $_tradeTransformer;

    public function __construct(
        ReturnsValueTransformer $valueTransformer = null,
        ReturnsPositionTransformer $positionTransformer = null,
        ReturnsTransactionTransformer $transactionTransformer = null,
        ReturnsTradeTransformer $tradeTransformer = null
    )
    {
        $this->_valueTransformer = $valueTransformer ?? new ReturnsValueTransformer();
        $this->_positionTransformer = $positionTransformer ?? new ReturnsPositionTransformer();
        $this->_transactionTransformer = $transactionTransformer ?? new ReturnsTransactionTransformer();
        $this->_tradeTransformer = $tradeTransformer ?? new ReturnsTradeTransformer();
    }
    /**
     * Transform returns data for view consumption
     *
     * @param array $returnsData Raw returns data from Returns service
     * @param int $year The year being displayed
     * @return array Transformed data ready for views
     */
    public function transform(array $returnsData, int $year): array
    {
        $transformed = [];

        foreach ($returnsData as $accountId => $accountData) {
            // Skip metadata entries
            if (in_array($accountId, ['totalReturnEUR', 'totalReturnUSD',
                'totalReturnEURFormatted', 'totalReturnUSDFormatted'])) {
                continue;
            }

            if (!isset($accountData['EUR']) || !isset($accountData['USD'])) {
                $transformed[$accountId] = $accountData;
                continue;
            }

            $transformed[$accountId] = $this->_transformAccount($accountData, $year);
        }

        return $transformed;
    }

    /**
     * Transform a single account's data
     */
    private function _transformAccount(array $accountData, int $year): array
    {
        return [
            'account' => $accountData['account'],
            'baseCurrency' => $accountData['baseCurrency'] ?? 'EUR',

            // Portfolio values with both currencies pre-formatted
            'jan1Value' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['jan1Value'],
                $accountData['USD']['jan1Value'],
                '€',
                '$'
            ),
            'jan1PositionsValue' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['jan1PositionsValue'],
                $accountData['USD']['jan1PositionsValue'],
                '€',
                '$'
            ),
            'jan1CashValue' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['jan1CashValue'],
                $accountData['USD']['jan1CashValue'],
                '€',
                '$'
            ),
            'dec31Value' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['dec31Value'],
                $accountData['USD']['dec31Value'],
                '€',
                '$'
            ),
            'dec31PositionsValue' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['dec31PositionsValue'],
                $accountData['USD']['dec31PositionsValue'],
                '€',
                '$'
            ),
            'dec31CashValue' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['dec31CashValue'],
                $accountData['USD']['dec31CashValue'],
                '€',
                '$'
            ),

            // Position details with both currencies
            'jan1PositionDetails' => $this->_positionTransformer->transformPositions(
                $accountData['EUR']['jan1PositionDetails'] ?? [],
                $accountData['USD']['jan1PositionDetails'] ?? []
            ),
            'dec31PositionDetails' => $this->_positionTransformer->transformPositions(
                $accountData['EUR']['dec31PositionDetails'] ?? [],
                $accountData['USD']['dec31PositionDetails'] ?? []
            ),

            // Transaction data with pre-calculated values
            'deposits' => $this->_transactionTransformer->transformDeposits(
                $accountData['EUR']['deposits'] ?? [],
                $accountData['USD']['deposits'] ?? []
            ),
            'withdrawals' => $this->_transactionTransformer->transformWithdrawals(
                $accountData['EUR']['withdrawals'] ?? [],
                $accountData['USD']['withdrawals'] ?? []
            ),
            'purchases' => $this->_tradeTransformer->transformTrades(
                $accountData['EUR']['purchases'] ?? [],
                $accountData['USD']['purchases'] ?? [],
                $accountData['EUR']['excludedTrades'] ?? [],
                $accountData['USD']['excludedTrades'] ?? [],
                $accountData['EUR']['totalPurchasesExcludedFees'] ?? 0,
                $accountData['USD']['totalPurchasesExcludedFees'] ?? 0,
                'purchase',
                $accountData['EUR']['totalPurchasesNet'] ?? 0,
                $accountData['USD']['totalPurchasesNet'] ?? 0
            ),
            'sales' => $this->_tradeTransformer->transformTrades(
                $accountData['EUR']['sales'] ?? [],
                $accountData['USD']['sales'] ?? [],
                $accountData['EUR']['excludedTrades'] ?? [],
                $accountData['USD']['excludedTrades'] ?? [],
                $accountData['EUR']['totalSalesExcludedFees'] ?? 0,
                $accountData['USD']['totalSalesExcludedFees'] ?? 0,
                'sale',
                $accountData['EUR']['totalSalesNet'] ?? 0,
                $accountData['USD']['totalSalesNet'] ?? 0
            ),
            'dividends' => $this->_transactionTransformer->transformDividends(
                $accountData['EUR']['dividends'] ?? [],
                $accountData['USD']['dividends'] ?? [],
                $accountData['EUR']['totalGrossDividends'] ?? 0,
                $accountData['USD']['totalGrossDividends'] ?? 0,
                $accountData['EUR']['totalGrossDividendsCalculated'] ?? null,
                $accountData['USD']['totalGrossDividendsCalculated'] ?? null,
                $accountData['EUR']['totalGrossDividendsOverride'] ?? null,
                $accountData['USD']['totalGrossDividendsOverride'] ?? null
            ),

            // Totals with both currencies
            'totalDeposits' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['totalDeposits'] ?? 0,
                $accountData['USD']['totalDeposits'] ?? 0,
                '€',
                '$'
            ),
            'totalWithdrawals' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['totalWithdrawals'] ?? 0,
                $accountData['USD']['totalWithdrawals'] ?? 0,
                '€',
                '$'
            ),
            'totalWithdrawalsOverride' => $this->_transformWithdrawalsOverride(
                $accountData['EUR'] ?? [],
                $accountData['USD'] ?? []
            ),
            'totalPurchases' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['totalPurchases'] ?? 0,
                $accountData['USD']['totalPurchases'] ?? 0,
                '€',
                '$'
            ),
            'totalSales' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['totalSales'] ?? 0,
                $accountData['USD']['totalSales'] ?? 0,
                '€',
                '$'
            ),
            'totalPurchasesNet' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['totalPurchasesNet'] ?? 0,
                $accountData['USD']['totalPurchasesNet'] ?? 0,
                '€',
                '$'
            ),
            'totalSalesNet' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['totalSalesNet'] ?? 0,
                $accountData['USD']['totalSalesNet'] ?? 0,
                '€',
                '$'
            ),
            'totalGrossDividends' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['totalGrossDividends'] ?? 0,
                $accountData['USD']['totalGrossDividends'] ?? 0,
                '€',
                '$'
            ),
            'actualReturn' => $this->_valueTransformer->transformValue(
                $accountData['EUR']['actualReturn'] ?? 0,
                $accountData['USD']['actualReturn'] ?? 0,
                '€',
                '$'
            ),
            'actualReturnColored' => $this->_valueTransformer->transformValueColored(
                $accountData['EUR']['actualReturn'] ?? 0,
                $accountData['USD']['actualReturn'] ?? 0,
                '€',
                '$'
            ),
            'actualReturnOverride' => $this->_transformActualReturnOverride(
                $accountData['EUR'] ?? [],
                $accountData['USD'] ?? []
            ),

            // Exclude original data to keep response lean
            'excludedTrades' => $accountData['EUR']['excludedTrades'] ?? [],
            'dividendsSummaryByTransactionCurrency' =>
                $accountData['EUR']['dividendsSummaryByTransactionCurrency'] ?? null,
        ];
    }

    private function _transformActualReturnOverride(array $eurData, array $usdData): array
    {
        $result = [
            'EUR' => null,
            'USD' => null,
            'reason' => null,
        ];

        // Get the reason (same for both currencies)
        $reason = $eurData['actualReturnOverrideReason'] ?? $usdData['actualReturnOverrideReason'] ?? null;
        if ($reason !== null) {
            $result['reason'] = $reason;
        }

        // Check if there's an override for EUR
        if (isset($eurData['actualReturnOverride']) && isset($eurData['actualReturnCalculated'])) {
            $result['EUR'] = [
                'override' => $eurData['actualReturnOverride'],
                'overrideFormatted' => MoneyFormat::get_formatted_gain('€', $eurData['actualReturnOverride']),
                'calculated' => $eurData['actualReturnCalculated'],
                'calculatedFormatted' => MoneyFormat::get_formatted_gain('€', $eurData['actualReturnCalculated']),
            ];
        }

        // Check if there's an override for USD
        if (isset($usdData['actualReturnOverride']) && isset($usdData['actualReturnCalculated'])) {
            $result['USD'] = [
                'override' => $usdData['actualReturnOverride'],
                'overrideFormatted' => MoneyFormat::get_formatted_gain('$', $usdData['actualReturnOverride']),
                'calculated' => $usdData['actualReturnCalculated'],
                'calculatedFormatted' => MoneyFormat::get_formatted_gain('$', $usdData['actualReturnCalculated']),
            ];
        }

        return $result;
    }

    /**
     * Transform withdrawals override data for both currencies
     */
    private function _transformWithdrawalsOverride(array $eurData, array $usdData): array
    {
        $result = [
            'EUR' => null,
            'USD' => null,
            'reason' => null,
        ];

        // Check if there's an override for EUR
        $eurOverride = $eurData['totalWithdrawalsOverride'] ?? null;
        if ($eurOverride !== null && isset($eurData['totalWithdrawalsCalculated'])) {
            $overrideValue = is_array($eurOverride) ? ($eurOverride['EUR'] ?? null) : null;
            $reason = is_array($eurOverride) ? ($eurOverride['reason'] ?? null) : null;

            if ($overrideValue !== null) {
                $result['EUR'] = [
                    'override' => $overrideValue,
                    'overrideFormatted' => MoneyFormat::get_formatted_balance('€', $overrideValue),
                    'calculated' => $eurData['totalWithdrawalsCalculated'],
                    'calculatedFormatted' => MoneyFormat::get_formatted_balance(
                        '€',
                        $eurData['totalWithdrawalsCalculated']
                    ),
                ];
                $result['reason'] = $reason;
            }
        }

        // Check if there's an override for USD
        $usdOverride = $usdData['totalWithdrawalsOverride'] ?? null;
        if ($usdOverride !== null && isset($usdData['totalWithdrawalsCalculated'])) {
            $overrideValue = is_array($usdOverride) ? ($usdOverride['USD'] ?? null) : null;
            $reason = is_array($usdOverride) ? ($usdOverride['reason'] ?? null) : null;

            if ($overrideValue !== null) {
                $result['USD'] = [
                    'override' => $overrideValue,
                    'overrideFormatted' => MoneyFormat::get_formatted_balance('$', $overrideValue),
                    'calculated' => $usdData['totalWithdrawalsCalculated'],
                    'calculatedFormatted' => MoneyFormat::get_formatted_balance(
                        '$',
                        $usdData['totalWithdrawalsCalculated']
                    ),
                ];
                if ($result['reason'] === null) {
                    $result['reason'] = $reason;
                }
            }
        }

        return $result;
    }
}

