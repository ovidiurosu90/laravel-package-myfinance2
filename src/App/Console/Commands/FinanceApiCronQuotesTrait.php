<?php

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Services\Stats;

/**
 * Trait for quotes and exchange rates refresh operations
 */
trait FinanceApiCronQuotesTrait
{
    public function getAllUsedSymbols(): array
    {
        $dividends = Dividend::select('symbol')->distinct()->pluck('symbol')->toArray();
        $trades = Trade::select('symbol')->distinct()->pluck('symbol')->toArray();
        $watchlistSymbols = WatchlistSymbol::select('symbol')
            ->distinct()
            ->pluck('symbol')
            ->toArray();

        $symbols = array_unique(array_merge($dividends, $trades, $watchlistSymbols));
        sort($symbols);

        return $symbols;
    }

    public function refreshQuotes(): void
    {
        Log::info('START app:finance-api-cron refreshQuotes()');
        $symbols = $this->getAllUsedSymbols();
        $financeAPI = new FinanceAPI();
        $quotes = $financeAPI->getQuotes($symbols, false); // don't check cache

        if (empty($quotes)) {
            Log::error('END app:finance-api-cron refreshQuotes() => '
                       . "ERROR! We couldn't get the quotes! Exiting...");
            return;
        }

        $fetchedSymbols = [];
        foreach ($quotes as $quote) {
            $fetchedSymbols[] = $quote->getSymbol();
        }

        $message = 'END app:finance-api-cron refreshQuotes() => '
                   . count($symbols) . ' symbols, '
                   . count($fetchedSymbols) . ' fetched!';

        $obsoleteSymbols = config('general.obsolete_symbols');
        $unableToFetch = array_diff(
            array_map('strtoupper', $symbols),
            array_map('strtoupper', $fetchedSymbols),
            $obsoleteSymbols
        );
        if (!empty($unableToFetch) && count($unableToFetch)) {
            $message .= ' Unable to fetch: '
                . implode(', ', array_diff($symbols, $fetchedSymbols));
        }
        Log::info($message);
    }

    public function refreshExchangeRates(): void
    {
        Log::info('START app:finance-api-cron refreshExchangeRates()');

        $currencyPairsData = $this->_getCurrencyPairsFromDb();
        if (empty($currencyPairsData)) {
            return;
        }

        $currencyPairs = $currencyPairsData['pairs'];
        $symbols = $currencyPairsData['symbols'];

        $financeAPI = new FinanceAPI();
        // don't check cache
        $quotes = $financeAPI->getExchangeRates($currencyPairs, false);

        if (empty($quotes)) {
            Log::error('END app:finance-api-cron refreshExchangeRates() => '
                       . "ERROR! We couldn't get the exchange rates! Exiting...");
            return;
        }

        $fetchedSymbols = [];
        foreach ($quotes as $quote) {
            $fetchedSymbols[] = $quote->getSymbol();
        }

        $message = 'END app:finance-api-cron refreshExchangeRates() => '
                   . count($symbols) . ' symbols, '
                   . count($fetchedSymbols) . ' fetched!';

        $obsoleteSymbols = config('general.obsolete_symbols');
        $unableToFetch = array_diff(
            array_map('strtoupper', $symbols),
            array_map('strtoupper', $fetchedSymbols),
            $obsoleteSymbols
        );

        if (!empty($unableToFetch) && count($unableToFetch)) {
            $message .= ' Unable to fetch: '
                . implode(', ', array_diff($symbols, $fetchedSymbols));
        }
        Log::info($message);
    }

    public function fetchHistorical(string $start, string $end): void
    {
        Log::info("START app:finance-api-cron fetchHistorical($start, $end)");

        $symbols = $this->getAllUsedSymbols();
        $financeAPI = new FinanceAPI();
        $delistedSymbols = config('trades.delisted_symbols', []);
        $numHistoricalDataEntries = 0;

        foreach ($symbols as $symbol) {
            // Skip delisted symbols - they won't have valid quotes from the API
            if (in_array($symbol, $delistedSymbols, true)) {
                continue;
            }

            // Skip unlisted symbols - they use FMV data from config, not API
            if (FinanceAPI::isUnlisted($symbol)) {
                continue;
            }

            $quote = $financeAPI->getQuote($symbol);
            if (empty($quote)) {
                continue;
            }

            $historicalDataArray = $financeAPI->getHistoricalPeriodQuoteData(
                $quote,
                new \DateTime($start),
                new \DateTime($end)
            );

            if (empty($historicalDataArray)) {
                continue;
            }

            foreach ($historicalDataArray as $historicalData) {
                if (Stats::persistHistoricalData($quote, $historicalData)) {
                    $numHistoricalDataEntries++;
                }
            }
        }

        // Fetch historical exchange rates for the date range
        $numExchangeRateEntries = $this->_fetchHistoricalExchangeRates(
            $financeAPI,
            $start,
            $end
        );

        if ($numExchangeRateEntries !== null) {
            Log::info('END app:finance-api-cron fetchHistorical() => '
                . "$numHistoricalDataEntries data entries, "
                . "$numExchangeRateEntries exchange rate entries");
        } else {
            Log::info('END app:finance-api-cron '
                . "fetchHistorical() => $numHistoricalDataEntries data entries");
        }
    }

    /**
     * Get currency pairs from database
     */
    private function _getCurrencyPairsFromDb(): ?array
    {
        $connection = config('myfinance2.db_connection');
        $result = \DB::connection($connection)->select("
            SELECT ac.iso_code AS account_currency_iso_code,
                tc.iso_code AS trade_currency_iso_code
            FROM trades t
            LEFT OUTER JOIN accounts a ON t.account_id = a.id
            LEFT OUTER JOIN currencies ac ON a.currency_id = ac.id
            LEFT OUTER JOIN currencies tc ON t.trade_currency_id = tc.id
            WHERE ac.iso_code <> tc.iso_code
            GROUP BY 1, 2
            ;
        ");

        if (empty($result) || !isset($result[0]->account_currency_iso_code)
            || !isset($result[0]->trade_currency_iso_code)
        ) {
            Log::error('ERROR: Unable to get currency pairs!'
                       . 'Result: ' . print_r($result, true));
            return null;
        }

        $currenciesReverseMapping = config('general.currencies_reverse_mapping');
        $currencyPairs = [];
        $symbols = [];

        foreach ($result as $resultItem) {
            $currencyPair = [
                $resultItem->account_currency_iso_code,
                $resultItem->trade_currency_iso_code,
            ];

            if (!empty($currenciesReverseMapping[$currencyPair[0]])) {
                $currencyPair[0] = $currenciesReverseMapping[$currencyPair[0]];
            }
            if (!empty($currenciesReverseMapping[$currencyPair[1]])) {
                $currencyPair[1] = $currenciesReverseMapping[$currencyPair[1]];
            }
            $currencyPairs[] = $currencyPair;
            $symbols[] = $currencyPair[0] . $currencyPair[1] . '=X';
        }

        return ['pairs' => $currencyPairs, 'symbols' => $symbols];
    }

    /**
     * Fetch historical exchange rates for a date range
     */
    private function _fetchHistoricalExchangeRates(
        FinanceAPI $financeAPI,
        string $start,
        string $end
    ): ?int
    {
        $currencyPairsData = $this->_getCurrencyPairsFromDb();
        if (empty($currencyPairsData)) {
            return null;
        }

        $currencyPairs = $currencyPairsData['pairs'];
        $symbols = $currencyPairsData['symbols'];

        $currentDate = new \DateTime($start);
        $endDate = new \DateTime($end);
        $numExchangeRateEntries = 0;

        while ($currentDate <= $endDate) {
            $historicalExchangeRates = $financeAPI
                ->getHistoricalExchangeRates($currencyPairs, $currentDate);

            if (!empty($historicalExchangeRates)) {
                // Match historical data with symbols by index
                foreach ($historicalExchangeRates as $index => $historicalData) {
                    if (!empty($historicalData) && isset($symbols[$index])) {
                        $symbol = $symbols[$index];
                        // Get quote for this exchange rate symbol
                        $quote = $financeAPI->getQuote($symbol);
                        if (!empty($quote)
                            && Stats::persistHistoricalData($quote, $historicalData)
                        ) {
                            $numExchangeRateEntries++;
                        }
                    }
                }
            }

            $currentDate->modify('+1 day');
        }

        return $numExchangeRateEntries;
    }
}

