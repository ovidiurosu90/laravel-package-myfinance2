<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Adds a per-period "what would my P&L be if I had sold at this window's peak" figure to each
 * owned symbol's table_meta, so the quadrant per-period table can show, next to "From peak", the
 * gain/loss you would have locked in selling your held shares at the 3M/6M/1Y/2Y high.
 *
 * The peak itself is market-only (the same window high "From peak" reports). This turns that into a
 * money figure by valuing your currently held shares at that peak price and comparing against their
 * true purchase cost (cost2), exactly the basis behind the position card's "Unrealized Gain".
 *
 * Everything is computed in EUR using the same per-account aggregation as
 * WatchlistSymbolsDashboard::_buildPositionReturns, so a symbol held across accounts in different
 * currencies still yields one figure. Watchlist-only rows (no held shares) get null.
 */
class PeakExitPnlBuilder
{
    private const PERIODS = ['3m', '6m', '1y', '2y'];

    /**
     * @param array $items     symbol => quoteData (must already carry 'categorization' and 'table_meta')
     * @param array $eurRates  currency iso => EUR rate (account currency to EUR)
     * @return array same items, each owned symbol's table_meta gaining a 'period_peak_pnl' key:
     *               period => ['eur' => float, 'pct' => ?float] | null
     */
    public function attach(array $items, array $eurRates): array
    {
        foreach ($items as $symbol => $quoteData)
        {
            $items[$symbol]['table_meta']['period_peak_pnl'] =
                $this->_forSymbol($quoteData, $eurRates);
        }

        return $items;
    }

    /**
     * @return array period => ['eur' => float, 'pct' => ?float] | null
     */
    private function _forSymbol(array $quoteData, array $eurRates): array
    {
        $positions = $quoteData['open_positions'] ?? [];
        $periods   = $quoteData['categorization']['periods'] ?? [];

        $empty = array_fill_keys(self::PERIODS, null);
        if (empty($positions) || empty($periods))
        {
            return $empty;
        }

        [$mvalueEur, $costEur] = $this->_aggregateEur($positions, $eurRates);
        if ($mvalueEur <= 0.0)
        {
            return $empty;
        }

        $result = [];
        foreach (self::PERIODS as $period)
        {
            $proximityPct = $periods[$period]['exit_zone']['proximity_pct'] ?? null;
            $result[$period] = $this->_pnlAtPeak($mvalueEur, $costEur, $proximityPct);
        }

        return $result;
    }

    /**
     * Sum current market value and true cost (cost2) of the held shares across every account the
     * symbol is held in, converted to EUR. Mirrors WatchlistSymbolsDashboard::_buildPositionReturns.
     *
     * @return array{0: float, 1: float} [mvalueEur, costEur]
     */
    private function _aggregateEur(array $positions, array $eurRates): array
    {
        $mvalueEur = 0.0;
        $costEur   = 0.0;

        foreach ($positions as $position)
        {
            $currency = $position['accountModel']->currency->iso_code ?? 'EUR';
            $rate     = (float) ($eurRates[$currency] ?? 1.0);
            $mvalueEur += (float) ($position['market_value_in_account_currency'] ?? 0.0) * $rate;
            $costEur   += (float) ($position['cost2_in_account_currency'] ?? 0.0) * $rate;
        }

        return [$mvalueEur, $costEur];
    }

    /**
     * P&L of selling the held shares at the window peak. The peak/current price ratio is recovered
     * from "From peak" (proximity_pct = (current/peak - 1) * 100), so proceeds at the peak are the
     * current market value scaled by peak/current. Percentage is null when cost is non-positive
     * (sell proceeds already exceeded buy cost), matching how the position card suppresses it.
     *
     * @return array{eur: float, pct: ?float}|null
     */
    private function _pnlAtPeak(float $mvalueEur, float $costEur, ?float $proximityPct): ?array
    {
        // proximity <= -100 would mean a zero/negative current price; not a usable peak ratio.
        if ($proximityPct === null || $proximityPct <= -100.0)
        {
            return null;
        }

        $peakOverCurrent  = 1.0 / (1.0 + $proximityPct / 100.0);
        $proceedsAtPeak   = $mvalueEur * $peakOverCurrent;
        $pnlEur           = $proceedsAtPeak - $costEur;
        $pct              = $costEur > 0.0 ? $pnlEur / $costEur * 100.0 : null;

        return [
            'eur' => round($pnlEur, 2),
            'pct' => $pct !== null ? round($pct, 2) : null,
        ];
    }
}
