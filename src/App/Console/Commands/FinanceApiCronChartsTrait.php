<?php

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\ChartsBuilder;
use ovidiuro\myfinance2\App\Services\Positions;
use ovidiuro\myfinance2\App\Services\Stats;

/**
 * Trait for account overview and charts building operations
 */
trait FinanceApiCronChartsTrait
{
    public function refreshAccountOverview(\DateTimeInterface $date = null): void
    {
        $formattedDate = !empty($date)
            ? $date->format(trans('myfinance2::general.date-format'))
            : '';

        Log::info('START app:finance-api-cron refreshAccountOverview('
                  . $formattedDate . ')');

        $service = new Positions();

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
            $cost = $accountData[$accountId]['total_cost'];
            $change = $accountData[$accountId]['total_change'];
            $currency = $accountData[$accountId]['accountModel']->currency->iso_code;

            // Total Cost in account currency
            Stats::persistStat(
                'A_' . $accountId . '_cost', // symbol
                $cost,
                $currency,
                !empty($date) ? $date : new \DateTime()
            );

            // Total Current Market Value in account currency
            Stats::persistStat(
                'A_' . $accountId . '_mvalue', // symbol
                $accountData[$accountId]['total_market_value'],
                $currency,
                !empty($date) ? $date : new \DateTime()
            );

            // Total Overall Gain in account currency
            Stats::persistStat(
                'A_' . $accountId . '_change', // symbol
                $change,
                $currency,
                !empty($date) ? $date : new \DateTime()
            );

            // Total Cash & Cash Alternatives in Account Currency
            $cashBalance = $accountData[$accountId]['cashBalanceUtils']
                ->getLastCashBalance();
            Stats::persistStat(
                'A_' . $accountId . '_cash', // symbol
                empty($cashBalance) ? 0 : $cashBalance->amount,
                $currency,
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

        self::_buildChartsAccount($chartsToBuildAccounts);
        self::_buildChartsSymbols($chartsToBuildSymbols);

        $message = 'END app:finance-api-cron refreshAccountOverview('
                   . $formattedDate . ') => '
                   . $numAccounts . ' accounts refreshed!';
        Log::info($message);
    }

    private static function _addAccountStatToUserStats(
        array &$dataPoints,
        string $date,
        array $stat
    ): void
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

    private static function _addAccountStatsToUserStats(
        array &$chartsUser,
        int $userId,
        string $metric,
        array $stats
    ): void
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

                self::_addAccountStatToUserStats(
                    $chartsUser[$userId][$metric],
                    $date,
                    $stat
                );
            }
        }
        if (!empty($stats['today_last'])) {
            $date = date(trans('myfinance2::general.date-format'));
            $stat = $stats['today_last'];
            self::_addAccountStatToUserStats(
                $chartsUser[$userId][$metric],
                $date,
                $stat
            );
        }
    }

    private static function _buildChartsAccount(array $chartsToBuildAccounts): void
    {
        $chartsUser = [];

        foreach ($chartsToBuildAccounts as $userId => $accounts) {
            if (empty($chartsUser[$userId])) {
                $chartsUser[$userId] = [];
            }

            foreach ($accounts as $accountId => $value) {
                $metrics = ChartsBuilder::getAccountMetrics();
                $costStats = null;
                $changeStats = null;

                // Accumulate metrics for batch write (improved I/O performance)
                $accountMetricsToWrite = [];

                foreach ($metrics as $metric => $properties) {
                    // Skip changePercentage as it's calculated from aggregated data
                    if ($metric === 'changePercentage') {
                        continue;
                    }

                    $stats = Stats::getQuoteStats('A_' . $accountId . '_' . $metric);

                    // Store cost and change stats for changePercentage calculation.
                    // We need both metrics to calculate the percentage:
                    //      (change / cost) * 100.
                    // This approach avoids fetching the stats twice and ensures we use
                    // the exact same data points for both metrics.
                    if ($metric === 'cost') {
                        $costStats = $stats;
                    } elseif ($metric === 'change') {
                        $changeStats = $stats;
                    }

                    // Accumulate both base and converted metrics for batch write
                    $accountMetricsToWrite[$metric] = $stats;

                    //NOTE Dual currency
                    list($convertedMetric, $convertedStats) =
                        ChartsBuilder::convertAccountStatsToCurrency(
                            $value['accountData'],
                            $metric,
                            $stats
                        );
                    $accountMetricsToWrite[$convertedMetric] = $convertedStats;

                    $currency = $value['accountData']['accountModel']
                        ->currency->iso_code;
                    self::_addAccountStatsToUserStats(
                        $chartsUser,
                        $userId,
                        $metric . '_' . $currency,
                        $stats
                    );
                    self::_addAccountStatsToUserStats(
                        $chartsUser,
                        $userId,
                        $convertedMetric,
                        $convertedStats
                    );
                }

                // Build changePercentage chart for this account.
                // changePercentage is a derived metric calculated from cost and change.
                if (!empty($costStats) && !empty($changeStats)) {
                    $changePercentageStats = self::_calculatePercentageStats(
                        $changeStats,
                        $costStats
                    );

                    // Accumulate changePercentage for batch write
                    $accountMetricsToWrite['changePercentage'] = $changePercentageStats;

                    // Convert changePercentage to the alternate currency (EUR <-> USD)
                    // NOTE: The currency conversion affects the unit_price values
                    // since they're in the account's base currency.
                    // The percentage itself doesn't change,
                    // but we maintain dual currency files for consistency.
                    list($convertedMetric, $convertedStats) =
                        ChartsBuilder::convertAccountStatsToCurrency(
                            $value['accountData'],
                            'changePercentage',
                            $changePercentageStats
                        );
                    $accountMetricsToWrite[$convertedMetric] = $convertedStats;
                }

                // Write all accumulated metrics for this account
                //      in a single batch operation
                // This reduces I/O by combining all metric writes into one operation
                ChartsBuilder::buildChartAccountBatch(
                    $value['accountData'],
                    $accountMetricsToWrite
                );
            }
        }

        // Build all user overview charts in batch operations for improved I/O performance
        foreach ($chartsUser as $userId => $metrics) {
            // Accumulate all metrics for batch write operation
            $userMetricsToWrite = [];

            // Add all aggregated metrics to batch
            foreach ($metrics as $metric => $stats) {
                $userMetricsToWrite[$metric] = $stats;
            }

            // Calculate and add changePercentage metrics
            //      from aggregated change and cost data
            foreach (['EUR', 'USD'] as $currency) {
                $changeMetric = 'change_' . $currency;
                $costMetric = 'cost_' . $currency;

                if (!empty($metrics[$changeMetric]) && !empty($metrics[$costMetric])) {
                    $changePercentageStats = self::_calculatePercentageStats(
                        $metrics[$changeMetric],
                        $metrics[$costMetric],
                        isFlat: true
                    );

                    $userMetricsToWrite['changePercentage_' . $currency] =
                        $changePercentageStats;
                }
            }

            // Write all accumulated metrics for this user in a single batch operation
            // This reduces I/O by combining all metric writes into one operation
            ChartsBuilder::buildChartOverviewUserBatch($userId, $userMetricsToWrite);
        }
    }

    /**
     * Calculate percentage statistics from cost and change data.
     *
     * Handles both structured stats (with 'historical' and 'today_last' keys) and
     * flat date-indexed arrays. Calculates percentage change as (change / cost * 100),
     * defaulting to 0 when cost is 0 to avoid division by zero.
     *
     * @param array $changeStats Change data (structured or flat)
     * @param array $costStats Cost data (structured or flat)
     * @param bool $isFlat True if data is flat date-indexed, false if structured
     * @return array Percentage stats in same format as input
     */
    private static function _calculatePercentageStats(
        array $changeStats,
        array $costStats,
        bool $isFlat = false
    ): array
    {
        if ($isFlat) {
            return self::_calculateFlatPercentageStats($changeStats, $costStats);
        }
        return self::_calculateStructuredPercentageStats($changeStats, $costStats);
    }

    /**
     * Calculate percentages from flat date-indexed cost and change data.
     * @param array $changeStats Date-indexed flat stats: ['2025-12-01' => [...], ...]
     * @param array $costStats Date-indexed flat stats: ['2025-12-01' => [...], ...]
     * @return array percentageStats in same flat format
     */
    private static function _calculateFlatPercentageStats(
        array $changeStats,
        array $costStats
    ): array
    {
        $percentageStats = [];

        foreach ($changeStats as $date => $changeStat) {
            if (!isset($costStats[$date])) {
                continue;
            }

            $cost = $costStats[$date]['unit_price'];
            $change = $changeStat['unit_price'];
            $percentage = ($cost != 0) ? ($change / $cost) * 100 : 0;

            $percentageStats[$date] = [
                'unit_price' => $percentage,
                'currency_iso_code' => $changeStat['currency_iso_code'],
                'num_accounts' => 1,
            ];
        }

        return $percentageStats;
    }

    /**
     * Calculate percentages from structured stats with 'historical' and 'today_last' keys.
     * @param array $changeStats Stats with 'historical' and 'today_last' keys
     * @param array $costStats Stats with 'historical' and 'today_last' keys
     * @return array percentageStats with same structure as input
     */
    private static function _calculateStructuredPercentageStats(
        array $changeStats,
        array $costStats
    ): array
    {
        $percentageStats = ['historical' => []];

        // Process historical data with O(1) lookup using date-keyed map
        if (!empty($costStats['historical']) && !empty($changeStats['historical'])) {
            $changeByDate = array_column($changeStats['historical'], null, 'date');

            foreach ($costStats['historical'] as $costStat) {
                if (empty($costStat['date'])) {
                    continue;
                }

                $date = $costStat['date'];
                $changeStat = $changeByDate[$date] ?? null;

                if ($changeStat !== null) {
                    $cost = $costStat['unit_price'];
                    $change = $changeStat['unit_price'];
                    $percentage = ($cost != 0) ? ($change / $cost) * 100 : 0;

                    $percentageStats['historical'][] = [
                        'date' => $date,
                        'unit_price' => $percentage,
                        'currency_iso_code' => $costStat['currency_iso_code'],
                    ];
                }
            }
        }

        // Process today_last: the most recent data point
        if (!empty($costStats['today_last']) && !empty($changeStats['today_last'])) {
            $cost = $costStats['today_last']['unit_price'];
            $change = $changeStats['today_last']['unit_price'];
            $percentage = ($cost != 0) ? ($change / $cost) * 100 : 0;

            $percentageStats['today_last'] = [
                'date' => date(trans('myfinance2::general.date-format')),
                'unit_price' => $percentage,
                'currency_iso_code' => $costStats['today_last']['currency_iso_code'],
            ];
        }

        return $percentageStats;
    }

    private static function _buildChartsSymbols(array $chartsToBuildSymbols): void
    {
        foreach ($chartsToBuildSymbols as $symbol => $value) {
            $stats = Stats::getQuoteStats($symbol);
            ChartsBuilder::buildChartSymbol($symbol, $stats);

            //NOTE Dual currency
            if (in_array(
                $value['position']['tradeCurrencyModel']->iso_code,
                ['EUR', 'USD']
            )) {
                list($convertedSymbol, $convertedStats) =
                    ChartsBuilder::convertPositionStatsToCurrency(
                        $value['position'],
                        $stats
                    );
                ChartsBuilder::buildChartSymbol($convertedSymbol, $convertedStats);
            } else {
                //NOTE It's not EUR or USD, e.g. GBX / GBP / GBp
                $convertedSymbol1 = $symbol . '_EUR';
                $stats1 = $stats;
                $convertedStats1 = Stats::convertStatsToCurrency($stats1, 'EUR');
                ChartsBuilder::buildChartSymbol($convertedSymbol1, $convertedStats1);

                $convertedSymbol2 = $symbol . '_USD';
                $stats2 = $stats;
                $convertedStats2 = Stats::convertStatsToCurrency($stats2, 'USD');
                ChartsBuilder::buildChartSymbol($convertedSymbol2, $convertedStats2);
            }
        }

        // Build Chart for 'EURUSD=X'
        $symbol = 'EURUSD=X';
        $stats = Stats::getQuoteStats($symbol);
        ChartsBuilder::buildChartSymbol($symbol, $stats);
    }

    /**
     * Run historical account overview for a date range
     */
    public function runHistoricalAccountOverview(string $start, string $end): void
    {
        while ($start <= $end) {
            $this->refreshAccountOverview(new \DateTime($start));
            // Clear Stats cache so next iteration fetches fresh data from DB
            Stats::clearCache();
            $start = (new \DateTime($start))->modify('+1 day')
                ->format(trans('myfinance2::general.date-format'));
        }
    }
}

