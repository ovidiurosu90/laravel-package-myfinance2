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
 */
class ReturnsViewTransformer
{
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

            $transformed[$accountId] = $this->transformAccount($accountData, $year);
        }

        return $transformed;
    }

    /**
     * Transform a single account's data
     */
    private function transformAccount(array $accountData, int $year): array
    {
        return [
            'account' => $accountData['account'],
            'baseCurrency' => $accountData['baseCurrency'] ?? 'EUR',

            // Portfolio values with both currencies pre-formatted
            'jan1Value' => $this->transformValue(
                $accountData['EUR']['jan1Value'],
                $accountData['USD']['jan1Value'],
                '€',
                '$'
            ),
            'jan1PositionsValue' => $this->transformValue(
                $accountData['EUR']['jan1PositionsValue'],
                $accountData['USD']['jan1PositionsValue'],
                '€',
                '$'
            ),
            'jan1CashValue' => $this->transformValue(
                $accountData['EUR']['jan1CashValue'],
                $accountData['USD']['jan1CashValue'],
                '€',
                '$'
            ),
            'dec31Value' => $this->transformValue(
                $accountData['EUR']['dec31Value'],
                $accountData['USD']['dec31Value'],
                '€',
                '$'
            ),
            'dec31PositionsValue' => $this->transformValue(
                $accountData['EUR']['dec31PositionsValue'],
                $accountData['USD']['dec31PositionsValue'],
                '€',
                '$'
            ),
            'dec31CashValue' => $this->transformValue(
                $accountData['EUR']['dec31CashValue'],
                $accountData['USD']['dec31CashValue'],
                '€',
                '$'
            ),

            // Position details with both currencies
            'jan1PositionDetails' => $this->transformPositions(
                $accountData['EUR']['jan1PositionDetails'] ?? [],
                $accountData['USD']['jan1PositionDetails'] ?? []
            ),
            'dec31PositionDetails' => $this->transformPositions(
                $accountData['EUR']['dec31PositionDetails'] ?? [],
                $accountData['USD']['dec31PositionDetails'] ?? []
            ),

            // Transaction data with pre-calculated values
            'deposits' => $this->transformDeposits(
                $accountData['EUR']['deposits'] ?? [],
                $accountData['USD']['deposits'] ?? []
            ),
            'withdrawals' => $this->transformWithdrawals(
                $accountData['EUR']['withdrawals'] ?? [],
                $accountData['USD']['withdrawals'] ?? []
            ),
            'purchases' => $this->transformTrades(
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
            'sales' => $this->transformTrades(
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
            'dividends' => $this->transformDividends(
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
            'totalDeposits' => $this->transformValue(
                $accountData['EUR']['totalDeposits'] ?? 0,
                $accountData['USD']['totalDeposits'] ?? 0,
                '€',
                '$'
            ),
            'totalWithdrawals' => $this->transformValue(
                $accountData['EUR']['totalWithdrawals'] ?? 0,
                $accountData['USD']['totalWithdrawals'] ?? 0,
                '€',
                '$'
            ),
            'totalPurchases' => $this->transformValue(
                $accountData['EUR']['totalPurchases'] ?? 0,
                $accountData['USD']['totalPurchases'] ?? 0,
                '€',
                '$'
            ),
            'totalSales' => $this->transformValue(
                $accountData['EUR']['totalSales'] ?? 0,
                $accountData['USD']['totalSales'] ?? 0,
                '€',
                '$'
            ),
            'totalPurchasesNet' => $this->transformValue(
                $accountData['EUR']['totalPurchasesNet'] ?? 0,
                $accountData['USD']['totalPurchasesNet'] ?? 0,
                '€',
                '$'
            ),
            'totalSalesNet' => $this->transformValue(
                $accountData['EUR']['totalSalesNet'] ?? 0,
                $accountData['USD']['totalSalesNet'] ?? 0,
                '€',
                '$'
            ),
            'totalGrossDividends' => $this->transformValue(
                $accountData['EUR']['totalGrossDividends'] ?? 0,
                $accountData['USD']['totalGrossDividends'] ?? 0,
                '€',
                '$'
            ),
            'actualReturn' => $this->transformValue(
                $accountData['EUR']['actualReturn'] ?? 0,
                $accountData['USD']['actualReturn'] ?? 0,
                '€',
                '$'
            ),
            'actualReturnColored' => $this->transformValueColored(
                $accountData['EUR']['actualReturn'] ?? 0,
                $accountData['USD']['actualReturn'] ?? 0,
                '€',
                '$'
            ),
            'actualReturnOverride' => $this->transformActualReturnOverride(
                $accountData['EUR'] ?? [],
                $accountData['USD'] ?? []
            ),

            // Exclude original data to keep response lean
            'excludedTrades' => $accountData['EUR']['excludedTrades'] ?? [],
            'dividendsSummaryByTransactionCurrency' =>
                $accountData['EUR']['dividendsSummaryByTransactionCurrency'] ?? null,
        ];
    }

    /**
     * Transform a value for both currencies
     */
    private function transformValue(float $eurValue, float $usdValue, string $eurSymbol,
        string $usdSymbol): array
    {
        return [
            'EUR' => [
                'value' => $eurValue,
                'formatted' => MoneyFormat::get_formatted_balance($eurSymbol, $eurValue),
                'plain' => number_format(abs($eurValue), 2) . ' ' . $eurSymbol,
            ],
            'USD' => [
                'value' => $usdValue,
                'formatted' => MoneyFormat::get_formatted_balance($usdSymbol, $usdValue),
                'plain' => number_format(abs($usdValue), 2) . ' ' . $usdSymbol,
            ],
        ];
    }

    /**
     * Transform a value for both currencies with color formatting (red/green for negative/positive)
     */
    private function transformValueColored(float $eurValue, float $usdValue, string $eurSymbol,
        string $usdSymbol): array
    {
        return [
            'EUR' => [
                'value' => $eurValue,
                'formatted' => MoneyFormat::get_formatted_gain($eurSymbol, $eurValue),
            ],
            'USD' => [
                'value' => $usdValue,
                'formatted' => MoneyFormat::get_formatted_gain($usdSymbol, $usdValue),
            ],
        ];
    }

    /**
     * Transform position details with both currencies
     */
    private function transformPositions(array $eurPositions, array $usdPositions): array
    {
        $positions = [];

        foreach ($eurPositions as $eurPos) {
            $symbol = $eurPos['symbol'] ?? '';
            $usdPos = collect($usdPositions)->firstWhere('symbol', $symbol);

            $positions[] = [
                'symbol' => $symbol,
                'quantity' => $eurPos['quantity'] ?? $eurPos['quantityFormatted'] ?? '',
                'quantityFormatted' => $eurPos['quantityFormatted'] ?? '',
                'price' => $eurPos['price'] ?? 0,
                'priceFormatted' => $eurPos['priceFormatted'] ?? '',
                'tradeCurrencyDisplayCode' => $eurPos['tradeCurrencyDisplayCode'] ?? '',
                'localMarketValue' => $eurPos['localMarketValue'] ?? 0,
                'localMarketValueFormatted' => $eurPos['localMarketValueFormatted'] ?? '',
                'priceOverridden' => $eurPos['priceOverridden'] ?? false,
                'apiPrice' => $eurPos['apiPrice'] ?? null,
                'apiPriceFormatted' => $eurPos['apiPriceFormatted'] ?? '',
                'configPrice' => $eurPos['configPrice'] ?? null,
                'configPriceFormatted' => $eurPos['configPriceFormatted'] ?? '',
                'priceDiffPercentage' => $this->calculatePriceDiff($eurPos),
                'exchangeRate' => $eurPos['exchangeRate'] ?? 1,
                'exchangeRateClean' => $eurPos['exchangeRateClean'] ?? '',
                'exchangeRateOverridden' => $eurPos['exchangeRateOverridden'] ?? false,
                'apiExchangeRate' => $eurPos['apiExchangeRate'] ?? null,
                'apiExchangeRateFormatted' => $eurPos['apiExchangeRateFormatted'] ?? '',
                'exchangeRateFormatted' => $eurPos['exchangeRateFormatted'] ?? '',
                'fxDiffPercentage' => $this->calculateFxDiff($eurPos),
                'EUR' => [
                    'marketValue' => $eurPos['marketValue'] ?? 0,
                    'marketValueFormatted' => $eurPos['marketValueFormatted'] ?? '',
                    'eurusdRate' => $eurPos['eurusdRate'] ?? '',
                    'conversionPair' => $eurPos['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurPos['conversionExchangeRateClean'] ?? '',
                ],
                'USD' => [
                    'marketValue' => $usdPos['marketValue'] ?? $eurPos['marketValue'] ?? 0,
                    'marketValueFormatted' => $usdPos['marketValueFormatted']
                        ?? $eurPos['marketValueFormatted'] ?? '',
                    'eurusdRate' => $usdPos['eurusdRate'] ?? '',
                    'conversionPair' => $usdPos['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdPos['conversionExchangeRateClean'] ?? '',
                ],
            ];
        }

        return $positions;
    }

    /**
     * Transform deposits with both currencies
     */
    private function transformDeposits(array $eurDeposits, array $usdDeposits): array
    {
        $deposits = [];
        $totalEUR = 0;
        $totalUSD = 0;

        foreach ($eurDeposits as $eurDep) {
            $date = $eurDep['date'] ?? '';
            $usdDep = collect($usdDeposits)->firstWhere('date', $date);

            $deposits[] = [
                'date' => $date,
                'description' => $eurDep['description'] ?? '',
                'EUR' => [
                    'amount' => $eurDep['amount'] ?? 0,
                    'formatted' => $eurDep['formatted'] ?? '',
                    'eurusdRate' => $eurDep['eurusdRate'] ?? '',
                    'conversionPair' => $eurDep['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurDep['conversionExchangeRateClean'] ?? '',
                ],
                'USD' => [
                    'amount' => $usdDep['amount'] ?? $eurDep['amount'] ?? 0,
                    'formatted' => $usdDep['formatted'] ?? $eurDep['formatted'] ?? '',
                    'eurusdRate' => $usdDep['eurusdRate'] ?? '',
                    'conversionPair' => $usdDep['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdDep['conversionExchangeRateClean'] ?? '',
                ],
            ];

            $totalEUR += $eurDep['amount'] ?? 0;
            $totalUSD += $usdDep['amount'] ?? $eurDep['amount'] ?? 0;
        }

        return [
            'items' => $deposits,
            'totals' => [
                'EUR' => [
                    'amount' => $totalEUR,
                    'formatted' => MoneyFormat::get_formatted_balance('€', $totalEUR),
                ],
                'USD' => [
                    'amount' => $totalUSD,
                    'formatted' => MoneyFormat::get_formatted_balance('$', $totalUSD),
                ],
            ],
        ];
    }

    /**
     * Transform withdrawals
     */
    private function transformWithdrawals(array $eurWithdrawals, array $usdWithdrawals): array
    {
        // Same structure as deposits
        return $this->transformDeposits($eurWithdrawals, $usdWithdrawals);
    }

    /**
     * Transform trades (purchases or sales) with fees text pre-calculated
     *
     * @param array $eurTrades EUR currency trades
     * @param array $usdTrades USD currency trades
     * @param array $eurExcluded EUR excluded trades
     * @param array $usdExcluded USD excluded trades
     * @param float $eurExcludedFees EUR excluded fees amount
     * @param float $usdExcludedFees USD excluded fees amount
     * @param string $type Trade type: 'purchase' or 'sale'
     * @param float $eurTotalNet EUR net total (with fees)
     * @param float $usdTotalNet USD net total (with fees)
     * @return array Transformed trades with items and totals
     */
    private function transformTrades(
        array $eurTrades,
        array $usdTrades,
        array $eurExcluded,
        array $usdExcluded,
        float $eurExcludedFees = 0,
        float $usdExcludedFees = 0,
        string $type = 'purchase',
        float $eurTotalNet = 0,
        float $usdTotalNet = 0
    ): array {
        $trades = [];
        $totalEURPrincipal = 0;
        $totalUSDPrincipal = 0;
        $totalEURFees = 0;
        $totalUSDFees = 0;

        foreach ($eurTrades as $eurTrade) {
            $id = $eurTrade['id'] ?? null;
            $usdTrade = collect($usdTrades)->firstWhere('id', $id);

            $eurFee = $eurTrade['fee'] ?? 0;
            $usdFee = $usdTrade['fee'] ?? $eurTrade['fee'] ?? 0;

            // Determine account currency and use the corresponding fee value
            $accountCurrencyCode = $eurTrade['accountCurrencyCode'] ?? '€';
            $accountCurrencyIsoCode = $eurTrade['accountCurrencyIsoCode'] ?? 'EUR';
            $accountCurrencyFee = ($accountCurrencyIsoCode === 'USD' && $usdTrade)
                ? ($usdTrade['fee'] ?? 0)
                : ($eurTrade['fee'] ?? 0);

            // For sales, fees are shown with a minus sign (they reduce proceeds)
            // For purchases, fees are shown without sign
            $accountCurrencyFeeFormatted = $accountCurrencyFee > 0
                ? ($type === 'sale' ? '- ' : '') . MoneyFormat::get_formatted_balance($accountCurrencyCode, $accountCurrencyFee)
                : '';

            $eurPrincipal = $eurTrade['principal_amount'] ?? 0;
            $usdPrincipal = $usdTrade['principal_amount'] ?? $eurTrade['principal_amount'] ?? 0;

            $trades[] = [
                'id' => $id,
                'date' => $eurTrade['date'] ?? '',
                'symbol' => $eurTrade['symbol'] ?? '',
                'quantityFormatted' => $eurTrade['quantityFormatted'] ?? '',
                'unitPriceFormatted' => $eurTrade['unitPriceFormatted'] ?? '',
                'accountCurrencyFee' => $accountCurrencyFee,
                'accountCurrencyFeeFormatted' => $accountCurrencyFeeFormatted,
                'EUR' => [
                    'principalAmount' => $eurPrincipal,
                    'principalAmountFormatted' => $eurTrade['principalAmountFormatted'] ?? '',
                    'fee' => $eurFee,
                    'feeFormatted' => $eurFee > 0
                        ? MoneyFormat::get_formatted_balance('€', $eurFee)
                        : '',
                    'eurusdRate' => $eurTrade['eurusdRate'] ?? '',
                    'conversionPair' => $eurTrade['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurTrade['conversionExchangeRateClean'] ?? '',
                    'showMissingRateWarning' => $eurTrade['showMissingRateWarning'] ?? false,
                ],
                'USD' => [
                    'principalAmount' => $usdPrincipal,
                    'principalAmountFormatted' => $usdTrade['principalAmountFormatted']
                        ?? $eurTrade['principalAmountFormatted'] ?? '',
                    'fee' => $usdFee,
                    'feeFormatted' => $usdFee > 0
                        ? MoneyFormat::get_formatted_balance('$', $usdFee)
                        : '',
                    'eurusdRate' => $usdTrade['eurusdRate'] ?? '',
                    'conversionPair' => $usdTrade['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdTrade['conversionExchangeRateClean'] ?? '',
                    'showMissingRateWarning' => $usdTrade['showMissingRateWarning'] ?? false,
                ],
            ];

            $totalEURPrincipal += $eurPrincipal;
            $totalUSDPrincipal += $usdPrincipal;
            $totalEURFees += $eurFee;
            $totalUSDFees += $usdFee;
        }

        return [
            'items' => $trades,
            'totals' => [
                'EUR' => [
                    'principalAmount' => $eurTotalNet,
                    'principalAmountFormatted' => MoneyFormat::get_formatted_balance('€', $eurTotalNet),
                    'principalAmountGross' => $totalEURPrincipal,
                    'principalAmountGrossFormatted' => MoneyFormat::get_formatted_balance('€', $totalEURPrincipal),
                    'fees' => $totalEURFees,
                    'feesFormatted' => MoneyFormat::get_formatted_balance('€', $totalEURFees),
                    'feesText' => $this->buildFeesText('€', $totalEURFees, $eurExcludedFees, $type),
                    'excludedFees' => $eurExcludedFees,
                    'excludedFeesFormatted' => MoneyFormat::get_formatted_balance('€', abs($eurExcludedFees)),
                ],
                'USD' => [
                    'principalAmount' => $usdTotalNet,
                    'principalAmountFormatted' => MoneyFormat::get_formatted_balance('$', $usdTotalNet),
                    'principalAmountGross' => $totalUSDPrincipal,
                    'principalAmountGrossFormatted' => MoneyFormat::get_formatted_balance('$', $totalUSDPrincipal),
                    'fees' => $totalUSDFees,
                    'feesFormatted' => MoneyFormat::get_formatted_balance('$', $totalUSDFees),
                    'feesText' => $this->buildFeesText('$', $totalUSDFees, $usdExcludedFees, $type),
                    'excludedFees' => $usdExcludedFees,
                    'excludedFeesFormatted' => MoneyFormat::get_formatted_balance('$', abs($usdExcludedFees)),
                ],
            ],
        ];
    }

    /**
     * Transform dividends with both currencies
     *
     * Uses pre-calculated totals from the service to preserve override information
     */
    private function transformDividends(array $eurDividends, array $usdDividends,
        float $totalEUR, float $totalUSD, ?float $calculatedEUR = null,
        ?float $calculatedUSD = null, $eurOverride = null, $usdOverride = null): array
    {
        $dividends = [];

        foreach ($eurDividends as $eurDiv) {
            $symbol = $eurDiv['symbol'] ?? '';
            $date = $eurDiv['date'] ?? '';
            $usdDiv = collect($usdDividends)
                ->where('symbol', $symbol)
                ->where('date', $date)
                ->first();

            $eurAmount = $eurDiv['amount'] ?? 0;
            $usdAmount = $usdDiv['amount'] ?? $eurDiv['amount'] ?? 0;

            $dividends[] = [
                'date' => $date,
                'symbol' => $symbol,
                'description' => $eurDiv['description'] ?? '',
                'fee' => $eurDiv['fee'] ?? 0,
                'feeFormatted' => $eurDiv['feeFormatted'] ?? '',
                'EUR' => [
                    'amount' => $eurAmount,
                    'formatted' => $eurDiv['formatted'] ?? '',
                    'eurusdRate' => $eurDiv['eurusdRate'] ?? '',
                    'conversionPair' => $eurDiv['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurDiv['conversionExchangeRateClean'] ?? '',
                    'showMissingRateWarning' => $eurDiv['showMissingRateWarning'] ?? false,
                ],
                'USD' => [
                    'amount' => $usdAmount,
                    'formatted' => $usdDiv['formatted'] ?? $eurDiv['formatted'] ?? '',
                    'eurusdRate' => $usdDiv['eurusdRate'] ?? '',
                    'conversionPair' => $usdDiv['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdDiv['conversionExchangeRateClean'] ?? '',
                    'showMissingRateWarning' => $usdDiv['showMissingRateWarning'] ?? false,
                ],
            ];
        }

        // Build totals using pre-calculated values from service
        $eurTotal = [
            'amount' => $totalEUR,
            'formatted' => MoneyFormat::get_formatted_balance('€', $totalEUR),
        ];

        // Add calculated value only if there's an actual override in the database
        if ($eurOverride !== null && $calculatedEUR !== null) {
            $eurTotal['calculatedAmount'] = $calculatedEUR;
            $eurTotal['calculatedFormatted'] = MoneyFormat::get_formatted_balance('€', $calculatedEUR);
        }

        $usdTotal = [
            'amount' => $totalUSD,
            'formatted' => MoneyFormat::get_formatted_balance('$', $totalUSD),
        ];

        // Add calculated value only if there's an actual override in the database
        if ($usdOverride !== null && $calculatedUSD !== null) {
            $usdTotal['calculatedAmount'] = $calculatedUSD;
            $usdTotal['calculatedFormatted'] = MoneyFormat::get_formatted_balance('$', $calculatedUSD);
        }

        return [
            'items' => $dividends,
            'totals' => [
                'EUR' => $eurTotal,
                'USD' => $usdTotal,
            ],
        ];
    }

    /**
     * Build fees text
     */
    private function buildFeesText(string $symbol, float $fees, float $excludedFees,
        string $type = 'purchase'): string
    {
        // For purchases: fees are shown as positive (they reduce cost basis)
        // For sales: fees are shown as negative (they reduce proceeds)
        $feesSign = ($type === 'purchase') ? '' : '-';
        $feesFormatted = MoneyFormat::get_formatted_balance($symbol, abs($fees));
        $text = "(including {$feesSign}{$feesFormatted} in fees";

        if (abs($excludedFees) > 0.001) {
            $excludedFormatted = MoneyFormat::get_formatted_balance($symbol, abs($excludedFees));
            // For purchases: always show as negative (reducing cost basis)
            // For sales: always show as positive (adding back to proceeds)
            $sign = ($type === 'purchase') ? '-' : '+';
            $text .= ", excluding {$sign}{$excludedFormatted} due to config";
        }

        $text .= ")";
        return $text;
    }

    /**
     * Calculate price difference percentage
     */
    private function calculatePriceDiff(array $position): float
    {
        if (empty($position['apiPrice']) || empty($position['price'])) {
            return 0;
        }

        return abs($position['price'] - $position['apiPrice']) / $position['apiPrice'] * 100;
    }

    /**
     * Calculate FX difference percentage
     */
    private function calculateFxDiff(array $position): float
    {
        if (empty($position['apiExchangeRate']) || empty($position['exchangeRate'])) {
            return 0;
        }

        return abs($position['exchangeRate'] - $position['apiExchangeRate'])
            / $position['apiExchangeRate'] * 100;
    }

    /**
     * Transform actualReturn override data for both currencies
     */
    private function transformActualReturnOverride(array $eurData, array $usdData): array
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
}

