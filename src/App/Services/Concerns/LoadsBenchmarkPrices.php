<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Concerns;

use Carbon\Carbon;

use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\ChartsBuilder;
use ovidiuro\myfinance2\App\Services\DipBuyingPlanService;

/**
 * Shared VUSA.AS benchmark price loading for the Dip Buying engine and its backtest, so the live
 * plan and the self-validation pass read the exact same series (and append the same live "today"
 * point) and can never drift. Both consumers live in App\Services, so the model/scope/chart helpers
 * resolve in their namespace.
 */
trait LoadsBenchmarkPrices
{
    /**
     * VUSA.AS daily closes (EUR-listed; raw unit_price is fine for ratios) keyed by Y-m-d, ascending.
     * $from defaults to the configured trailing-peak lookback window; pass an explicit cutoff for the
     * backtest's own from-date. Today's live intraday point is appended when newer than the stored
     * history (see _appendLiveBenchmarkPoint).
     *
     * @param string|null $from  Y-m-d cutoff, or null for the configured peak-lookback window
     *
     * @return array<string, float>
     */
    protected function _loadBenchmarkPrices(?string $from = null): array
    {
        $from ??= Carbon::today()
            ->subYears((int) config('alerts.dip_buying.peak_lookback_years', 3))
            ->format('Y-m-d');

        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', DipBuyingPlanService::BENCHMARK_SYMBOL)
            ->where('date', '>=', $from)
            ->orderBy('date')
            ->select('date', 'unit_price')
            ->get();

        $prices = [];
        foreach ($rows as $row) {
            $dateStr = is_string($row->date) ? substr($row->date, 0, 10) : $row->date->format('Y-m-d');
            $prices[$dateStr] = (float) $row->unit_price;
        }

        $this->_appendLiveBenchmarkPoint($prices);

        return $prices;
    }

    /**
     * Append today's intraday benchmark close to a price series when stats_historical has not yet
     * recorded it. Equity end-of-day bars are ingested the night after the close, so on an open
     * trading day stats_historical trails by a session (more across weekends); the stored symbol chart
     * series already carries an intraday "today" point, which is what the /positions overview plots.
     * Adding it here keeps the VUSA drawdown, and therefore the effective drawdown, in step with the
     * live overview instead of trailing the last completed session. No-op when the live point is
     * missing or not newer than the stored history (so a real recorded close is never overwritten).
     *
     * @param array<string, float> $prices  date => price, ascending (modified in place)
     *
     * @return void
     */
    protected function _appendLiveBenchmarkPoint(array &$prices): void
    {
        $live = ChartsBuilder::getChartSymbolAsArray(DipBuyingPlanService::BENCHMARK_SYMBOL);
        if (empty($live)) {
            return;
        }

        $last = end($live);
        $date = $last['time'] ?? null;
        $val  = isset($last['value']) ? (float) $last['value'] : 0.0;
        if ($date === null || $val <= 0.0) {
            return;
        }

        if (empty($prices) || $date > array_key_last($prices)) {
            $prices[$date] = $val;
        }
    }
}
