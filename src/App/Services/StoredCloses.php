<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Daily closes of a symbol from a date to today: the stored history (stats_historical), and where that
 * starts later, the missing span from HistoricalPriceCache (cached for 7 days, never written to the
 * database), the same gap rule as DrawdownService.
 */
final class StoredCloses
{
    /**
     * @return array{prices: array<string, float>, currency: ?string} date => close ascending, ISO currency
     */
    public static function load(string $symbol, string $from): array
    {
        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', $symbol)
            ->where('date', '>=', $from)
            ->orderBy('date')
            ->toBase()
            ->get(['date', 'unit_price', 'currency_iso_code']);

        $prices = [];
        foreach ($rows as $row) {
            $prices[substr((string) $row->date, 0, 10)] = (float) $row->unit_price;
        }
        $currency = $rows->last()->currency_iso_code ?? null;

        $dbFirst = array_key_first($prices);
        if ($dbFirst === null || $dbFirst > $from) {
            $gapTo   = $dbFirst !== null
                ? Carbon::parse($dbFirst)->subDay()->format('Y-m-d')
                : Carbon::today()->format('Y-m-d');
            $fetched = (new HistoricalPriceCache())->fetch($symbol, $from, $gapTo);
            foreach ($fetched['prices'] ?? [] as $date => $price) {
                if ($date >= $from && $date <= $gapTo) {
                    $prices[$date] = (float) $price;
                }
            }
            $currency ??= $fetched['currency'] ?? null;
            ksort($prices);
        }

        return ['prices' => $prices, 'currency' => $currency];
    }

    /**
     * Binary search for the value on the last date <= $day in a date => value map with ascending keys.
     *
     * @param string[] $dates the map's keys (ascending)
     */
    public static function onOrBefore(array $series, array $dates, string $day): ?float
    {
        $lo   = 0;
        $hi   = count($dates) - 1;
        $best = null;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($dates[$mid] <= $day) {
                $best = $dates[$mid];
                $lo   = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $best !== null ? $series[$best] : null;
    }
}
