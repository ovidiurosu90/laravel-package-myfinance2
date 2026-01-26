<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Dividends Service
 *
 * Handles fetching, formatting, and summarizing dividends for returns calculations.
 */
class ReturnsDividends
{
    /**
     * Get dividends (income from dividends) for a year
     *
     * @param int $accountId The account ID
     * @param int $year The year to get dividends for
     * @param Account|null $preloadedAccount Pre-loaded account object (optional, avoids redundant query)
     */
    public function getDividends(int $accountId, int $year, ?Account $preloadedAccount = null): array
    {
        $startDate = "$year-01-01 00:00:00";
        $endDate = "$year-12-31 23:59:59";

        // Only eager load accountModel if we don't have a pre-loaded account
        $eagerLoad = $preloadedAccount !== null
            ? ['dividendCurrencyModel']
            : ['accountModel', 'dividendCurrencyModel'];

        $dividends = Dividend::with($eagerLoad)
            ->where('account_id', $accountId)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp', 'ASC')
            ->get();

        // Set the pre-loaded account on all dividends to avoid lazy loading
        if ($preloadedAccount !== null) {
            foreach ($dividends as $dividend) {
                $dividend->setRelation('accountModel', $preloadedAccount);
            }
        }

        $dividendsList = [];
        foreach ($dividends as $dividend) {
            // Handle both string and DateTime timestamp
            $timestamp = $dividend->timestamp;
            if (is_string($timestamp)) {
                $timestamp = new \DateTime($timestamp);
            }

            // Gross dividend = amount (without fees) in dividend currency (don't convert yet)
            $grossAmountInDividendCurrency = $dividend->amount;

            $dividendsList[] = [
                'date' => $timestamp->format('Y-m-d'),
                'symbol' => $dividend->symbol,
                'amount' => $grossAmountInDividendCurrency,
                'fee' => $dividend->fee,
                'description' => $dividend->description,
                'dividendCurrencyCode' => $dividend->dividendCurrencyModel->display_code,
                'dividendCurrencyIsoCode' => $dividend->dividendCurrencyModel->iso_code,
                'accountCurrencyCode' => $dividend->accountModel->currency->display_code,
                'accountCurrencyIsoCode' => $dividend->accountModel->currency->iso_code,
                'exchangeRate' => (float)($dividend->exchange_rate ?: 1),
                'formatted' => MoneyFormat::get_formatted_balance(
                    $dividend->dividendCurrencyModel->display_code,
                    $grossAmountInDividendCurrency
                ),
                'feeFormatted' => $dividend->fee > 0 ? MoneyFormat::get_formatted_fee(
                    $dividend->accountModel->currency->display_code,
                    $dividend->fee
                ) : '',
            ];
        }

        return $dividendsList;
    }

    /**
     * Get total gross dividends override for an account and year
     *
     * @param int $accountId The account ID
     * @param int $year The year
     * @param string|null $currency Optional: specific currency (EUR, USD, etc.)
     *                               If null, returns the override value (for backwards compatibility)
     * @return float|array|null Currency-specific override, overall override, or null
     */
    public function getTotalGrossDividendsOverride(
        int $accountId,
        int $year,
        string $currency = null
    ): float|array|null {
        $allOverrides = config('trades.total_gross_dividends_overrides', []);
        $globalOverrides = $allOverrides['global'] ?? [];
        // Note: config keys are strings, so cast to string
        $accountSpecificOverrides = $allOverrides['by_account'][(string)$accountId] ?? [];

        // Try account-specific first (takes precedence), then fall back to global
        $override = $accountSpecificOverrides[(string)$year]
            ?? $globalOverrides[(string)$year]
            ?? null;

        if ($override === null) {
            return null;
        }

        // If no specific currency requested, return the override as-is (backwards compatible)
        if ($currency === null) {
            // Handle new format: if override is an array, return it; if it's a float, return it
            return $override;
        }

        // Currency-specific lookup
        // If override is an array (new format), look up the specific currency
        if (is_array($override)) {
            return $override[$currency] ?? null;
        }

        // If override is a float (old format), return it if no currency is specified
        // This maintains backwards compatibility
        return null;
    }

    /**
     * Create a summary of dividends grouped by their dividend currency (with tax mapping support)
     * Returns an array sorted by currency code with totals for gross amount and fees
     */
    public function createDividendsSummaryByTransactionCurrency(
        array $dividends,
        string $accountCurrencyCode,
        int $accountId
    ): array {
        $summary = [];
        $remappedSymbols = [];

        // Get tax mappings from config (hierarchical: by_account takes precedence over global)
        $allTaxMappings = config('trades.dividend_currency_tax_mappings', []);
        $globalMappings = $allTaxMappings['global'] ?? [];
        $accountSpecificMappings = $allTaxMappings['by_account'][$accountId] ?? [];
        // Merge with account-specific mappings taking precedence
        $taxMappings = array_merge($globalMappings, $accountSpecificMappings);

        // First pass: create mapping of ISO codes to currency display codes from all dividends
        $isoCodesToDisplayCodes = [];
        foreach ($dividends as $dividend) {
            $isoCodesToDisplayCodes[$dividend['dividendCurrencyIsoCode']] = $dividend['dividendCurrencyCode'];
        }

        foreach ($dividends as $dividend) {
            $symbol = $dividend['symbol'];
            // Check if this symbol should be remapped to a different currency bucket for tax purposes
            $taxCurrency = $taxMappings[$symbol] ?? null;
            $currencyIsoCode = $taxCurrency ?? $dividend['dividendCurrencyIsoCode'];
            // Use the correct currency code for the bucket's ISO code, not the dividend's original code
            $currencyCode = $isoCodesToDisplayCodes[$currencyIsoCode] ?? $dividend['dividendCurrencyCode'];

            // Track remapped symbols
            if ($taxCurrency && $taxCurrency !== $dividend['dividendCurrencyIsoCode']) {
                if (!isset($remappedSymbols[$symbol])) {
                    $remappedSymbols[$symbol] = [
                        'original_currency' => $dividend['dividendCurrencyIsoCode'],
                        'tax_currency' => $taxCurrency,
                        'totalGross' => 0,
                    ];
                }
                $remappedSymbols[$symbol]['totalGross'] += $dividend['amount'];
            }

            if (!isset($summary[$currencyIsoCode])) {
                $summary[$currencyIsoCode] = [
                    'isoCode' => $currencyIsoCode,
                    'currencyCode' => $currencyCode,
                    'totalGross' => 0,
                    'totalFee' => 0,
                ];
            }

            $summary[$currencyIsoCode]['totalGross'] += $dividend['amount'];
            $summary[$currencyIsoCode]['totalFee'] += $dividend['fee'];
        }

        // Add formatted values for display
        foreach ($summary as &$entry) {
            $entry['totalGrossFormatted'] = MoneyFormat::get_formatted_balance(
                $entry['currencyCode'],
                $entry['totalGross']
            );
            // Fees are always in account currency, not dividend currency
            $entry['totalFeeFormatted'] = MoneyFormat::get_formatted_fee(
                $accountCurrencyCode,
                $entry['totalFee']
            );
        }

        // Sort by currency code
        ksort($summary);

        // Prepare remapped symbols info for display
        $remappedInfo = [];
        if (!empty($dividends) && !empty($remappedSymbols)) {
            foreach ($remappedSymbols as $symbol => $data) {
                // Find a dividend entry to get the currency code for formatting
                $dividendForFormatting = null;
                foreach ($dividends as $div) {
                    if ($div['symbol'] === $symbol) {
                        $dividendForFormatting = $div;
                        break;
                    }
                }

                if ($dividendForFormatting) {
                    $remappedInfo[] = [
                        'symbol' => $symbol,
                        'originalCurrency' => $data['original_currency'],
                        'taxCurrency' => $data['tax_currency'],
                        'totalGross' => $data['totalGross'],
                        'totalGrossFormatted' => MoneyFormat::get_formatted_balance(
                            $dividendForFormatting['dividendCurrencyCode'],
                            $data['totalGross']
                        ),
                    ];
                }
            }
        }

        return [
            'groups' => array_values($summary),
            'remapped' => $remappedInfo,
        ];
    }
}

