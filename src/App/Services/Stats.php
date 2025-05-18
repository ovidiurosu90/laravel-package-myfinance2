<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\Results\Quote;

use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

use ovidiuro\myfinance2\App\Models\StatToday;
use ovidiuro\myfinance2\App\Models\StatHistorical;

class Stats
{
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

        if (date('Y-m-d') != $quoteTimestamp->format('Y-m-d')) {
            // Insert historical stats
            $table = (new StatHistorical())->getTable();
            $dateColumn = 'date';
            $dateFormat = 'Y-m-d';
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
        ", [
            $quoteTimestamp->format($dateFormat),
            $symbol,
            $price,
            $currency,
            $price,
            $currency,
        ]);
        return (bool) $result;
    }
}

