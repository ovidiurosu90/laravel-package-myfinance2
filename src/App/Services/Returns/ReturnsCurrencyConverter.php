<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Currency Converter Service
 *
 * Handles multi-currency conversion of returns data.
 * Converts portfolio values, transactions, and positions to different currencies
 * using historical exchange rates.
 */
class ReturnsCurrencyConverter
{
    private ReturnsQuoteProvider $_quoteProvider;
    private ReturnsCurrencyFormatter $_formatter;

    public function __construct(
        ReturnsQuoteProvider $quoteProvider = null,
        ReturnsCurrencyFormatter $formatter = null
    )
    {
        $this->_quoteProvider = $quoteProvider ?? new ReturnsQuoteProvider();
        $this->_formatter = $formatter ?? new ReturnsCurrencyFormatter();
    }

    /**
     * Convert returns data to multiple currencies in a single pass (optimized version)
     *
     * @param int $accountId The account ID
     * @param array $returnsData The returns data to convert
     * @param array $targetCurrencies Target currencies (e.g., ['EUR', 'USD'])
     * @param int|null $year The year for fee exclusions
     * @param array $preloadedCurrencies Pre-fetched currency models keyed by iso_code (optional)
     */
    public function convertReturnsToCurrencies(
        int $accountId,
        array $returnsData,
        array $targetCurrencies,
        int $year = null,
        array $preloadedCurrencies = []
    ): array {
        $baseCurrency = $returnsData['baseCurrency'];
        $jan1 = $returnsData['jan1Date'] ?? null;
        $dec31 = $returnsData['dec31Date'] ?? null;

        if (empty($jan1) || empty($dec31)) {
            return array_fill_keys($targetCurrencies, $returnsData);
        }

        // Load fee exclusions from config
        $feesExclusions = $this->_getFeeExclusions($accountId, $year);

        // Pre-fetch all exchange rates and currency models
        $exchangeRates = $this->_prefetchExchangeRates(
            $accountId,
            $baseCurrency,
            $targetCurrencies,
            $jan1,
            $dec31
        );
        $eurusdRates = $this->_getEurusdRates($accountId, $jan1, $dec31);
        $currencyModels = $this->_getCurrencyModels($targetCurrencies, $preloadedCurrencies);

        $result = [];
        foreach ($targetCurrencies as $targetCurrency) {
            $result[$targetCurrency] = $this->_convertToSingleCurrency(
                $accountId,
                $returnsData,
                $targetCurrency,
                $baseCurrency,
                $exchangeRates,
                $eurusdRates,
                $currencyModels,
                $feesExclusions
            );
        }

        return $result;
    }

    /**
     * Get fee exclusions from config for a specific account and year
     */
    private function _getFeeExclusions(int $accountId, ?int $year): array
    {
        if ($year === null) {
            return [];
        }

        $config = config('trades.fees_exclusions', []);

        // Check for account-specific overrides first, then fall back to global
        $byAccount = $config['by_account'] ?? [];
        if (isset($byAccount[$accountId][$year])) {
            return $byAccount[$accountId][$year];
        }

        $global = $config['global'] ?? [];
        if (isset($global[$year])) {
            return $global[$year];
        }

        return [];
    }

    /**
     * Prefetch exchange rates for all target currencies
     */
    private function _prefetchExchangeRates(
        int $accountId,
        string $baseCurrency,
        array $targetCurrencies,
        \DateTimeInterface $jan1,
        \DateTimeInterface $dec31
    ): array
    {
        $exchangeRates = [];
        foreach ($targetCurrencies as $targetCurrency) {
            $jan1Rate = $this->_quoteProvider->getExchangeRate(
                $accountId,
                $baseCurrency,
                $targetCurrency,
                $jan1
            );
            $dec31Rate = $this->_quoteProvider->getExchangeRate(
                $accountId,
                $baseCurrency,
                $targetCurrency,
                $dec31
            );

            $exchangeRates[$targetCurrency] = [
                'jan1' => $jan1Rate,
                'dec31' => $dec31Rate,
            ];
        }
        return $exchangeRates;
    }

    /**
     * Get EURUSD rates for both dates
     */
    private function _getEurusdRates(
        int $accountId,
        \DateTimeInterface $jan1,
        \DateTimeInterface $dec31
    ): array
    {
        $jan1Rate = $this->_quoteProvider->getExchangeRate($accountId, 'EUR', 'USD', $jan1);
        $dec31Rate = $this->_quoteProvider->getExchangeRate($accountId, 'EUR', 'USD', $dec31);

        return [
            'jan1' => $this->_quoteProvider->formatCleanExchangeRate($jan1Rate),
            'dec31' => $this->_quoteProvider->formatCleanExchangeRate($dec31Rate),
        ];
    }

    /**
     * Get currency models for all target currencies
     * Uses pre-loaded currencies if available, otherwise queries database
     *
     * @param array $targetCurrencies Currency ISO codes to get models for
     * @param array $preloadedCurrencies Pre-loaded currency models keyed by iso_code (optional)
     */
    private function _getCurrencyModels(array $targetCurrencies, array $preloadedCurrencies = []): array
    {
        $currencyModels = [];

        // Check which currencies we still need to fetch
        $currenciesToFetch = [];
        foreach ($targetCurrencies as $targetCurrency) {
            if (isset($preloadedCurrencies[$targetCurrency])) {
                $currencyModels[$targetCurrency] = $preloadedCurrencies[$targetCurrency];
            } else {
                $currenciesToFetch[] = $targetCurrency;
            }
        }

        // Fetch any missing currencies from database
        if (!empty($currenciesToFetch)) {
            $currencies = Currency::whereIn('iso_code', $currenciesToFetch)->get();
            foreach ($currencies as $currency) {
                $currencyModels[$currency->iso_code] = $currency;
            }
        }

        // Ensure all requested currencies are in the result (even if not found)
        foreach ($targetCurrencies as $targetCurrency) {
            if (!isset($currencyModels[$targetCurrency])) {
                $currencyModels[$targetCurrency] = null;
            }
        }

        return $currencyModels;
    }

    /**
     * Convert returns data to a single target currency
     */
    private function _convertToSingleCurrency(
        int $accountId,
        array $returnsData,
        string $targetCurrency,
        string $baseCurrency,
        array $exchangeRates,
        array $eurusdRates,
        array $currencyModels,
        array $feesExclusions = []
    ): array
    {
        $jan1ExchangeRate = $exchangeRates[$targetCurrency]['jan1'];
        $dec31ExchangeRate = $exchangeRates[$targetCurrency]['dec31'];
        $currencyModel = $currencyModels[$targetCurrency];
        $displayCode = $currencyModel
            ? $currencyModel->display_code
            : ($targetCurrency === 'EUR' ? '€' : '$');
        $conversionPair = $baseCurrency . '->' . $targetCurrency;

        $converted = [
            'jan1Value' => $returnsData['jan1Value'] * $jan1ExchangeRate,
            'jan1PositionsValue' =>
                $returnsData['jan1PositionsValue'] * $jan1ExchangeRate,
            'jan1CashValue' => $returnsData['jan1CashValue'] * $jan1ExchangeRate,
            'dec31Value' => $returnsData['dec31Value'] * $dec31ExchangeRate,
            'dec31PositionsValue' =>
                $returnsData['dec31PositionsValue'] * $dec31ExchangeRate,
            'dec31CashValue' => $returnsData['dec31CashValue'] * $dec31ExchangeRate,
            'totalDeposits' => 0,
            'totalWithdrawals' => 0,
        ];

        // Convert positions and deposits/withdrawals
        $converted['jan1PositionDetails'] = $this->_convertPositionDetails(
            $returnsData['jan1PositionDetails'] ?? [],
            $jan1ExchangeRate,
            $eurusdRates['jan1'],
            $conversionPair,
            $displayCode
        );

        $converted['dec31PositionDetails'] = $this->_convertPositionDetails(
            $returnsData['dec31PositionDetails'] ?? [],
            $dec31ExchangeRate,
            $eurusdRates['dec31'],
            $conversionPair,
            $displayCode
        );

        $depositResult = $this->_convertDepositsOrWithdrawals(
            $accountId,
            'deposit',
            $returnsData['deposits'] ?? [],
            $baseCurrency,
            $targetCurrency,
            $conversionPair,
            $displayCode
        );
        $converted['deposits'] = $depositResult['items'];
        $converted['totalDepositsCalculated'] = $depositResult['total'];
        $converted['totalDepositsFees'] = $depositResult['totalFees'] ?? 0;

        // Check for currency-specific deposits override
        $depositsOverride = null;
        if (is_array($returnsData['totalDepositsOverride'] ?? null)) {
            $depositsOverride = $returnsData['totalDepositsOverride'][$targetCurrency] ?? null;
        }

        if ($depositsOverride !== null) {
            // Use the currency-specific override value
            $converted['totalDeposits'] = $depositsOverride;
            $converted['totalDepositsOverride'] = $returnsData['totalDepositsOverride'];
        } else {
            // Use calculated total
            $converted['totalDeposits'] = $depositResult['total'];
        }

        $withdrawalResult = $this->_convertDepositsOrWithdrawals(
            $accountId,
            'withdrawal',
            $returnsData['withdrawals'] ?? [],
            $baseCurrency,
            $targetCurrency,
            $conversionPair,
            $displayCode
        );
        $converted['withdrawals'] = $withdrawalResult['items'];
        $converted['totalWithdrawalsCalculated'] = $withdrawalResult['total'];
        $converted['totalWithdrawalsFees'] = $withdrawalResult['totalFees'] ?? 0;

        // Check for currency-specific withdrawals override
        $withdrawalsOverride = null;
        if (is_array($returnsData['totalWithdrawalsOverride'] ?? null)) {
            $withdrawalsOverride = $returnsData['totalWithdrawalsOverride'][$targetCurrency] ?? null;
        }

        if ($withdrawalsOverride !== null) {
            // Use the currency-specific override value
            $converted['totalWithdrawals'] = $withdrawalsOverride;
            $converted['totalWithdrawalsOverride'] = $returnsData['totalWithdrawalsOverride'];
        } else {
            // Use calculated total
            $converted['totalWithdrawals'] = $withdrawalResult['total'];
        }

        $dividendResult = $this->_convertDepositsOrWithdrawals(
            $accountId,
            'dividend',
            $returnsData['dividends'] ?? [],
            $baseCurrency,
            $targetCurrency,
            $conversionPair,
            $displayCode
        );
        $converted['dividends'] = $dividendResult['items'];
        $converted['totalGrossDividendsCalculated'] = $dividendResult['total'];

        // Check for currency-specific override (new format: array with currency keys)
        // Fall back to the overall override if no currency-specific override exists
        $currencyOverride = null;
        if (is_array($returnsData['totalGrossDividendsOverride'] ?? null)) {
            // New format: override is already per-currency
            $currencyOverride = $returnsData['totalGrossDividendsOverride'][$targetCurrency] ?? null;
        }

        if ($currencyOverride !== null) {
            // Use the currency-specific override value
            $converted['totalGrossDividends'] = $currencyOverride;
            $converted['totalGrossDividendsOverride'] = $currencyOverride;
        } else {
            // Use calculated total
            $converted['totalGrossDividends'] = $dividendResult['total'];
        }

        // Convert stock purchases
        $purchaseResult = $this->_convertDepositsOrWithdrawals(
            $accountId,
            'purchase',
            $returnsData['purchases'] ?? [],
            $baseCurrency,
            $targetCurrency,
            $conversionPair,
            $displayCode
        );
        $converted['purchases'] = $purchaseResult['items'];
        $converted['totalPurchases'] = $purchaseResult['total'];
        $converted['totalPurchasesFees'] = $purchaseResult['totalFees'] ?? 0;

        // Convert stock sales
        $saleResult = $this->_convertDepositsOrWithdrawals(
            $accountId,
            'sale',
            $returnsData['sales'] ?? [],
            $baseCurrency,
            $targetCurrency,
            $conversionPair,
            $displayCode
        );
        $converted['sales'] = $saleResult['items'];
        $converted['totalSales'] = $saleResult['total'];
        $converted['totalSalesFees'] = $saleResult['totalFees'] ?? 0;

        // Convert net purchase and sale totals (which include fees)
        // Use totalWithFees from converted items instead of pre-calculated values
        if (isset($returnsData['totalPurchasesNet'])) {
            $converted['totalPurchasesNet'] = $purchaseResult['totalWithFees'] ?? 0;
        }
        if (isset($returnsData['totalSalesNet'])) {
            $converted['totalSalesNet'] = $saleResult['totalWithFees'] ?? 0;
        }

        // Apply fee exclusions (e.g., indirect currency conversion fees)
        $purchasesExcludedFees = 0;
        $salesExcludedFees = 0;

        if (isset($feesExclusions[$targetCurrency])) {
            $exclusion = $feesExclusions[$targetCurrency];

            // For purchases: exclude fees by subtracting them from the net total
            if (isset($exclusion['purchases'])) {
                $purchasesExcludedFees = $exclusion['purchases'];
                $converted['totalPurchasesNet'] -= $purchasesExcludedFees;
            }

            // For sales: exclude fees by adding them back to the net total
            // (since we subtract fees from sales, excluding fees means adding them back)
            if (isset($exclusion['sales'])) {
                $salesExcludedFees = $exclusion['sales'];
                $converted['totalSalesNet'] += $salesExcludedFees;
            }
        }

        // Store excluded fees for display in drilldowns
        $converted['totalPurchasesExcludedFees'] = $purchasesExcludedFees;
        $converted['totalSalesExcludedFees'] = $salesExcludedFees;

        // Convert excluded trades for informational display
        $excludedResult = $this->_convertDepositsOrWithdrawals(
            $accountId,
            'excluded',
            $returnsData['excludedTrades'] ?? [],
            $baseCurrency,
            $targetCurrency,
            $conversionPair,
            $displayCode
        );
        $converted['excludedTrades'] = $excludedResult['items'];

        // Calculate return using the final gross dividends value (which includes override if available)
        // Return = Dividends + End value - Start value
        //          - (Deposits - Deposit Fees) + (Withdrawals + Withdrawal Fees)
        //          - Purchases (net, including fees) + Sales (net, including fees)
        $depositsWithFees = $converted['totalDeposits']
            - ($converted['totalDepositsFees'] ?? 0);
        $withdrawalsWithFees = $converted['totalWithdrawals']
            + ($converted['totalWithdrawalsFees'] ?? 0);
        $actualReturn = $converted['totalGrossDividends'] + $converted['dec31Value']
            - $converted['jan1Value'] - $depositsWithFees + $withdrawalsWithFees
            - ($converted['totalPurchasesNet'] ?? $converted['totalPurchases'])
            + ($converted['totalSalesNet'] ?? $converted['totalSales']);

        // Store the calculated return before applying override
        $converted['actualReturnCalculated'] = $actualReturn;

        // Apply returns override if available for this currency
        if (isset($returnsData['actualReturnOverride'][$targetCurrency])) {
            $actualReturn = $returnsData['actualReturnOverride'][$targetCurrency];
            $converted['actualReturnOverride'] = $actualReturn;
            $converted['actualReturnOverrideReason'] = $returnsData['actualReturnOverrideReason'] ?? null;
        }

        $converted['actualReturn'] = $actualReturn;
        $converted['actualReturnFormatted'] =
            MoneyFormat::get_formatted_gain($displayCode, $actualReturn);

        // Add formatted totals and return
        $this->_formatter->formatConvertedValues($converted, $displayCode);

        return $converted;
    }

    /**
     * Convert position details to target currency
     */
    private function _convertPositionDetails(
        array $positions,
        float $exchangeRate,
        string $eurusdRate,
        string $conversionPair,
        string $displayCode
    ): array
    {
        $converted = [];
        foreach ($positions as $position) {
            $convertedPosition = $position;
            $convertedPosition['marketValue'] =
                $position['marketValue'] * $exchangeRate;
            $convertedPosition['marketValueFormatted'] =
                MoneyFormat::get_formatted_balance(
                    $displayCode,
                    $convertedPosition['marketValue']
                );
            $convertedPosition['conversionExchangeRate'] = $exchangeRate;
            $convertedPosition['conversionExchangeRateClean'] =
                $this->_quoteProvider->formatCleanExchangeRate($exchangeRate);
            $convertedPosition['conversionPair'] = $conversionPair;
            $convertedPosition['eurusdRate'] = $eurusdRate;
            $converted[] = $convertedPosition;
        }
        return $converted;
    }

    /**
     * Convert deposits or withdrawals to target currency
     */
    private function _convertDepositsOrWithdrawals(
        int $accountId,
        string $type,
        array $items,
        string $baseCurrency,
        string $targetCurrency,
        string $conversionPair,
        string $displayCode
    ): array
    {
        $converted = [];
        $total = 0;
        $totalWithFees = 0;
        $totalFees = 0;

        // Pre-fetch all unique dates' exchange rates to avoid redundant lookups
        $uniqueDates = [];
        foreach ($items as $item) {
            $uniqueDates[$item['date']] = true;
        }

        $exchangeRateCache = [];
        foreach (array_keys($uniqueDates) as $dateKey) {
            $itemDate = new \DateTime($dateKey);
            $mainRate = $this->_quoteProvider->getExchangeRate(
                $accountId,
                $baseCurrency,
                $targetCurrency,
                $itemDate
            );
            $eurusdRate = $this->_quoteProvider->getExchangeRate(
                $accountId,
                'EUR',
                'USD',
                $itemDate
            );

            $exchangeRateCache[$dateKey] = [
                'main' => $mainRate,
                'eurusd' => $eurusdRate,
            ];
        }

        foreach ($items as $item) {
            $dateKey = $item['date'];
            $itemConversionPair = $conversionPair;
            $usedStoredRate = false;
            $showMissingRateWarning = false;

            // Handle dividends and trades specially (use stored exchange rate if available)
            if (!empty($item['dividendCurrencyIsoCode']) || !empty($item['tradeCurrencyIsoCode'])) {
                // Determine transaction currency (dividend or trade currency)
                $transactionCurrency =
                    $item['dividendCurrencyIsoCode'] ?? $item['tradeCurrencyIsoCode'];
                $storedRate = (float)$item['exchangeRate'];

                // For transaction currency, conversion pair is based on that currency
                $itemConversionPair = $transactionCurrency . '->' . $targetCurrency;

                // If transaction currency matches target currency, use rate 1
                if ($transactionCurrency === $targetCurrency) {
                    $exchangeRate = 1.0;
                    $eurusdRate = '1';
                    $usedStoredRate = false;
                    $showMissingRateWarning = false;
                } elseif (
                    $storedRate > 0
                    && !($storedRate == 1.0 && $transactionCurrency === $baseCurrency)
                ) {
                    // Use stored exchange rate to convert to target currency
                    // Divide by exchange rate (inverse, as stored in records)
                    $exchangeRate = 1.0 / $storedRate;
                    $eurusdRate = $this->_quoteProvider->formatCleanExchangeRate($storedRate);
                    $usedStoredRate = true;
                    $showMissingRateWarning = false;
                } else {
                    // No stored rate or rate is 1.0 - fetch from API
                    $exchangeRate = $exchangeRateCache[$dateKey]['main'];
                    $eurusdRate = $this->_quoteProvider->formatCleanExchangeRate(
                        $exchangeRateCache[$dateKey]['eurusd']
                    );
                    $usedStoredRate = false;
                    // Show warning only if actual conversion happened (rate != 1)
                    $showMissingRateWarning = $exchangeRate != 1.0;
                }
            } else {
                // For deposits/withdrawals, use fetched exchange rates
                $exchangeRate = $exchangeRateCache[$dateKey]['main'];
                $eurusdRate = $this->_quoteProvider->formatCleanExchangeRate(
                    $exchangeRateCache[$dateKey]['eurusd']
                );
            }

            $convertedItem = $item;
            // For purchases/sales, convert principal_amount; otherwise convert amount
            if (!empty($item['principal_amount'])) {
                $convertedItem['principal_amount'] = $item['principal_amount'] * $exchangeRate;
            } elseif (isset($item['amount'])) {
                $convertedItem['amount'] = $item['amount'] * $exchangeRate;
            }

            // Convert fee from account currency to target currency
            // Fee is always in account/base currency, so use direct base->target exchange rate
            $convertedFee = 0;
            if (!empty($item['fee'])) {
                // Get exchange rate from base currency to target currency for this date
                $baseToTargetRate = $exchangeRateCache[$dateKey]['main'];
                if ($baseCurrency !== $targetCurrency) {
                    $convertedFee = $item['fee'] * $baseToTargetRate;
                    $convertedItem['fee'] = $convertedFee;
                } else {
                    // No conversion needed if currencies match
                    $convertedFee = $item['fee'];
                    $convertedItem['fee'] = $convertedFee;
                }
            }

            $amountToFormat = $convertedItem['principal_amount'] ??
                $convertedItem['amount'] ?? 0;
            $convertedItem['formatted'] = MoneyFormat::get_formatted_balance(
                $displayCode,
                $amountToFormat
            );
            $convertedItem['eurusdRate'] = $eurusdRate;
            $convertedItem['conversionPair'] = $itemConversionPair;
            $convertedItem['conversionExchangeRateClean'] =
                $this->_quoteProvider->formatCleanExchangeRate($exchangeRate);
            $convertedItem['usedStoredRate'] = $usedStoredRate;
            $convertedItem['showMissingRateWarning'] = $showMissingRateWarning;

            $converted[] = $convertedItem;
            $total += $amountToFormat;
            $totalFees += $convertedFee;

            // For sales, fees reduce the proceeds (subtract); for purchases, fees increase cost (add)
            if ($type === 'sale') {
                $totalWithFees += $amountToFormat - $convertedFee;
            } else {
                $totalWithFees += $amountToFormat + $convertedFee;
            }
        }

        return [
            'items' => $converted,
            'total' => $total,
            'totalFees' => $totalFees,
            'totalWithFees' => $totalWithFees,
        ];
    }

}

