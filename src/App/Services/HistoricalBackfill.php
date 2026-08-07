<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;

/**
 * Backfill of raw historical daily closes for a single symbol.
 *
 * Shared by the CLI backfill (app:finance-api-cron --historical) and the
 * "Populate historical data" button in the symbol chart modal. The button path
 * is meant for symbols whose history was wiped (e.g. after a stock split, where
 * the pre-split prices are removed and never refetched), so it rewrites every
 * day it fetches: Stats::persistHistoricalData() upserts, so an existing price
 * for a date is overwritten with the value the API returns now (split-adjusted).
 */
class HistoricalBackfill
{
    /**
     * Default start date of the on-demand (button) backfill. The CLI passes its
     * own --start, so this only applies to the chart modal.
     */
    public const DEFAULT_START_DATE = '2025-01-01';

    /**
     * Fetch and persist the daily closes of one symbol between two dates.
     * Existing days in the range are overwritten with the fetched prices.
     *
     * @return int|null Number of persisted data points, or null when the symbol
     *                  is skipped (delisted/unlisted) or the API returns nothing.
     */
    public static function persistSymbol(
        FinanceAPI $financeAPI,
        string $symbol,
        string $start,
        string $end
    ): ?int
    {
        if (!self::isBackfillable($symbol)) {
            return null;
        }

        $quote = $financeAPI->getQuote($symbol);
        if (empty($quote)) {
            return null;
        }

        $historicalDataArray = $financeAPI->getHistoricalPeriodQuoteData(
            $quote,
            new \DateTime($start),
            new \DateTime($end)
        );

        if (empty($historicalDataArray)) {
            return null;
        }

        $numEntries = 0;
        foreach ($historicalDataArray as $historicalData) {
            if (Stats::persistHistoricalData($quote, $historicalData)) {
                $numEntries++;
            }
        }

        return $numEntries;
    }

    /**
     * On-demand backfill for one symbol: persists the daily closes, then rebuilds
     * the symbol's cached chart file so every consumer of the stored series (the
     * inline row charts, the chart modal, the orders form panel) picks up the
     * refilled history without waiting for the next cron run.
     *
     * A backfill only adds days *before* the last stored point, so the staleness
     * probe in AjaxController@getSymbolChart cannot detect it; the rebuild has to
     * be explicit here.
     *
     * @return int Number of persisted data points (0 when nothing was fetched).
     */
    public static function rebuildSymbol(string $symbol, string $start, string $end): int
    {
        Log::info("START HistoricalBackfill::rebuildSymbol($symbol, $start, $end)");

        $numEntries = self::persistSymbol(new FinanceAPI(), $symbol, $start, $end) ?? 0;

        if ($numEntries > 0) {
            // The rows just written must be visible to the chart build, so drop the
            // in-request stats cache before reading the symbol's stats back.
            Stats::clearCache();
            ChartsBuilder::buildChartSymbol($symbol, Stats::getQuoteStats($symbol));
        }

        Log::info("END HistoricalBackfill::rebuildSymbol($symbol) => $numEntries data entries");

        return $numEntries;
    }

    /**
     * Whether the symbol has an API price history to fetch at all. Delisted
     * symbols have no valid quotes; unlisted ones are priced from the FMV values
     * in config rather than the API. Both are skipped rather than failed, so the
     * on-demand caller can tell "nothing to fetch here" apart from "the fetch
     * came back empty".
     */
    public static function isBackfillable(string $symbol): bool
    {
        $delistedSymbols = config('trades.delisted_symbols', []);

        return !in_array($symbol, $delistedSymbols, true)
            && !FinanceAPI::isUnlisted($symbol);
    }

    /**
     * Whether the symbol is one of the portfolio's own (traded, held, watched or
     * alerted on). Guards the on-demand backfill so it cannot be pointed at an
     * arbitrary ticker.
     */
    public static function isKnownSymbol(string $symbol): bool
    {
        return Trade::where('symbol', $symbol)->exists()
            || WatchlistSymbol::where('symbol', $symbol)->exists()
            || Dividend::where('symbol', $symbol)->exists()
            || PriceAlert::where('symbol', $symbol)->exists();
    }
}
