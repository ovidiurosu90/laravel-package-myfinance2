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

    public function getDividendSummaryByCountry(
        array $dividends,
        int $accountId,
        float $eurusdRate
    ): array
    {
        $config = $this->_loadDividendCountryConfig($accountId);
        $byCountry = [];
        $unmappedSymbols = [];

        foreach ($dividends as $dividend) {
            $country = $this->_getSymbolCountry($dividend['symbol'], $config);
            if ($country === null) {
                if (!in_array($dividend['symbol'], $unmappedSymbols)) {
                    $unmappedSymbols[] = $dividend['symbol'];
                }
                continue;
            }

            $grossInEur = $this->_computeGrossEur($dividend, $eurusdRate);
            $taxInEur   = $this->_computeTaxEur($dividend, $country, $config['withholdingCountries'], $eurusdRate);

            if (!isset($byCountry[$country])) {
                $byCountry[$country] = ['country' => $country, 'gross' => 0.0, 'tax' => 0.0, 'symbols' => []];
            }
            $byCountry[$country]['gross'] += $grossInEur;
            $byCountry[$country]['tax']   += $taxInEur;
            if (!in_array($dividend['symbol'], $byCountry[$country]['symbols'])) {
                $byCountry[$country]['symbols'][] = $dividend['symbol'];
            }
        }

        ksort($byCountry);

        return $this->_buildCountrySummaryResult($byCountry, $unmappedSymbols);
    }

    private function _loadDividendCountryConfig(int $accountId): array
    {
        $allMappings = config(
            'trades.dividend_country_mappings',
            ['global' => [], 'by_account' => []]
        );
        return [
            'globalMappings'      => $allMappings['global'] ?? [],
            'accountMappings'     => $allMappings['by_account'][$accountId] ?? [],
            'withholdingCountries' => config('trades.dividend_withholding_tax_countries', ['NL', 'US']),
        ];
    }

    private function _getSymbolCountry(string $symbol, array $config): ?string
    {
        return $config['accountMappings'][$symbol] ?? $config['globalMappings'][$symbol] ?? null;
    }

    private function _computeGrossEur(array $dividend, float $eurusdRate): float
    {
        $exchangeRate = (float)($dividend['exchangeRate'] ?: 1);
        $grossInAccountCurrency = $dividend['amount'] / $exchangeRate;
        if ($dividend['accountCurrencyIsoCode'] === 'USD') {
            return $eurusdRate > 0 ? $grossInAccountCurrency / $eurusdRate : 0.0;
        }
        return $grossInAccountCurrency;
    }

    private function _computeTaxEur(
        array $dividend,
        string $country,
        array $withholdingCountries,
        float $eurusdRate
    ): float
    {
        if (!in_array($country, $withholdingCountries) || $dividend['fee'] <= 0) {
            return 0.0;
        }
        if ($dividend['accountCurrencyIsoCode'] === 'USD') {
            return $eurusdRate > 0 ? $dividend['fee'] / $eurusdRate : 0.0;
        }
        return (float)$dividend['fee'];
    }

    private function _buildCountrySummaryResult(array $byCountry, array $unmappedSymbols): array
    {
        $formattedByCountry = [];
        $totalGross = 0.0;
        $totalTax   = 0.0;

        foreach ($byCountry as $country => $data) {
            $gross = $data['gross'];
            $tax   = $data['tax'];
            $net   = $gross - $tax;
            $totalGross += $gross;
            $totalTax   += $tax;

            $symbols = $data['symbols'] ?? [];
            sort($symbols);
            $formattedByCountry[] = [
                'country'        => $country,
                'gross'          => $gross,
                'tax'            => $tax,
                'net'            => $net,
                'grossFormatted' => MoneyFormat::get_formatted_balance('€', $gross),
                'taxFormatted'   => $tax > 0 ? MoneyFormat::get_formatted_balance('€', $tax) : '',
                'netFormatted'   => MoneyFormat::get_formatted_balance('€', $net),
                'symbols'        => $symbols,
            ];
        }

        $totalNet         = $totalGross - $totalTax;
        $dutchDividendTax = $byCountry['NL']['tax'] ?? 0.0;

        $foreignTaxByCountry = [];
        foreach ($byCountry as $country => $data) {
            if ($country !== 'NL' && $data['tax'] > 0) {
                $foreignTaxByCountry[] = [
                    'country'        => $country,
                    'tax'            => $data['tax'],
                    'gross'          => $data['gross'],
                    'taxFormatted'   => MoneyFormat::get_formatted_balance('€', $data['tax']),
                    'grossFormatted' => MoneyFormat::get_formatted_balance('€', $data['gross']),
                ];
            }
        }

        return [
            'byCountry' => $formattedByCountry,
            'totals'    => [
                'gross'          => $totalGross,
                'tax'            => $totalTax,
                'net'            => $totalNet,
                'grossFormatted' => MoneyFormat::get_formatted_balance('€', $totalGross),
                'taxFormatted'   => MoneyFormat::get_formatted_balance('€', $totalTax),
                'netFormatted'   => MoneyFormat::get_formatted_balance('€', $totalNet),
            ],
            'dutchDividendTax'          => $dutchDividendTax,
            'dutchDividendTaxFormatted' => MoneyFormat::get_formatted_balance('€', $dutchDividendTax),
            'totalGross'                => $totalGross,
            'foreignTaxByCountry'       => $foreignTaxByCountry,
            'unmappedSymbols'           => $unmappedSymbols,
        ];
    }

    /**
     * Create a summary of dividends grouped by their dividend currency (with tax mapping support)
     * Returns an array sorted by currency code with totals for gross amount and fees
     */
    public function createDividendsSummaryByTransactionCurrency(
        array $dividends,
        string $accountCurrencyCode
    ): array {
        $summary = [];

        foreach ($dividends as $dividend) {
            $currencyIsoCode = $dividend['dividendCurrencyIsoCode'];
            $currencyCode = $dividend['dividendCurrencyCode'];

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

        return ['groups' => array_values($summary)];
    }
}

