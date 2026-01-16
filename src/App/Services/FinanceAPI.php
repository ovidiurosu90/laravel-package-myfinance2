<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Cache;

use GuzzleHttp\Client;
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

    public static function isUnlisted(string $symbol): bool
    {
        return str_starts_with($symbol, config('trades.unlisted'));
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
                $quote = $client->getQuote($symbol);
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

    public function getQuotes(array $symbols, bool $checkCache = true): ?array
    {
        $missingCachedSymbols = [];
        $quotes = [];
        $obsoleteSymbols = config('general.obsolete_symbols');

        foreach ($symbols as $symbol) {
            $quote = $checkCache ? $this->getCachedQuote($symbol) : null;
            if (!empty($quote)) {
                $quotes[] = $quote;
            } else {
                if (!in_array($symbol, $obsoleteSymbols)
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
                $quotes = $client->getQuotes($symbols);
                $this->cacheQuotes($quotes);
            } catch (\Exception $e) {
                Log::warning(
                    "Couldn't get quotes for symbols "
                    . join(', ', $symbols)
                    . ". Exception message: " . $e->getMessage()
                );
            }
        }

        if (empty($quotes) || !is_array($quotes) ||
            !($quotes[0] instanceof Quote)
        ) {
            return null;
        }

        return $quotes;
    }

    public function getExchangeRates(array $currencyPairs, bool $checkCache = true)
        : ?array
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
                $quotes = $client->getExchangeRates($currencyPairs);
                $this->cacheQuotes($quotes);
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
        \DateTimeInterface $date): ?array
    {
        if (empty($currencyPairs)) {
            return [];
        }
        $symbols = self::currencyPairsToSymbols($currencyPairs);

        Log::info(
            "FinanceAPI->getHistoricalExchangeRates("
            . implode(', ', $symbols) . ") from FinanceAPI"
        );

        $historicalDataItems = [];
        $client = $this->getClient();

        foreach ($symbols as $symbol) {
            try {
                $quote = $this->getQuote($symbol); //LATER Get rid of this!
                $historicalData = $this->getHistoricalQuoteData($quote, $date);
                $historicalDataItems[] = $historicalData;
            } catch (\Exception $e) {
                Log::warning(
                    "Couldn't get the exchange rate for symbol '"
                    . $symbol . "'. Exception message: " . $e->getMessage()
                );
                return null;
            }
        }

        if (empty($historicalDataItems) || !is_array($historicalDataItems)
            || !($historicalDataItems[0] instanceof HistoricalData)
            || count($symbols) != count($historicalDataItems)
        ) {
            LOG::info("FinanceAPI->getHistoricalExchangeRates("
                      . implode(', ', $symbols) . ") failed!");
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
                // LOG::info("FinanceAPI->getHistoricalQuoteData($symbol, "
                //           . $timestamp->format('Y-m-d') . ") => close: "
                //           . $historicalData->getClose()
                //           . " from FinanceAPI");

                $this->cacheHistoricalData($quote, $historicalData, $persistStats);
            } catch (\Exception $e) {
                LOG::warning("Couldn't get historical data for symbol $symbol,"
                    . " for date " . $timestamp->format('Y-m-d') . "!"
                    . " Exception message: " . $e->getMessage());
            }
        }

        if (empty($historicalData)
            || !($historicalData instanceof HistoricalData)
        ) {
            LOG::warning("Invalid historical data for symbol $symbol,"
                    . " for date " . $timestamp->format('Y-m-d'));
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
            return $historicalDataResponse;
        } catch (\Exception $e) {
            Log::warning(
                "Couldn't get historical data for symbol $symbol!"
                . " Exception message: " . $e->getMessage()
            );
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
}

