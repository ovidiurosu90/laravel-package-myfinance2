<?php

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
    private const USER_AGENT_CHROME_116 = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36';

    public function __construct()
    {
        // Deal with '429 Too Many Requests' errors, use curl_impersonate
        UserAgent::setUserAgents([self::USER_AGENT_CHROME_116]);
        return;

        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            UserAgent::setUserAgents([UserAgent::getRandomUserAgent()]);
            return;
        }

        //NOTE Sometimes we get an GuzzleHttp\Exception\ClientException
        /*
        Client error: `GET https://query2.finance.yahoo.com/v1/test/getcrumb`
            resulted in a `401 Unauthorized`
        response: {"finance":{"result":null,"error":{"code":"Unauthorized",
            "description":"Invalid Cookie"}}}
        */
        UserAgent::setUserAgents([
            $_SERVER['HTTP_USER_AGENT']
        ]);
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

    public function getQuote(string $symbol, bool $checkCache = true): ?Quote
    {
        if (self::isUnlisted($symbol)) {
            LOG::info("Unlisted quote, returning null");
            return null;
        }

        $quote = $checkCache ? $this->getCachedQuote($symbol) : null;

        if (!empty($quote)) {
            LOG::info("FinanceAPI->getQuote($symbol) from cache");
        } else {
            LOG::info("FinanceAPI->getQuote($symbol) from FinanceAPI");

            $client = $this->getClient();

            try {
                $quote = $client->getQuote($symbol);
                if (!empty($quote)) {
                    $this->cacheQuote($quote);
                }
            } catch (\Exception $e) {
                LOG::warning("Couldn't get quote for symbol $symbol. "
                             . "Exception message: " . $e->getMessage());
            }
        }

        if (empty($quote) || !($quote instanceof Quote)) {
            LOG::warning("Invalid quote for symbol $symbol");
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
            LOG::info("FinanceAPI->getQuotes(" . implode(', ', $symbols)
                      . ") from cache");
        } else {
            LOG::info("FinanceAPI->getQuotes(" . implode(', ', $symbols)
                      . ") from FinanceAPI, missingCachedSymbols: "
                      . implode(', ', $missingCachedSymbols));

            $client = $this->getClient();

            try {
                $quotes = $client->getQuotes($symbols);
                $this->cacheQuotes($quotes);
            } catch (\Exception $e) {
                LOG::warning("Couldn't get quotes for symbols "
                             . join(', ', $symbols)
                             . ". Exception message: " . $e->getMessage());
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
        array $currencyPairs, bool $checkCache = true): ?array
    {
        $missingCachedSymbols = [];
        $quotes = [];
        $symbols = [];
        $obsoleteSymbols = config('general.obsolete_symbols');

        foreach ($currencyPairs as $currencyPair) {
            $symbol = strtoupper($currencyPair[0])
                . strtoupper($currencyPair[1]) . '=X';
            $symbols[] = $symbol;
            $quote = $checkCache ? $this->getCachedQuote($symbol) : null;
            if (!empty($quote)) {
                $quotes[] = $quote;
            } else {
                if (!in_array($symbol, $obsoleteSymbols)) {
                    $missingCachedSymbols[] = $symbol;
                }
            }
        }

        if (empty($missingCachedSymbols)) {
            LOG::info("FinanceAPI->getExchangeRates(" . implode(', ', $symbols)
                      . ") from cache");
        } else {
            LOG::info("FinanceAPI->getExchangeRates(" . implode(', ', $symbols)
                      . ") from FinanceAPI, missingCachedSymbols: "
                      . implode(', ', $missingCachedSymbols));

            $client = $this->getClient();

            try {
                $quotes = $client->getExchangeRates($currencyPairs);
                $this->cacheQuotes($quotes);
            } catch (\Exception $e) {
                LOG::warning("Couldn't get exchange rates for currencyPairs" .
                    print_r($currencyPairs, true) .
                    ". Exception message: " . $e->getMessage());
            }
        }

        if (empty($quotes) || !is_array($quotes) ||
            !($quotes[0] instanceof Quote)
        ) {
            return null;
        }

        return $quotes;
    }

    public function getHistoricalQuoteData(Quote $quote,
        \DateTime $timestamp, bool $checkCache = true): ?HistoricalData
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

        $historicalData = $checkCache
            ? $this->getCachedHistoricalData($symbol, $timestamp->format('Y-m-d'))
            : null;

        if (!empty($historicalData)) {
            LOG::info("FinanceAPI->getHistoricalQuoteData($symbol, "
                      . $timestamp->format('Y-m-d') . ") from cache");
        } else {
            LOG::info("FinanceAPI->getHistoricalQuoteData($symbol, "
                      . $timestamp->format('Y-m-d') . ") from FinanceAPI");

            $client = $this->getClient();

            try {
                $historicalDataResponse = $client->getHistoricalQuoteData($symbol,
                    $interval, $startDate, $endDate);

                if (empty($historicalDataResponse)
                    || !is_array($historicalDataResponse)
                    || !($historicalDataResponse[0] instanceof HistoricalData)
                ) {
                    return null;
                }
                $historicalData = $historicalDataResponse[0];

                $this->cacheHistoricalData($symbol,
                    $historicalData, $timestamp->format('Y-m-d'));
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

    public function cacheQuotes(array $quotes): int
    {
        $numCached = 0;
        foreach ($quotes as $quote) {
            if ($this->cacheQuote($quote)) {
                $numCached++;
            }
        }
        return $numCached;
    }

    public function cacheQuote(Quote $quote): bool
    {
        $symbol = $quote->getSymbol();
        $key = 'QUOTE_' . $symbol;
        $value = serialize($quote);
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

    public function cacheHistoricalData(
        string $symbol, HistoricalData $historicalData, string $date): bool
    {
        $key = 'HISTORICAL_DATA_' . $symbol . '_' . $date;
        $value = serialize($historicalData);
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

