<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Services\Positions;

/**
 * Returns Quote Provider
 *
 * Handles quote and exchange rate fetching with intelligent caching:
 * - Layer 1: In-memory cache (per-request)
 * - Layer 2: FinanceAPI cache (2-10 min TTL)
 * - Layer 3: Returns persistent cache (1 hour TTL)
 *
 * Supports:
 * - Price/exchange rate overrides (hierarchical: account-specific → global)
 * - Delisted symbols (from config)
 * - Unlisted symbols (from config)
 * - 7-day fallback for missing data (weekends/holidays)
 */
class ReturnsQuoteProvider
{
    /**
     * Quote stats cache shared across all symbol/date calls
     * Format: 'SYMBOL_YYYY-MM-DD' => stat_array
     * Scope: In-memory, per-request (Layer 1)
     */
    private array $quoteStatsCache = [];

    /**
     * Exchange rate stats cache shared across all symbol/date calls
     * Format: 'SYMBOL_YYYY-MM-DD' => stat_array
     * Scope: In-memory, per-request (Layer 1)
     */
    private array $exchangeRateStatsCache = [];
    private array $exchangeRateOverrideCache = [];
    private array $exchangeRateCache = [];
    private ?FinanceAPI $financeAPI = null;
    private ReturnsConfigHelper $configHelper;

    public function __construct(ReturnsConfigHelper $configHelper = null)
    {
        $this->configHelper = $configHelper ?? new ReturnsConfigHelper();
    }

    /**
     * Get quote stat (price with override and caching support)
     */
    public function getOrFetchQuoteStat(
        int $accountId,
        string $symbol,
        \DateTimeInterface $date
    ): ?array {
        // Step 1: Try price overrides first (hierarchical: account-specific, then global)
        $overrideStat = $this->getPriceOverrideStat($accountId, $symbol, $date);
        if (!empty($overrideStat)) {
            return $overrideStat;
        }

        // Step 2: Try in-memory cache (Layer 1) - fastest
        $cachedStat = $this->getStatFromCache($symbol, $date);
        if (!empty($cachedStat)) {
            return $cachedStat;
        }

        // Step 3: Try unlisted symbol config
        if (FinanceAPI::isUnlisted($symbol)) {
            return $this->getUnlistedSymbolStat($symbol, $date);
        }

        // Step 4: Fetch from FinanceAPI (uses Layer 2 cache internally)
        // Populates both in-memory and persistent Returns caches
        // No database interaction - all caching is in-memory or persistent cache
        return $this->fetchStatFromAPI($symbol, $date);
    }

    /**
     * Get stat from price override config using hierarchical lookup
     * Checks account-specific overrides first, then global overrides
     * Tries current date and up to 7 days prior
     */
    private function getPriceOverrideStat(
        int $accountId,
        string $symbol,
        \DateTimeInterface $date
    ): ?array {
        $currentDate = clone $date;

        // Try current date and up to MAX_FALLBACK_DAYS prior
        for ($attempt = 0; $attempt < ReturnsConstants::MAX_FALLBACK_DAYS; $attempt++) {
            $price = $this->getOverride($symbol, $accountId, $currentDate, 'price');
            if ($price !== null) {
                $dateStr = $currentDate->format('Y-m-d');
                $apiPrice = $this->getApiPriceForOverride($symbol, $currentDate);

                return [
                    'symbol' => $symbol,
                    'date' => $dateStr,
                    'unit_price' => $price,
                    'price_overridden' => true,
                    'api_price' => $apiPrice,
                ];
            }
            $currentDate->modify('-1 day');
        }

        return null;
    }

    /**
     * Get API price for comparison with override
     */
    private function getApiPriceForOverride(
        string $symbol,
        \DateTimeInterface $dateTime
    ): ?float {
        $obsoleteSymbols = config('general.obsolete_symbols', []);
        if (in_array($symbol, $obsoleteSymbols) || FinanceAPI::isUnlisted(
            $symbol
        )) {
            return null;
        }

        $startDate = clone $dateTime;

        for ($apiAttempts = 0; $apiAttempts < ReturnsConstants::MAX_FALLBACK_DAYS; $apiAttempts++) {
            try {
                $financeAPI = $this->getFinanceAPI();
                // Create minimal quote object without API call
                $quote = $this->createMinimalQuote($symbol);

                $historicalData = $financeAPI->getHistoricalQuoteData(
                    $quote,
                    clone $startDate,
                    checkCache: true,
                    persistStats: false
                );
                if (!empty($historicalData)) {
                    return $historicalData->getClose();
                }
            } catch (\Exception $e) {
                // Silent fallback
            }
            $startDate->modify('-1 day');
        }

        return null;
    }

    /**
     * Get stat from persistent and in-memory caches
     */
    private function getStatFromCache(string $symbol, \DateTimeInterface $date): ?array
    {
        $dateStr = $date->format('Y-m-d');
        $cacheKey = 'returns_stat_' . $symbol . '_' . $dateStr;

        // Try persistent cache (Layer 3)
        $cachedStat = Cache::get($cacheKey);
        if (!empty($cachedStat)) {
            return $cachedStat;
        }

        // Try in-memory cache (Layer 1)
        $inMemoryKey = $symbol . '_' . $dateStr;
        if (isset($this->quoteStatsCache[$inMemoryKey])) {
            return $this->quoteStatsCache[$inMemoryKey];
        }
        return null;
    }

    /**
     * Cache stat data in both in-memory and persistent cache (Layer 1 & Layer 3)
     */
    private function cacheStatData(
        string $symbol,
        \DateTimeInterface $date,
        array $stat
    ): void {
        // Layer 1: In-memory cache (per-request)
        $inMemoryKey = $symbol . '_' . $date->format('Y-m-d');
        $this->quoteStatsCache[$inMemoryKey] = $stat;

        // Layer 3: Persistent cache (1 hour TTL across all years)
        $cacheKey = 'returns_stat_' . $symbol . '_' . $date->format('Y-m-d');
        Cache::put($cacheKey, $stat, ReturnsConstants::QUOTE_CACHE_TTL);
    }

    /**
     * Get stat for unlisted symbol from config
     */
    private function getUnlistedSymbolStat(
        string $symbol,
        \DateTimeInterface $date
    ): ?array {
        $unlistedFMV = config('trades.unlisted_fmv');
        if (empty($unlistedFMV[$symbol])) {
            Log::warning(
                "Unlisted symbol $symbol not found in config "
                . "trades.unlisted_fmv"
            );
            return null;
        }

        list($price, $priceTimestamp) = Positions::getUnlistedFMV(
            $unlistedFMV[$symbol],
            $date
        );

        $stat = [
            'symbol' => $symbol,
            'date' => $priceTimestamp->format('Y-m-d'),
            'unit_price' => $price,
        ];

        $this->cacheStatData($symbol, $date, $stat);
        return $stat;
    }

    /**
     * Fetch stat from FinanceAPI with fallback to previous days
     */
    private function fetchStatFromAPI(
        string $symbol,
        \DateTimeInterface $date
    ): ?array {
        $currentDate = clone $date;
        $currencyIsoCode = $this->extractCurrencyFromSymbol($symbol);
        $isToday = $this->isToday($date);

        for ($attempt = 0; $attempt < 7; $attempt++) {
            try {
                $dateStr = $currentDate->format('Y-m-d');
                $financeAPI = $this->getFinanceAPI();

                // For today's date, use current quote price instead of historical data
                if ($isToday && $attempt === 0) {
                    // Need actual quote for current price
                    $quote = $financeAPI->getQuote($symbol, checkCache: true, persistStats: false);

                    if (!empty($quote)) {
                        $price = $quote->getRegularMarketPrice();
                        if (!empty($price)) {
                            $stat = [
                                'symbol' => $symbol,
                                'date' => $dateStr,
                                'unit_price' => (float)$price,
                            ];

                            if (!empty($currencyIsoCode)) {
                                $stat['currency_iso_code'] = $currencyIsoCode;
                            }

                            $this->cacheStatData($symbol, $date, $stat);
                            return $stat;
                        }
                    }
                    // If no regular market price, fall back to trying historical data or previous days
                }

                // For historical data, use minimal quote object (no API call needed)
                $quote = $this->createMinimalQuote($symbol, currency: $currencyIsoCode);

                // persistStats=false: Uses FinanceAPI cache but never writes to stats_historical
                $historicalData = $financeAPI->getHistoricalQuoteData(
                    $quote,
                    new \DateTime($dateStr),
                    checkCache: true,
                    persistStats: false
                );

                if (!empty($historicalData)) {
                    $stat = [
                        'symbol' => $symbol,
                        'date' => $dateStr,
                        'unit_price' => $historicalData->getClose(),
                        'open' => $historicalData->getOpen(),
                        'high' => $historicalData->getHigh(),
                        'low' => $historicalData->getLow(),
                        'adjclose' => $historicalData->getAdjClose(),
                        'volume' => $historicalData->getVolume(),
                    ];

                    if (!empty($currencyIsoCode)) {
                        $stat['currency_iso_code'] = $currencyIsoCode;
                    }

                    $this->cacheStatData($symbol, $date, $stat);
                    return $stat;
                }

                $currentDate->modify('-1 day');
            } catch (\Exception $e) {
                $currentDate->modify('-1 day');
            }
        }

        Log::warning(
            "Could not get historical data for $symbol on or before "
            . $date->format('Y-m-d') . " after " . ReturnsConstants::MAX_FALLBACK_DAYS . " attempts"
        );
        return null;
    }

    /**
     * Get exchange rate with lookup hierarchy and override support
     */
    public function getExchangeRateWithFallback(
        int $accountId,
        string $symbol,
        \DateTimeInterface $date
    ): ?array {
        $currencyIsoCode = $this->extractCurrencyFromSymbol($symbol);

        // Try override first
        $overrideStat = $this->getExchangeRateOverride($accountId, $symbol, $date);
        if (!empty($overrideStat)) {
            return $overrideStat;
        }

        // Try cache
        $cachedStat = $this->getExchangeRateFromCache($symbol, $date);
        if (!empty($cachedStat)) {
            return $cachedStat;
        }

        // Fetch from FinanceAPI (no database lookups)
        return $this->fetchExchangeRateFromAPI($symbol, $date, $currencyIsoCode);
    }

    /**
     * Get exchange rate override from config
     */
    private function getExchangeRateOverride(
        int $accountId,
        string $symbol,
        \DateTimeInterface $date
    ): ?array {
        $dateStr = $date->format('Y-m-d');
        $overrideCacheKey = "{$accountId}_{$symbol}_{$dateStr}";

        // Check in-memory cache first
        if (isset($this->exchangeRateOverrideCache[$overrideCacheKey])) {
            return $this->exchangeRateOverrideCache[$overrideCacheKey];
        }

        $currentDate = clone $date;

        // Try current date and up to MAX_FALLBACK_DAYS prior
        for ($attempt = 0; $attempt < ReturnsConstants::MAX_FALLBACK_DAYS; $attempt++) {
            $rate = $this->getOverride($symbol, $accountId, $currentDate, 'exchange_rate');
            if ($rate !== null) {
                $overrideDateStr = $currentDate->format('Y-m-d');
                $apiRate = $this->getApiRateForOverride($symbol, $currentDate);

                $result = [
                    'symbol' => $symbol,
                    'date' => $overrideDateStr,
                    'unit_price' => $rate,
                    'exchange_rate_overridden' => true,
                    'api_rate' => $apiRate,
                ];

                // Cache the result to avoid repeated lookups
                $this->exchangeRateOverrideCache[$overrideCacheKey] = $result;
                return $result;
            }
            $currentDate->modify('-1 day');
        }

        // Cache the "no result found" to avoid repeated 7-day loops
        $this->exchangeRateOverrideCache[$overrideCacheKey] = null;
        return null;
    }

    /**
     * Get API rate for comparison with override
     */
    private function getApiRateForOverride(
        string $symbol,
        \DateTimeInterface $dateTime
    ): ?float {
        $startDate = clone $dateTime;

        for ($apiAttempts = 0; $apiAttempts < ReturnsConstants::MAX_FALLBACK_DAYS; $apiAttempts++) {
            try {
                $financeAPI = $this->getFinanceAPI();
                $quote = $this->createMinimalQuoteForExchangeRate($symbol);
                $historicalData = $financeAPI->getHistoricalQuoteData(
                    $quote,
                    clone $startDate,
                    checkCache: true,
                    persistStats: false
                );
                if (!empty($historicalData)) {
                    return $historicalData->getClose();
                }
            } catch (\Exception $e) {
                // Silent fallback
            }
            $startDate->modify('-1 day');
        }

        return null;
    }

    /**
     * Get exchange rate from persistent and in-memory caches
     */
    private function getExchangeRateFromCache(string $symbol, \DateTimeInterface $date): ?array
    {
        $dateStr = $date->format('Y-m-d');
        $cacheKey = 'returns_exchange_rate_' . $symbol . '_' . $dateStr;

        // Try persistent cache (Layer 3)
        $cachedStat = Cache::get($cacheKey);
        if (!empty($cachedStat)) {
            return $cachedStat;
        }

        // Try in-memory cache (Layer 1)
        $inMemoryKey = $symbol . '_' . $dateStr;
        if (isset($this->exchangeRateStatsCache[$inMemoryKey])) {
            return $this->exchangeRateStatsCache[$inMemoryKey];
        }
        return null;
    }

    /**
     * Cache exchange rate data in both in-memory and persistent cache
     */
    private function cacheExchangeRateData(
        string $symbol,
        \DateTimeInterface $date,
        array $stat
    ): void {
        // Layer 1: In-memory cache (per-request)
        $inMemoryKey = $symbol . '_' . $date->format('Y-m-d');
        $this->exchangeRateStatsCache[$inMemoryKey] = $stat;

        // Layer 3: Persistent cache (TTL across all years)
        $cacheKey = 'returns_exchange_rate_' . $symbol . '_' . $date->format(
            'Y-m-d'
        );
        Cache::put($cacheKey, $stat, ReturnsConstants::QUOTE_CACHE_TTL);
    }

    /**
     * Create minimal Quote object for any symbol
     */
    private function createMinimalQuote(
        string $symbol,
        ?string $timezone = null,
        ?string $currency = null
    ): \Scheb\YahooFinanceApi\Results\Quote {
        $quoteData = [
            'symbol' => $symbol,
            'exchangeTimezoneName' => $timezone ?? 'UTC',
        ];

        // Use provided currency, or try to extract from symbol (e.g., EURUSD=X)
        $currencyIsoCode = $currency ?? $this->extractCurrencyFromSymbol($symbol);
        if (!empty($currencyIsoCode)) {
            $quoteData['currency'] = $currencyIsoCode;
        }

        return new \Scheb\YahooFinanceApi\Results\Quote($quoteData);
    }

    /**
     * Create minimal Quote object for exchange rate lookups
     */
    private function createMinimalQuoteForExchangeRate(
        string $symbol,
        ?string $currency = null,
        ?string $timezone = null
    ): \Scheb\YahooFinanceApi\Results\Quote {
        return $this->createMinimalQuote($symbol, $timezone, $currency);
    }

    /**
     * Fetch exchange rate from FinanceAPI with fallback to previous days
     */
    private function fetchExchangeRateFromAPI(
        string $symbol,
        \DateTimeInterface $date,
        ?string $currencyIsoCode
    ): ?array {
        $currentDate = clone $date;
        $isToday = $this->isToday($date);

        for ($attempt = 0; $attempt < 7; $attempt++) {
            try {
                $dateStr = $currentDate->format('Y-m-d');
                $financeAPI = $this->getFinanceAPI();

                // For today's date, try to get current quote price first
                if ($isToday && $attempt === 0) {
                    $quote = $financeAPI->getQuote($symbol, checkCache: true, persistStats: false);
                    if (!empty($quote)) {
                        $price = $quote->getRegularMarketPrice();
                        if (!empty($price)) {
                            $stat = [
                                'symbol' => $symbol,
                                'date' => $dateStr,
                                'unit_price' => (float)$price,
                            ];

                            if (!empty($currencyIsoCode)) {
                                $stat['currency_iso_code'] = $currencyIsoCode;
                            }

                            $this->cacheExchangeRateData($symbol, $date, $stat);
                            return $stat;
                        }
                    }
                    // If no current price, fall back to historical data
                }

                $quote = $this->createMinimalQuoteForExchangeRate($symbol, $currencyIsoCode);

                // persistStats=false: Uses FinanceAPI cache but never writes to stats_historical
                $historicalData = $financeAPI->getHistoricalQuoteData(
                    $quote,
                    new \DateTime($dateStr),
                    checkCache: true,
                    persistStats: false
                );

                if (!empty($historicalData)) {
                    $stat = [
                        'symbol' => $symbol,
                        'date' => $dateStr,
                        'unit_price' => $historicalData->getClose(),
                        'open' => $historicalData->getOpen(),
                        'high' => $historicalData->getHigh(),
                        'low' => $historicalData->getLow(),
                        'adjclose' => $historicalData->getAdjClose(),
                        'volume' => $historicalData->getVolume(),
                    ];

                    if (!empty($currencyIsoCode)) {
                        $stat['currency_iso_code'] = $currencyIsoCode;
                    }

                    $this->cacheExchangeRateData($symbol, $date, $stat);
                    return $stat;
                }

                $currentDate->modify('-1 day');
            } catch (\Exception $e) {
                $currentDate->modify('-1 day');
            }
        }

        Log::warning(
            "Could not get exchange rate $symbol on or before "
            . $date->format('Y-m-d') . " from Stats or FinanceAPI"
        );
        return null;
    }

    /**
     * Get exchange rate between two currencies at a specific date
     */
    public function getExchangeRate(
        int $accountId,
        string $fromCurrency,
        string $toCurrency,
        \DateTimeInterface $date
    ): float {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $dateStr = $date->format('Y-m-d');
        $cacheKey = "{$accountId}_{$fromCurrency}_{$toCurrency}_{$dateStr}";

        // Check account-specific cache
        if (isset($this->exchangeRateCache[$cacheKey])) {
            return $this->exchangeRateCache[$cacheKey];
        }

        $rate = 1.0;

        if ($fromCurrency === 'EUR' && $toCurrency === 'USD') {
            $symbol = 'EURUSD=X';
            $stat = $this->getExchangeRateWithFallback($accountId, $symbol, $date);
            $rate = $stat ? $stat['unit_price'] : 1.0;
        } elseif ($fromCurrency === 'USD' && $toCurrency === 'EUR') {
            $symbol = 'EURUSD=X';
            $stat = $this->getExchangeRateWithFallback($accountId, $symbol, $date);
            $rate = $stat ? (1 / $stat['unit_price']) : 1.0;
        } else {
            Log::error("Unsupported currency conversion: $fromCurrency to $toCurrency");
        }

        $this->exchangeRateCache[$cacheKey] = $rate;
        return $rate;
    }

    /**
     * Extract currency ISO code from exchange rate symbol (e.g., USD from EURUSD=X)
     */
    private function extractCurrencyFromSymbol(string $symbol): ?string
    {
        if (strpos($symbol, '=X') === false) {
            return null;
        }

        $baseSymbol = str_replace('=X', '', $symbol);
        if (strlen($baseSymbol) >= 6) {
            return substr($baseSymbol, 3);
        }

        return null;
    }

    /**
     * Format exchange rate cleanly
     */
    public function formatCleanExchangeRate(float $rate): string
    {
        if (round($rate) == $rate) {
            return (string)(int)round($rate);
        }
        return (string)round($rate, 4);
    }

    /**
     * Get or create lazy-loaded FinanceAPI instance
     */
    private function getFinanceAPI(): FinanceAPI
    {
        if ($this->financeAPI === null) {
            $this->financeAPI = new FinanceAPI();
        }
        return $this->financeAPI;
    }

    /**
     * Check if a date is today
     */
    private function isToday(\DateTimeInterface $date): bool
    {
        $today = new \DateTime();
        return $date->format('Y-m-d') === $today->format('Y-m-d');
    }

    /**
     * Check if a symbol is known to be delisted
     */
    private function isDelistedSymbol(string $symbol): bool
    {
        $delistedSymbols = config('trades.delisted_symbols', []);
        return in_array($symbol, $delistedSymbols, true);
    }

    /**
     * Get price or exchange rate override for a symbol/pair on a specific date
     * Delegates to ReturnsConfigHelper for centralized override resolution
     */
    private function getOverride(
        string $symbol,
        int $accountId,
        \DateTimeInterface $date,
        string $type
    ): ?float {
        return $this->configHelper->getOverride($symbol, $accountId, $date, $type);
    }
}
