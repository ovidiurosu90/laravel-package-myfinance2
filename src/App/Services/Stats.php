<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\Results\Quote;
use Scheb\YahooFinanceApi\Results\HistoricalData;

use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Models\StatToday;
use ovidiuro\myfinance2\App\Models\StatHistorical;

class Stats
{
    private static $_stats = null;

    private static function _fetchAndPrepareStats()
    {
        if (self::$_stats !== null) {
            return;
        }

        $statsToday = StatToday
            ::withoutGlobalScope(AssignedToUserScope::class)
            ->select()->get()->toArray();
        $statsHistorical = StatHistorical
            ::withoutGlobalScope(AssignedToUserScope::class)
            ->select()->get()->toArray();

        self::$_stats = [];
        foreach ($statsHistorical as $stat) {
            if (empty(self::$_stats[$stat['symbol']])) {
                self::$_stats[$stat['symbol']] = [
                    'historical' => [],
                    'today'      => [],
                    'today_last' => null,
                ];
            }
            self::$_stats[$stat['symbol']]['historical'][] = $stat;
        }

        foreach ($statsToday as $stat) {
            if (empty(self::$_stats[$stat['symbol']])) {
                self::$_stats[$stat['symbol']] = [
                    'historical' => [],
                    'today'      => [],
                    'today_last' => null,
                ];
            }
            self::$_stats[$stat['symbol']]['today'][] = $stat;

            if (empty(self::$_stats[$stat['symbol']]['today_last']['timestamp'])
                || self::$_stats[$stat['symbol']]['today_last']['timestamp'] <
                    $stat['timestamp']
            ) {
                self::$_stats[$stat['symbol']]['today_last'] = $stat;
            }
        }

        // LOG::debug(self::$_stats);
    }

    public static function persistQuote(Quote $quote): bool
    {
        $symbol = $quote->getSymbol();
        $price = $quote->getRegularMarketPrice();
        $currency = $quote->getCurrency();

        $quoteTimestamp = $quote->getRegularMarketTime();
        if (!empty($quoteTimestamp)) {
            $offset = FinanceUtils::get_timezone_offset(
                $quoteTimestamp->getTimezone()->getName());
            $quoteTimestamp->add(
                \DateInterval::createFromDateString((string)$offset . 'seconds'));
        } else {
            LOG::error("Quote for symbol $symbol doesn't have a timestamp!");
            return false;
        }

        $connection = config('myfinance2.db_connection');

        // Insert today's stats
        $table = (new StatToday())->getTable();
        $dateColumn = 'timestamp';
        $dateFormat = 'Y-m-d H:i:s';
        $statsToday = true;

        if (date('Y-m-d') != $quoteTimestamp->format('Y-m-d')) {
            // Insert historical stats
            $table = (new StatHistorical())->getTable();
            $dateColumn = 'date';
            $dateFormat = 'Y-m-d';
            $statsToday = false;
        }

        $result = false;
        \DB::connection($connection)->transaction(function () use (
            $result, $statsToday, $connection, $table, $dateColumn,
            $symbol, $price, $currency, $quoteTimestamp, $dateFormat
        ) {
            //NOTE We only keep one entry per hour
            //NOTE This operation is expensive, so we only run it via cronjob
            if ($statsToday && php_sapi_name() == 'cli') {
                \DB::connection($connection)->statement("
                    DELETE FROM `$table`
                    WHERE `symbol` = ?
                        AND HOUR(`timestamp`) = HOUR(?)
                    ;
                ", [
                    $symbol,
                    $quoteTimestamp->format($dateFormat),
                ]);
            }

            $result = \DB::connection($connection)->statement("
                INSERT INTO `$table`
                    (`$dateColumn`, `symbol`, `unit_price`, `currency_iso_code`,
                     `created_at`)
                VALUES(?, ?, ?, ?, NOW())
                ON DUPLICATE KEY
                UPDATE
                    `unit_price` = ?,
                    `currency_iso_code` = ?,
                    `updated_at` = NOW()
                ;
            ", [
                $quoteTimestamp->format($dateFormat),
                $symbol,
                $price,
                $currency,
                $price,
                $currency,
            ]);
        }, 3); // try max 3 times

        return (bool) $result;
    }


    public static function persistHistoricalData(Quote $quote,
        HistoricalData $historicalData): bool
    {
        $symbol = $quote->getSymbol();
        $date = $historicalData->getDate()->format('Y-m-d');
        $price = $historicalData->getClose();
        $currency = $quote->getCurrency();

        $connection = config('myfinance2.db_connection');
        $table = (new StatHistorical())->getTable();

        $result = \DB::connection($connection)->statement("
            INSERT INTO `$table`
                (`date`, `symbol`, `unit_price`, `currency_iso_code`,
                 `created_at`)
            VALUES(?, ?, ?, ?, NOW())
            ON DUPLICATE KEY
            UPDATE
                `unit_price` = ?,
                `currency_iso_code` = ?,
                `updated_at` = NOW()
            ;
        ", [
            $date,
            $symbol,
            $price,
            $currency,
            $price,
            $currency,
        ]);

        return (bool) $result;
    }

    public static function getQuoteStats(string $symbol): ?array
    {
        self::_fetchAndPrepareStats();

        return !empty(self::$_stats[$symbol]) ? self::$_stats[$symbol] : null;
    }
}

