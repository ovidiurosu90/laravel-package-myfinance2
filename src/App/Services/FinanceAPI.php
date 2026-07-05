<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Cache;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\UserAgent;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\ApiClientFactory;
use Scheb\YahooFinanceApi\Results\Quote;
use Scheb\YahooFinanceApi\Results\HistoricalData;

class FinanceAPI
{
    private const USER_AGENT_CHROME_116
        = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36';

    private const SECTOR_CACHE_KEY_PREFIX = 'SECTOR_';
    private const SECTOR_CACHE_TTL = 604800; // 7 days

    // Transient-failure retry policy for Yahoo Finance calls.
    private const RETRY_MAX_ATTEMPTS = 3;    // total tries (initial + 2 retries)
    private const RETRY_BASE_DELAY_MS = 300; // exponential backoff base per retry

    public function __construct()
    {
        // Deal with '429 Too Many Requests' errors, use curl_impersonate
        UserAgent::setUserAgents([self::USER_AGENT_CHROME_116]);
    }

    public function getClient(): ApiClient
    {
        $options = [/*...*/];
        $guzzleClient = new Client($options);
        return ApiClientFactory::createApiClient($guzzleClient);
    }

    /**
     * Execute a Yahoo Finance client call, retrying only transient failures
     * (5xx responses and connection resets/timeouts) with exponential backoff.
     * These are Yahoo-side blips that typically clear within a second, so a
     * short in-run retry avoids escalating a single hiccup to WARNING/ERROR.
     * Non-transient errors (4xx, including 429, and decode errors) are not
     * retried; the last exception is rethrown so the caller handles it as before.
     *
     * @throws \Exception the last exception when all attempts fail
     */
    private function _withRetry(callable $operation, string $context)
    {
        $attempt = 0;
        while (true) {
            try {
                return $operation();
            } catch (ConnectException | ServerException $e) {
                $attempt++;
                if ($attempt >= self::RETRY_MAX_ATTEMPTS) {
                    throw $e;
                }

                $delayMs = self::RETRY_BASE_DELAY_MS * (2 ** ($attempt - 1));
                Log::info(
                    "FinanceAPI retry $attempt/" . (self::RETRY_MAX_ATTEMPTS - 1)
                    . " for $context after transient error (waiting {$delayMs}ms): "
                    . $e->getMessage()
                );
                usleep($delayMs * 1000);
            }
        }
    }

    public static function isUnlisted(string $symbol): bool
    {
        return UnlistedSymbol::isUnlisted($symbol);
    }

    /**
     * Returns true for symbols that should be skipped in all API fetch operations:
     * unlisted symbols, obsolete symbols (acquired/merged), and delisted symbols.
     * Single source of truth — replaces the repeated pattern of checking both
     * general.obsolete_symbols and trades.delisted_symbols at every callsite.
     */
    public static function isSkippedSymbol(string $symbol): bool
    {
        if (self::isUnlisted($symbol)) {
            return true;
        }
        $obsolete = config('general.obsolete_symbols', []);
        $delisted = config('trades.delisted_symbols', []);
        return in_array($symbol, $obsolete, true) || in_array($symbol, $delisted, true);
    }

    /**
     * Get quote with caching control
     *
     * checkCache: Whether to check FinanceAPI cache first (2 min TTL)
     * persistStats: Whether to write to stat_today table
     *   - true: Write to database (default, for crons/normal operations)
     *   - false: Cache only, no database writes (Returns endpoint usage)
     */
    public function getQuote(string $symbol, bool $checkCache = true, bool $persistStats = true): ?Quote
    {
        if (self::isUnlisted($symbol)) {
            Log::info("Unlisted quote, returning null");
            return null;
        }

        $quote = $checkCache ? $this->getCachedQuote($symbol) : null;

        if (!empty($quote)) {
            // LOG::info("FinanceAPI->getQuote($symbol) from cache");
        } else {
            // LOG::info("FinanceAPI->getQuote($symbol) from FinanceAPI");

            $client = $this->getClient();

            try {
                $quote = $this->_withRetry(
                    fn () => $client->getQuote($symbol),
                    "getQuote($symbol)"
                );
                if (!empty($quote)) {
                    $this->cacheQuote($quote, $persistStats);
                }
            } catch (\Exception $e) {
                Log::warning(
                    "Couldn't get quote for symbol $symbol. "
                    . "Exception message: " . $e->getMessage()
                );
            }
        }

        if (empty($quote) || !($quote instanceof Quote)) {
            Log::warning("Invalid quote for symbol $symbol");
            return null;
        }

        return $quote;
    }

    public function getQuotes(
        array $symbols,
        bool $checkCache = true,
        bool $persistStats = true,
        bool $warnOnError = true
    ): ?array
    {
        $missingCachedSymbols = [];
        $quotes = [];
        $obsoleteSymbols = config('general.obsolete_symbols');
        $delistedSymbols = config('trades.delisted_symbols', []);

        foreach ($symbols as $symbol) {
            $quote = $checkCache ? $this->getCachedQuote($symbol) : null;
            if (!empty($quote)) {
                $quotes[] = $quote;
            } else {
                if (!in_array($symbol, $obsoleteSymbols)
                    && !in_array($symbol, $delistedSymbols, true)
                    && !self::isUnlisted($symbol)
                ) {
                    $missingCachedSymbols[] = $symbol;
                }
            }
        }

        if (empty($missingCachedSymbols)) {
            Log::info(
                "FinanceAPI->getQuotes(" . implode(', ', $symbols)
                . ") from cache"
            );
        } else {
            Log::info(
                "FinanceAPI->getQuotes(" . implode(', ', $symbols)
                . ") from FinanceAPI, missingCachedSymbols: "
                . implode(', ', $missingCachedSymbols)
            );

            $client = $this->getClient();

            try {
                $apiQuotes = $this->_withRetry(
                    fn () => $client->getQuotes($missingCachedSymbols),
                    'getQuotes(' . join(', ', $missingCachedSymbols) . ')'
                );
                $this->cacheQuotes($apiQuotes, $persistStats);
                $quotes = array_merge($quotes, $apiQuotes);
            } catch (\Exception $e) {
                $message = "Couldn't get quotes for symbols "
                    . join(', ', $missingCachedSymbols)
                    . ". Exception message: " . $e->getMessage();
                $warnOnError ? Log::warning($message) : Log::info($message);
            }
        }

        if (empty($quotes) || !is_array($quotes) ||
            !($quotes[0] instanceof Quote)
        ) {
            return null;
        }

        return $quotes;
    }

    public function getExchangeRates(
        array $currencyPairs,
        bool $checkCache = true,
        bool $persistStats = true
    ): ?array
    {
        if (empty($currencyPairs)) {
            return [];
        }
        $symbols = self::currencyPairsToSymbols($currencyPairs);

        $missingCachedSymbols = [];
        $quotes = [];
        $obsoleteSymbols = config('general.obsolete_symbols');

        foreach ($symbols as $symbol) {
            $quote = $checkCache ? $this->getCachedQuote($symbol) : null;
            if (!empty($quote)) {
                $quotes[] = $quote;
            } else if (!in_array($symbol, $obsoleteSymbols)) {
                $missingCachedSymbols[] = $symbol;
            }
        }

        if (empty($missingCachedSymbols)) {
            Log::info(
                "FinanceAPI->getExchangeRates(" . implode(', ', $symbols)
                . ") from cache"
            );
        } else {
            Log::info(
                "FinanceAPI->getExchangeRates(" . implode(', ', $symbols)
                . ") from FinanceAPI, missingCachedSymbols: "
                . implode(', ', $missingCachedSymbols)
            );

            $client = $this->getClient();

            try {
                $quotes = $this->_withRetry(
                    fn () => $client->getExchangeRates($currencyPairs),
                    'getExchangeRates(' . implode(', ', $symbols) . ')'
                );
                $this->cacheQuotes($quotes, $persistStats);
            } catch (\Exception $e) {
                Log::warning(
                    "Couldn't get exchange rates for currencyPairs" .
                    print_r($currencyPairs, true) .
                    ". Exception message: " . $e->getMessage()
                );
            }
        }

        if (empty($quotes) || !is_array($quotes) ||
            !($quotes[0] instanceof Quote)
            || count($symbols) != count($quotes)
        ) {
            Log::info(
                "FinanceAPI->getExchangeRates("
                . implode(', ', $symbols) . ") failed!"
            );
            return null;
        }

        return $quotes;
    }


    /**
     * @param $currencyPairs array(array('EUR', 'USD'))
     *
     * @return $symbols array('EURUSD=X')
     */
    public static function currencyPairsToSymbols(array $currencyPairs): array
    {
        if (empty($currencyPairs)) {
            return [];
        }

        $symbols = [];
        foreach ($currencyPairs as $currencyPair) {
            $symbol = strtoupper($currencyPair[0])
                . strtoupper($currencyPair[1]) . '=X';
            $symbols[] = $symbol;
        }
        return $symbols;
    }

    /**
     * @param $currencyPairs array(array('EUR', 'USD'))
     * @param $date \DateTimeInterface
     *
     * @return $results array(HistoricalData)
     */
    public function getHistoricalExchangeRates(array $currencyPairs,
        \DateTimeInterface $date, bool $persistStats = true): ?array
    {
        if (empty($currencyPairs)) {
            return [];
        }
        $symbols = self::currencyPairsToSymbols($currencyPairs);

        Log::info(
            "FinanceAPI->getHistoricalExchangeRates("
            . implode(', ', $symbols) . ", date: " . $date->format('Y-m-d')
            . ") from FinanceAPI"
        );

        $historicalDataItems = [];
        $client = $this->getClient();

        foreach ($symbols as $symbol) {
            try {
                $quote = $this->getQuote($symbol); //LATER Get rid of this!
                $historicalData = $this->getHistoricalQuoteData(
                    $quote, $date, persistStats: $persistStats
                );
                $historicalDataItems[] = $historicalData;
            } catch (\Exception $e) {
                // Check if it's a weekend (expected failure)
                $dayOfWeek = $date->format('N'); // 1 (Mon) to 7 (Sun)
                $isWeekend = ($dayOfWeek >= 6);
                $reason = $isWeekend ? ' (weekend, no trading)' : ' (likely holiday or data not yet available)';

                Log::info(
                    "No exchange rate data for symbol '"
                    . $symbol . "', date: " . $date->format('Y-m-d') . $reason
                );
                return null;
            }
        }

        if (empty($historicalDataItems) || !is_array($historicalDataItems)
            || !($historicalDataItems[0] instanceof HistoricalData)
            || count($symbols) != count($historicalDataItems)
        ) {
            // Check if it's a weekend (expected failure)
            $dayOfWeek = $date->format('N'); // 1 (Mon) to 7 (Sun)
            $isWeekend = ($dayOfWeek >= 6);
            $reason = $isWeekend ? ' (weekend, no trading)' : ' (likely holiday or data not yet available)';

            LOG::info("FinanceAPI->getHistoricalExchangeRates("
                      . implode(', ', $symbols) . ", date: " . $date->format('Y-m-d')
                      . ") failed" . $reason);
            return null;
        }

        return $historicalDataItems;
    }

    public function getHistoricalQuoteData(Quote $quote,
        \DateTime $timestamp, bool $checkCache = true, bool $persistStats = true): ?HistoricalData
    {
        $startDate = clone $timestamp;
        $startDate->setTime(0, 0, 0, 0);
        $endDate = clone $startDate;
        $endDate->add(new \DateInterval('P1D'));

        $symbol = $quote->getSymbol();
        $quoteTimezone = $quote->getExchangeTimezoneName();
        $offset = FinanceUtils::get_timezone_offset($quoteTimezone);

        // LOG::debug('quoteTimezone');
        // LOG::debug(var_export($quoteTimezone, true));
        // LOG::debug('startDate timezone');
        // LOG::debug(var_export($startDate->getTimezone()->getName(), true));
        // LOG::debug('offset'); LOG::debug(var_export($offset, true));

        //NOTE Adding 1 day when origin timezone is ahead of remote timezone
        if ($offset > 0) { // For stocks like GOOGL, AMZN, MSFT
            $startDate->add(new \DateInterval('P1D'));
            $endDate->add(new \DateInterval('P1D'));
        }

        $interval = ApiClient::INTERVAL_1_DAY;

        // FinanceAPI caching layer (2-10 min TTL)
        // Helps avoid duplicate API calls within short time windows
        // Note: persistStats controls whether data is also written to stats_historical table
        $historicalData = $checkCache
            ? $this->getCachedHistoricalData($symbol, $timestamp->format('Y-m-d'))
            : null;

        if (!empty($historicalData)) {
            // LOG::info("FinanceAPI->getHistoricalQuoteData($symbol, "
            //           . $timestamp->format('Y-m-d') . ") => close: "
            //           . $historicalData->getClose()
            //           . " from cache");
        } else {
            // LOG::info("FinanceAPI->getHistoricalQuoteData($symbol, "
            //           . $timestamp->format('Y-m-d') . ") from FinanceAPI");

            $client = $this->getClient();

            try {
                $historicalDataResponse = $client->getHistoricalQuoteData($symbol,
                    $interval, $startDate, $endDate);

                if (empty($historicalDataResponse)
                    || !is_array($historicalDataResponse)
                    || !($historicalDataResponse[0] instanceof HistoricalData)
                ) {
                    if (!empty($historicalDataResponse)) {
                        LOG::warning('Invalid historicalDataResponse('
                            . print_r($historicalDataResponse, true)
                            . ') for symbol ' . $symbol . ', interval: ' . $interval
                            . ', startDate ' . $startDate->format('Y-m-d')
                            . ', endDate: ' . $endDate->format('Y-m-d'));
                    }
                    return null;
                }
                $historicalData = $historicalDataResponse[0];

                // Validate price before caching - reject 0 or negative prices
                $price = $historicalData->getClose();
                if (empty($price) || $price <= 0) {
                    LOG::info("Rejecting invalid price ($price) for $symbol on "
                        . $timestamp->format('Y-m-d'));
                    return null;
                }

                // LOG::info("FinanceAPI->getHistoricalQuoteData($symbol, "
                //           . $timestamp->format('Y-m-d') . ") => close: "
                //           . $price . " from FinanceAPI");

                $this->cacheHistoricalData($quote, $historicalData, $persistStats);
            } catch (\Exception $e) {
                // Don't log here - will be logged once in the validation check below
            }
        }

        if (empty($historicalData)
            || !($historicalData instanceof HistoricalData)
        ) {
            // Check if it's a weekend (expected failure)
            $dayOfWeek = $timestamp->format('N'); // 1 (Mon) to 7 (Sun)
            $isWeekend = ($dayOfWeek >= 6);
            $reason = $isWeekend ? ' (weekend, no trading)' : ' (likely holiday or data not yet available)';

            LOG::info("No historical data for symbol $symbol,"
                    . " date " . $timestamp->format('Y-m-d') . $reason);
            return null;
        }

        return $historicalData;
    }

    public function getHistoricalPeriodQuoteData(Quote $quote,
        \DateTime $startDate, \DateTime $endDate): ?array
    {
        $symbol = $quote->getSymbol();
        $quoteTimezone = $quote->getExchangeTimezoneName();
        $offset = FinanceUtils::get_timezone_offset($quoteTimezone);

        //NOTE Adding 1 day when origin timezone is ahead of remote timezone
        if ($offset > 0) { // For stocks like GOOGL, AMZN, MSFT
            $startDate->add(new \DateInterval('P1D'));
            $endDate->add(new \DateInterval('P1D'));
        }

        $interval = ApiClient::INTERVAL_1_DAY;

        Log::info(
            "FinanceAPI->getHistoricalPeriodQuoteData($symbol, start: "
            . $startDate->format('Y-m-d') . ", end: "
            . $endDate->format('Y-m-d') . ") from FinanceAPI"
        );

        $client = $this->getClient();

        try {
            $historicalDataResponse = $client->getHistoricalQuoteData(
                $symbol,
                $interval,
                $startDate,
                $endDate
            );

            if (empty($historicalDataResponse)
                || !is_array($historicalDataResponse)
                || !($historicalDataResponse[0] instanceof HistoricalData)
            ) {
                return null;
            }

            // Filter out entries with invalid prices (0 or negative)
            $validData = [];
            foreach ($historicalDataResponse as $historicalData) {
                $price = $historicalData->getClose();
                if (!empty($price) && $price > 0) {
                    $validData[] = $historicalData;
                } else {
                    LOG::info("Rejecting invalid price ($price) for $symbol on "
                        . $historicalData->getDate()->format('Y-m-d'));
                }
            }

            return empty($validData) ? null : $validData;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            // 400 "Data doesn't exist for startDate" is expected for newly listed symbols
            // (e.g. a spinoff/IPO where the ticker didn't exist for the requested date range).
            if (str_contains($msg, "Data doesn't exist for startDate")) {
                Log::info(
                    "No historical data for $symbol before its listing date"
                    . " (start: " . $startDate->format('Y-m-d') . ")"
                );
            } else {
                Log::warning(
                    "Couldn't get historical data for symbol $symbol!"
                    . " Exception message: " . $msg
                );
            }
            return null;
        }
    }

    public function cacheQuotes(array $quotes, bool $persistStats = true): int
    {
        $numCached = 0;
        foreach ($quotes as $quote) {
            if ($this->cacheQuote($quote, $persistStats)) {
                $numCached++;
            }
        }
        return $numCached;
    }

    /**
     * Cache quote in FinanceAPI cache (2 minute TTL)
     *
     * persistStats controls whether to write to database (stat_today table)
     * Set to false when you want to cache in FinanceAPI but NOT persist to DB
     * Use case: Returns endpoint always uses persistStats=false to avoid DB pollution
     */
    public function cacheQuote(Quote $quote, bool $persistStats = true): bool
    {
        $symbol = $quote->getSymbol();
        $key = 'QUOTE_' . $symbol;
        $value = serialize($quote);

        // persistStats controls whether to write to database (stat_today table)
        if ($persistStats) {
            Stats::persistQuote($quote);
        }

        // FinanceAPI cache: 2 minutes TTL
        return Cache::add($key, $value, 60*2); // cached for 2 minutes
    }

    public function getCachedQuote(string $symbol): ?Quote
    {
        $key = 'QUOTE_' . $symbol;
        if (!Cache::has($key)) {
            return null;
        }

        $cached = Cache::get($key);
        if (!empty($cached)) {
            return unserialize($cached);
        }

        return null;
    }

    public function cacheHistoricalData(Quote $quote,
        HistoricalData $historicalData, bool $persistStats = true): bool
    {
        $symbol = $quote->getSymbol();
        $date = $historicalData->getDate()->format('Y-m-d');

        $key = 'HISTORICAL_DATA_' . $symbol . '_' . $date;
        $value = serialize($historicalData);

        // persistStats controls whether to write to database (stats_historical table)
        // Set to false when you want to cache in FinanceAPI but NOT persist to DB
        // Use case: Returns endpoint always uses persistStats=false to avoid DB pollution
        if ($persistStats) {
            Stats::persistHistoricalData($quote, $historicalData);
        }

        // FinanceAPI cache: 10 minutes TTL
        // Short-lived but helps avoid duplicate API calls within same time window
        return Cache::add($key, $value, 60*10); // cached for 10 minutes
    }

    public function getCachedHistoricalData(
        string $symbol, string $date): ?HistoricalData
    {
        $key = 'HISTORICAL_DATA_' . $symbol . '_' . $date;
        if (!Cache::has($key)) {
            return null;
        }

        $cached = Cache::get($key);
        if (!empty($cached)) {
            return unserialize($cached);
        }

        return null;
    }

    public function getCachedSector(string $symbol): ?string
    {
        $cached = Cache::get(self::SECTOR_CACHE_KEY_PREFIX . $symbol);
        return (is_string($cached) && $cached !== '') ? $cached : null;
    }

    public function fetchAndCacheSector(string $symbol): ?string
    {
        // Crypto pairs (e.g. BTC-EUR, ETH-USD) carry no sector in Yahoo Finance
        if (self::isCryptoSymbol($symbol)) {
            Cache::put(self::SECTOR_CACHE_KEY_PREFIX . $symbol, 'Crypto', self::SECTOR_CACHE_TTL);
            return 'Crypto';
        }

        try {
            $result = $this->getClient()->getStockSummary($symbol, ['assetProfile', 'fundProfile', 'quoteType']);

            // Stocks/equities: use sector from assetProfile
            $sector = $result[0]['assetProfile']['sector'] ?? null;
            if (is_string($sector) && $sector !== '') {
                Cache::put(self::SECTOR_CACHE_KEY_PREFIX . $symbol, $sector, self::SECTOR_CACHE_TTL);
                return $sector;
            }

            // US ETFs/funds: use Morningstar category from fundProfile
            $category = $result[0]['fundProfile']['categoryName'] ?? null;
            if (is_string($category) && $category !== '') {
                $category = self::_normalizeFundCategory($category);
                Cache::put(self::SECTOR_CACHE_KEY_PREFIX . $symbol, $category, self::SECTOR_CACHE_TTL);
                return $category;
            }

            // UCITS ETFs and other non-equity instruments: fall back to instrument type
            $label = match($result[0]['quoteType']['quoteType'] ?? null) {
                'ETF'        => 'Equity ETF',
                'MUTUALFUND' => 'Fund',
                'INDEX'      => 'Index',
                default      => null,
            };
            if ($label !== null) {
                Cache::put(self::SECTOR_CACHE_KEY_PREFIX . $symbol, $label, self::SECTOR_CACHE_TTL);
                return $label;
            }

            return null;
        } catch (\Exception $e) {
            Log::warning("Could not fetch sector for {$symbol}: " . $e->getMessage());
            return null;
        }
    }

    private static function _normalizeFundCategory(string $category): string
    {
        // Morningstar equity style-box categories (e.g. "Large Blend", "Foreign Large Blend",
        // "Global Large-Stock Blend") → unified label so ETFs of similar scope group together
        if (preg_match('/\b(Blend|Growth|Value)\s*$/', $category)) {
            return 'Equity ETF';
        }
        return $category;
    }

    public static function isCryptoSymbol(string $symbol): bool
    {
        $parts = explode('-', $symbol);
        if (count($parts) !== 2) {
            return false;
        }
        return in_array(strtoupper($parts[1]), ['EUR', 'USD', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD'], true);
    }
}

