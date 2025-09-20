<?php

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\ChartsBuilder;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Services\Stats;
use ovidiuro\myfinance2\App\Services\Positions;

class FinanceApiCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:finance-api-cron {--historical}
        {--historical-account-overview} {--start=} {--end=}';

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
        $start = $this->option('start');
        $end = $this->option('end');
        $historical = $this->option('historical');
        $historicalAccountOverview = $this->option('historical-account-overview');

        if (!$start && ($historical || $historicalAccountOverview)) {
            Log::error('Missing option --start');
            return;
        }
        if (!$end) {
            $end = date(trans('myfinance2::general.date-format'));
        }

        if ($historical) {
            $this->fetchHistorical($start, $end);
            return;
        }

        if ($historicalAccountOverview) {
            while ($start <= $end) {
                $this->refreshAccountOverview(new \DateTime($start));
                $start = (new \DateTime($start))->modify('+1 day')
                    ->format(trans('myfinance2::general.date-format'));
            }
            return;
        }

        // Not historical
        $this->refreshQuotes();
        $this->refreshExchangeRates();
        $this->refreshAccountOverview();
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
            $obsoleteSymbols);
        if (!empty($unableToFetch) && count($unableToFetch)) {
            $message .= ' Unable to fetch: '
                . implode(', ', array_diff($symbols, $fetchedSymbols));
        }
        Log::info($message);
    }

    public function refreshExchangeRates()
    {
        Log::info('START app:finance-api-cron refreshExchangeRates()');

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
            $obsoleteSymbols);

        if (!empty($unableToFetch) && count($unableToFetch)) {
            $message .= ' Unable to fetch: '
                . implode(', ', array_diff($symbols, $fetchedSymbols));
        }
        Log::info($message);
    }

    public function fetchHistorical(string $start, string $end)
    {
        Log::info("START app:finance-api-cron fetchHistorical($start, $end)");

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
        }

        Log::info('END app:finance-api-cron '
            . "fetchHistorical() => $numHistoricalDataEntries data entries");
    }

    public function refreshAccountOverview(\DateTimeInterface $date = null)
    {
        $formattedDate = !empty($date)
            ? $date->format(trans('myfinance2::general.date-format'))
            : '';

        Log::info('START app:finance-api-cron refreshAccountOverview('
                  . $formattedDate . ')');

        $service = new Positions();
        $service->setWithUser(false);

        // array with items grouped by account and account data
        $data = $service->handle($date);

        if (empty($data) || empty($data['groupedItems'])
            || empty($data['accountData'])
        ) {
            Log::error('END app:finance-api-cron refreshAccountOverview('
                       . $formattedDate . ') => '
                       . "ERROR! We couldn't get the positions! Exiting...");
            return;
        }

        $numAccounts = 0;
        $accountData = $data['accountData'];

        $chartsToBuildAccounts = [];
        $chartsToBuildSymbols = [];

        foreach ($data['groupedItems'] as $accountId => $symbols) {
            // Total Cost in account currency
            Stats::persistStat(
                'A_' . $accountId . '_cost', // symbol
                $accountData[$accountId]['total_cost'],
                $accountData[$accountId]['accountModel']->currency->iso_code,
                !empty($date) ? $date : new \DateTime()
            );

            // Total Current Market Value in account currency
            Stats::persistStat(
                'A_' . $accountId . '_mvalue', // symbol
                $accountData[$accountId]['total_market_value'],
                $accountData[$accountId]['accountModel']->currency->iso_code,
                !empty($date) ? $date : new \DateTime()
            );

            // Total Overall Gain in account currency
            Stats::persistStat(
                'A_' . $accountId . '_change', // symbol
                $accountData[$accountId]['total_change'],
                $accountData[$accountId]['accountModel']->currency->iso_code,
                !empty($date) ? $date : new \DateTime()
            );

            // Total Cash & Cash Alternatives in Account Currency
            $cashBalance = $accountData[$accountId]['cashBalanceUtils']
                ->getLastCashBalance();
            Stats::persistStat(
                'A_' . $accountId . '_cash', // symbol
                empty($cashBalance) ? 0 : $cashBalance->amount,
                $accountData[$accountId]['accountModel']->currency->iso_code,
                !empty($date) ? $date : new \DateTime()
            );

            // Create overview of charts to be built
            $userId = $accountData[$accountId]['accountModel']->user_id;
            if (empty($chartsToBuildAccounts[$userId])) {
                $chartsToBuildAccounts[$userId] = [];
            }
            if (empty($chartsToBuildAccounts[$userId][$accountId])) {
                $chartsToBuildAccounts[$userId][$accountId] = [
                    'accountData' => $accountData[$accountId],
                ];
            }
            foreach ($symbols as $symbol => $position) {
                if (empty($chartsToBuildSymbols[$symbol])) {
                    $chartsToBuildSymbols[$symbol] = [
                        'position' => $position,
                    ];
                }
            }

            $numAccounts++;
        }

        self::buildChartsAccount($chartsToBuildAccounts);
        self::buildChartsSymbols($chartsToBuildSymbols);

        $message = 'END app:finance-api-cron refreshAccountOverview('
                   . $formattedDate . ') => '
                   . $numAccounts . ' accounts refreshed!';
        Log::info($message);
    }

    public static function addAccountStatToUserStats(array &$dataPoints,
        string $date, array $stat)
    {
        if (empty($dataPoints[$date])) {
            $dataPoints[$date] = [
                'unit_price'        => $stat['unit_price'],
                'currency_iso_code' => $stat['currency_iso_code'],
                'num_accounts'      => 1,
            ];
            return;
        }

        // Check if currency matches
        if ($dataPoints[$date]['currency_iso_code'] != $stat['currency_iso_code']) {
            Log::error('Inconsistent currency for stat ' . $stat['symbol']
                . '! Previous currency = '
                . $dataPoints[$date]['currency_iso_code']
                . ', current currency = ' . $stat['currency_iso_code']);
            return;
        }

        // Sum with the previous value
        $dataPoints[$date]['unit_price'] += $stat['unit_price'];
        $dataPoints[$date]['num_accounts']++;
    }

    public static function addAccountStatsToUserStats(array &$chartsUser,
        int $userId, string $metric, array $stats)
    {
        if (empty($chartsUser[$userId])) {
            $chartsUser[$userId] = [];
        }
        if (empty($chartsUser[$userId][$metric])) {
            $chartsUser[$userId][$metric] = [];
        }

        if (!empty($stats['historical']) && is_array($stats['historical'])) {
            foreach ($stats['historical'] as $stat) {
                if (empty($stat) || empty($stat['date'])) {
                    continue;
                }
                $date = $stat['date'];

                if ($date == date(trans('myfinance2::general.date-format'))
                    && !empty($stats['today_last'])
                ) {
                    // skip historical for today if I have another entry
                    continue;
                }

                self::addAccountStatToUserStats($chartsUser[$userId][$metric],
                    $date, $stat);
            }
        }
        if (!empty($stats['today_last'])) {
            $date = date(trans('myfinance2::general.date-format'));
            $stat = $stats['today_last'];
            self::addAccountStatToUserStats($chartsUser[$userId][$metric],
                $date, $stat);
        }
    }

    public static function buildChartsAccount(array $chartsToBuildAccounts)
    {
        $chartsUser = [];

        foreach ($chartsToBuildAccounts as $userId => $accounts) {
            if (empty($chartsUser[$userId])) {
                $chartsUser[$userId] = [];
            }

            foreach ($accounts as $accountId => $value) {
                $metrics = ChartsBuilder::getAccountMetrics();
                foreach ($metrics as $metric => $properties) {
                    $stats = Stats::getQuoteStats(
                        'A_' . $accountId . '_' . $metric);

                    ChartsBuilder::buildChartAccount($value['accountData'],
                        $metric, $stats);

                    //NOTE Dual currency
                    list($convertedMetric, $convertedStats) =
                        ChartsBuilder::convertAccountStatsToCurrency(
                            $value['accountData'], $metric, $stats);
                    ChartsBuilder::buildChartAccount($value['accountData'],
                        $convertedMetric, $convertedStats);

                    $currency = $value['accountData']['accountModel']
                        ->currency->iso_code;
                    self::addAccountStatsToUserStats($chartsUser, $userId,
                        $metric . '_' . $currency, $stats);
                    self::addAccountStatsToUserStats($chartsUser, $userId,
                        $convertedMetric, $convertedStats);
                }
            }
        }

        // Log::debug('chartsUser: ' . print_r($chartsUser, true));
        foreach ($chartsUser as $userId => $metrics) {
            foreach ($metrics as $metric => $stats) {
                ChartsBuilder::buildChartOverviewUser($userId, $metric, $stats);
            }
        }
    }

    public static function buildChartsSymbols(array $chartsToBuildSymbols)
    {
        foreach ($chartsToBuildSymbols as $symbol => $value) {
            $stats = Stats::getQuoteStats($symbol);
            ChartsBuilder::buildChartSymbol($symbol, $stats);

            //NOTE Dual currency
            if (in_array($value['position']['tradeCurrencyModel']->iso_code,
                    ['EUR', 'USD'])
            ) {
                list($convertedSymbol, $convertedStats) =
                    ChartsBuilder::convertPositionStatsToCurrency(
                        $value['position'], $stats);
                ChartsBuilder::buildChartSymbol($convertedSymbol, $convertedStats);
            } else {
                //NOTE It's not EUR or USD, e.g. GBX / GBP / GBp
                $convertedSymbol1 = $symbol . '_EUR';
                $stats1 = $stats;
                $convertedStats1 = Stats::convertStatsToCurrency($stats1, 'EUR');
                ChartsBuilder::buildChartSymbol($convertedSymbol1,
                    $convertedStats1);

                $convertedSymbol2 = $symbol . '_USD';
                $stats2 = $stats;
                $convertedStats2 = Stats::convertStatsToCurrency($stats2, 'USD');
                ChartsBuilder::buildChartSymbol($convertedSymbol2,
                    $convertedStats2);
            }
        }

        // Build Chart for 'EURUSD=X'
        $symbol = 'EURUSD=X';
        $stats = Stats::getQuoteStats($symbol);
        ChartsBuilder::buildChartSymbol($symbol, $stats);
    }
}

