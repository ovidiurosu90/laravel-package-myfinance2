<?php

namespace ovidiuro\myfinance2\App\Console\Commands;

use Cache;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Services\Stats;

class FinanceApiCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:finance-api-cron {--historical} {--start=} {--end=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finance API Cron';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $historical = $this->option('historical');

        if ($historical) {
            $start = $this->option('start');
            $end = $this->option('end');
            if (!$start) {
                Log::error('Missing option --start');
            }
            if (!$end) {
                $end = date('Y-m-d');
            }
            $this->fetchHistorical($start, $end);
            return;
        }

        // Not historical
        $this->refreshQuotes();
        $this->refreshExchangeRates();
    }

    public function getAllUsedSymbols(): array
    {
        $dividends = Dividend::withoutGlobalScope(AssignedToUserScope::class)
            ->select('symbol')->distinct()->pluck('symbol')->toArray();
        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->select('symbol')->distinct()->pluck('symbol')->toArray();
        $watchlistSymbols = WatchlistSymbol
            ::withoutGlobalScope(AssignedToUserScope::class)
            ->select('symbol')->distinct()->pluck('symbol')->toArray();

        $symbols = array_unique(array_merge(
            $dividends, $trades, $watchlistSymbols));
        sort($symbols);

        return $symbols;
    }

    public function refreshQuotes()
    {
        Log::info('START app:finance-api-cron refreshQuotes()');
        $symbols = $this->getAllUsedSymbols();
        $financeAPI = new FinanceAPI();
        $quotes = $financeAPI->getQuotes($symbols, false); // don't check cache

        if (empty($quotes)) {
            Log::error('END app:finance-api-cron refreshQuotes() =>'
                       . "ERROR! We couldn't get the quotes! Exiting...");
            return;
        }

        $fetchedSymbols = [];
        foreach ($quotes as $quote) {
            $fetchedSymbols[] = $quote->getSymbol();
        }

        $message =
            'END app:finance-api-cron refreshQuotes() => '
            . count($symbols) . ' symbols; ' . count($fetchedSymbols) . ' fetched!';

        $obsoleteSymbols = config('general.obsolete_symbols');
        $unableToFetch = array_diff(
            array_map('strtoupper', $symbols),
            array_map('strtoupper', $fetchedSymbols),
            $obsoleteSymbols);
        if (!empty($unableToFetch) && count($unableToFetch)) {
            $message .= ' Unable to fetch: '
                . implode(', ', array_diff($symbols, $fetchedSymbols));
        }
        Log::info($message);
    }

    public function refreshExchangeRates()
    {
        Log::info('START app:finance-api-cron '
            . 'refreshExchangeRates()');

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
            Log::error("ERROR: Unable to get currency pairs!"
                . "Result: " . print_r($result, true));
            return;
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

        $financeAPI = new FinanceAPI();
        // don't check cache
        $quotes = $financeAPI->getExchangeRates($currencyPairs, false);

        if (empty($quotes)) {
            Log::error('END app:finance-api-cron refreshExchangeRates() =>'
                       . "ERROR! We couldn't get the exchange rates! Exiting...");
            return;
        }

        $fetchedSymbols = [];
        foreach ($quotes as $quote) {
            $fetchedSymbols[] = $quote->getSymbol();
        }

        $message =
            'END app:finance-api-cron refreshExchangeRates() => '
            . count($symbols) . ' symbols; ' . count($fetchedSymbols) . ' fetched!';

        $obsoleteSymbols = config('general.obsolete_symbols');
        $unableToFetch = array_diff(
            array_map('strtoupper', $symbols),
            array_map('strtoupper', $fetchedSymbols),
            $obsoleteSymbols);
        if (!empty($unableToFetch) && count($unableToFetch)) {
            $message .= ' Unable to fetch: '
                . implode(', ', array_diff($symbols, $fetchedSymbols));
        }
        Log::info($message);
    }

    public function fetchHistorical(string $start, string $end)
    {
        Log::info('START app:finance-api-cron '
            . "fetchHistorical($start, $end)");

        $symbols = $this->getAllUsedSymbols();
        $financeAPI = new FinanceAPI();
        $numHistoricalDataEntries = 0;
        foreach ($symbols as $symbol) {
            $quote = $financeAPI->getQuote($symbol);
            if (empty($quote)) {
                continue;
            }

            $historicalDataArray = $financeAPI->getHistoricalPeriodQuoteData(
                $quote, new \DateTime($start), new \DateTime($end));

            if (empty($historicalDataArray)) {
                continue;
            }

            foreach ($historicalDataArray as $historicalData) {
                if (Stats::persistHistoricalData($quote, $historicalData)) {
                    $numHistoricalDataEntries++;
                }
            }

            // LOG::debug('historicalData');
            // LOG::debug(var_export($historicalData, true));
            $price = $historicalData->getClose();
            $quoteTimestamp = $historicalData->getDate();

        }

        Log::info('END app:finance-api-cron '
            . "fetchHistorical() => $numHistoricalDataEntries data entries");
    }
}

