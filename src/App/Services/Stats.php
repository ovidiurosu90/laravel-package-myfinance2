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
            self::$_stats[$stat['symbol']]['historical'][$stat['date']] = $stat;
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
            /*
            $offset = FinanceUtils::get_timezone_offset(
                $quoteTimestamp->getTimezone()->getName());
            $quoteTimestamp->add(
                \DateInterval::createFromDateString((string)$offset . 'seconds'));
            */
            FinanceUtils::fixTimezone($quote, $quoteTimestamp);
        } else {
            LOG::error("Quote for symbol $symbol doesn't have a timestamp!");
            return false;
        }

        return self::persistStat($symbol, $price, $currency, $quoteTimestamp);
    }


    public static function persistStat($symbol, $price, $currency,
        \DateTimeInterface $date): bool
    {
        $connection = config('myfinance2.db_connection');

        // Insert today's stats
        $table = (new StatToday())->getTable();
        $dateColumn = 'timestamp';
        $dateFormat = trans('myfinance2::general.datetime-format');
        $statsToday = true;

        if (date(trans('myfinance2::general.date-format'))
            != $date->format(trans('myfinance2::general.date-format'))
        ) {
            // Insert historical stats
            $table = (new StatHistorical())->getTable();
            $dateColumn = 'date';
            $dateFormat = trans('myfinance2::general.date-format');
            $statsToday = false;
        }

        $result = false;
        \DB::connection($connection)->transaction(function () use (
            $result, $statsToday, $connection, $table, $dateColumn,
            $symbol, $price, $currency, $date, $dateFormat
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
                    $date->format($dateFormat),
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
                $date->format($dateFormat),
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
        $date = $historicalData->getDate()
            ->format(trans('myfinance2::general.date-format'));
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

    public static function getQuoteStatByDate(string $symbol,
        \DateTimeInterface $date): ?array
    {
        self::_fetchAndPrepareStats();

        if (empty(self::$_stats[$symbol])) {
            return null;
        }

        if (date(trans('myfinance2::general.date-format'))
            != $date->format(trans('myfinance2::general.date-format'))
        ) { // Historical
            $dateString = $date->format(trans('myfinance2::general.date-format'));
            if (!empty(self::$_stats[$symbol]['historical'][$dateString])) {
                return self::$_stats[$symbol]['historical'][$dateString];
            }
        } else { // Today
            if (!empty(self::$_stats[$symbol]['today_last'])) {
                return self::$_stats[$symbol]['today_last'];
            }
        }

        // Symbol found, but no stats for the asked date
        //NOTE there is no data when the market is closed,
        //      so we look for the latest available date
        $maxDaysBefore = 7;
        $currentDaysBefore = 1;

        do {
            $currentDate = clone $date;
            $currentDate = $currentDate
                ->modify('-' . $currentDaysBefore . ' days');

            $dateString = $currentDate
                ->format(trans('myfinance2::general.date-format'));
            if (!empty(self::$_stats[$symbol]['historical'][$dateString])) {
                /*
                LOG::info('We were unable to get the stats for ' . $symbol
                          . ' for the requested date ('
                          . $date->format(trans('myfinance2::general.date-format'))
                          . '), but we found them for date ' . $dateString);
                */
                return self::$_stats[$symbol]['historical'][$dateString];
            } else {
                /*
                LOG::info('We were unable to get the stats for ' . $symbol
                          . ' for date ' . $dateString);
                */
            }
            $currentDaysBefore++;
        } while (empty($results)
                 && $currentDaysBefore <= $maxDaysBefore);

        LOG::error('We were unable to get the stats for ' . $symbol
                  . ' for the requested date ('
                  . $date->format(trans('myfinance2::general.date-format'))
                  . ') or before! This should never happen! '
                  . 'We still failed after all these tries!');
        return null;
    }

    // Example symbol: 'EURUSD=X'
    public static function getStatHistoricalBySymbolAndDate(string $symbol,
        \DateTimeInterface $date): ?StatHistorical
    {
        return StatHistorical
            ::withoutGlobalScope(AssignedToUserScope::class)
            ->select()
            ->where('symbol', $symbol)
            ->where('date', $date->format(trans('myfinance2::general.date-format')))
            ->first();
    }

    public static function convertStatsToCurrency(array $stats, string $currency)
        :array
    {
        if (!empty($stats['historical'])) {
            foreach ($stats['historical'] as $key => $stat) {
                $stats['historical'][$key] = self::convertStatToCurrency($stat,
                    $currency);
            }
        }
        if (!empty($stats['today'])) {
            foreach ($stats['today'] as $key => $stat) {
                $stats['today'][$key] = self::convertStatToCurrency($stat,
                    $currency);
            }
        }
        if (!empty($stats['today_last'])) {
            $stats['today_last'] = self::convertStatToCurrency($stats['today_last'],
                $currency);
        }
        return $stats;
    }

    public static function convertStatToCurrency(array $stat, string $currency)
        :?array
    {
        if (empty($stat) || empty($stat['currency_iso_code'])
            || $stat['currency_iso_code'] == $currency
        ) {
            return $stat;
        }

        $date = null;
        if (!empty($stat['date'])) {
            $date = new \DateTime($stat['date']);
        } elseif (!empty($stat['timestamp'])) {
            $date = new \DateTime($stat['timestamp']);
        } else {
            Log::error('Unexpected stat with unknown date! stat: '
                       . var_dump($stat, true));
            return null;
        }

        if ($stat['currency_iso_code'] == 'EUR' && $currency == 'USD') {
            $symbol = 'EURUSD=X';
            $exchangeRateStat = self::getQuoteStatByDate($symbol, $date);
            if (empty($exchangeRateStat)) {
                return null;
            }
            $exchangeRate = $exchangeRateStat['unit_price'];
            $stat['unit_price'] = $stat['unit_price'] * $exchangeRate;
            $stat['currency_iso_code'] = 'USD';
            return $stat;
        }

        if ($stat['currency_iso_code'] == 'USD' && $currency == 'EUR') {
            $symbol = 'EURUSD=X';
            $exchangeRateStat = self::getQuoteStatByDate($symbol, $date);
            if (empty($exchangeRateStat)) {
                return null;
            }
            $exchangeRate = $exchangeRateStat['unit_price'];
            $stat['unit_price'] = $stat['unit_price'] / $exchangeRate;
            $stat['currency_iso_code'] = 'EUR';
            return $stat;
        }

        if ($stat['currency_iso_code'] == 'GBp' && $currency == 'EUR') {
            $symbol = 'EURGBP=X';
            $exchangeRateStat = self::getQuoteStatByDate($symbol, $date);
            if (empty($exchangeRateStat)) {
                return null;
            }
            $exchangeRate = $exchangeRateStat['unit_price'];
            $stat['unit_price'] = $stat['unit_price'] / $exchangeRate;
            $stat['currency_iso_code'] = 'EUR';
            return $stat;
        }

        if ($stat['currency_iso_code'] == 'GBp' && $currency == 'USD') {
            $symbol1 = 'EURGBP=X';
            $exchangeRateStat1 = self::getQuoteStatByDate($symbol1, $date);
            if (empty($exchangeRateStat1)) {
                return null;
            }
            $exchangeRate1 = $exchangeRateStat1['unit_price'];

            //NOTE We do this in 2 exchanges as we don't have USDGBP
            $symbol2 = 'EURUSD=X';
            $exchangeRateStat2 = self::getQuoteStatByDate($symbol2, $date);
            if (empty($exchangeRateStat2)) {
                return null;
            }
            $exchangeRate2 = $exchangeRateStat2['unit_price'];

            $stat['unit_price'] = $stat['unit_price']
                / $exchangeRate1 * $exchangeRate2;
            $stat['currency_iso_code'] = 'USD';
            return $stat;
        }

        Log::error("Unexpected currency $currency for statCurrency "
                   . $stat['currency_iso_code'] . " in convertStatToCurrency()");
        return null;
    }
}

