<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * Adds a per-period "what would my P&L be if I had sold at this window's peak" figure to each
 * owned symbol's table_meta, so the quadrant per-period table can show, next to "From peak", the
 * gain/loss you would have locked in selling your held shares at the 3M/6M/1Y/2Y high.
 *
 * The peak is scoped to when you actually held the position:
 *  - If you held the position for the whole period, the period's own high (the exit zone's
 *    peak_price_eur) is used.
 *  - If you only held part of the period (you bought in partway through), the holding sits entirely
 *    inside the period, so the best price you could have sold at is the open position window's peak.
 *    That makes the figure match the position window's peak gain, and the period is flagged
 *    'incomplete' with the overall vs held peak so the view can warn that a higher peak occurred
 *    before you held.
 *
 * The held shares are valued at that peak's EUR price times the held quantity, minus the cost of the
 * held shares (the open window's running-average basis, reset on each full sell-out; cost2 fallback).
 * Watchlist-only rows (no held shares) get null.
 */
class PeakExitPnlBuilder
{
    private const PERIODS = ['3m', '6m', '1y', '2y'];

    /** Calendar length of each period in days, matching DrawdownService's exit-zone windows. */
    private const PERIOD_DAYS = ['3m' => 91, '6m' => 182, '1y' => 365, '2y' => 730];

    /**
     * @param array $items     symbol => quoteData (must already carry 'categorization' and 'table_meta')
     * @param array $eurRates  currency iso => EUR rate (account currency to EUR)
     * @return array same items, each owned symbol's table_meta gaining a 'period_peak_pnl' key:
     *               period => ['eur' => float, 'pct' => ?float, ...incomplete info] | null
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
     * @return array period => ['eur' => float, 'pct' => ?float, ...] | null
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

        $heldQty = $this->_heldQty($positions);
        if ($heldQty <= 0.0)
        {
            return $empty;
        }

        // Cost basis and holding span come from the open position window (running average, reset on
        // each full sell-out, so a rebought symbol is not valued against shares you no longer hold).
        $openWin      = $this->_openWindow($quoteData);
        $costEur      = ($openWin['remaining_cost_eur'] ?? null) ?? $this->_cost2Eur($positions, $eurRates);
        $ownedSince   = $this->_winDate($openWin['start_date'] ?? null);
        $heldPeakEur  = $openWin['peak_price_eur'] ?? null;
        $heldPeakDate = $this->_winDate($openWin['peak_gain_date'] ?? null);
        $today        = Carbon::today();

        $result = [];
        foreach (self::PERIODS as $period)
        {
            $periodPeakEur = $periods[$period]['exit_zone']['peak_price_eur'] ?? null;
            if ($periodPeakEur === null)
            {
                $result[$period] = null;
                continue;
            }

            $cutoff = $today->copy()->subDays(self::PERIOD_DAYS[$period])->format('Y-m-d');

            // Held the whole period (or holding / held peak unknown): the period's own peak is one you
            // actually held through, so value at it directly.
            if ($ownedSince === null || $heldPeakEur === null || $ownedSince <= $cutoff)
            {
                $result[$period] = $this->_pnlAtPeak($periodPeakEur, $heldQty, $costEur);
                continue;
            }

            // Held only part of the period: the holding sits entirely inside it, so the best price you
            // could actually have sold at is the open window peak (matching the position window).
            $pnl       = $this->_pnlAtPeak($heldPeakEur, $heldQty, $costEur);
            $shortfall = round(($periodPeakEur - $heldPeakEur) / $periodPeakEur * 100.0, 1);

            if ($pnl !== null && $shortfall > 0.0)
            {
                $pnl['incomplete']      = true;
                $pnl['period_peak_eur'] = $periodPeakEur;
                $pnl['held_peak_eur']   = $heldPeakEur;
                $pnl['held_peak_date']  = $heldPeakDate;
                $pnl['shortfall_pct']   = $shortfall;
            }

            $result[$period] = $pnl;
        }

        return $result;
    }

    /**
     * The symbol's current open holding window, or [] when none.
     */
    private function _openWindow(array $quoteData): array
    {
        foreach ($quoteData['performance']['windows'] ?? [] as $window)
        {
            if (!empty($window['is_open']))
            {
                return $window;
            }
        }

        return [];
    }

    /**
     * Format a window date (Carbon/DateTime, or already a string) to Y-m-d, or null.
     */
    private function _winDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface)
        {
            return $value->format('Y-m-d');
        }

        return $value !== null ? (string) $value : null;
    }

    /**
     * Total currently held quantity for the symbol across every account it is held in.
     */
    private function _heldQty(array $positions): float
    {
        $qty = 0.0;
        foreach ($positions as $position)
        {
            $qty += (float) ($position['quantity'] ?? 0.0);
        }

        return $qty;
    }

    /**
     * Fallback cost basis: all-buys cost (cost2) of the held shares across every account, in EUR.
     * Mirrors WatchlistSymbolsDashboard::_buildPositionReturns.
     */
    private function _cost2Eur(array $positions, array $eurRates): float
    {
        $costEur = 0.0;
        foreach ($positions as $position)
        {
            $currency = $position['accountModel']->currency->iso_code ?? 'EUR';
            $rate     = (float) ($eurRates[$currency] ?? 1.0);
            $costEur += (float) ($position['cost2_in_account_currency'] ?? 0.0) * $rate;
        }

        return $costEur;
    }

    /**
     * P&L of selling the held shares at the given peak: held quantity valued at the peak's EUR price,
     * minus the held shares' cost. Percentage is null when cost is non-positive (sell proceeds already
     * exceeded buy cost), matching how the position card suppresses it.
     *
     * @return array{eur: float, pct: ?float}|null
     */
    private function _pnlAtPeak(?float $peakPriceEur, float $heldQty, float $costEur): ?array
    {
        if ($peakPriceEur === null || $peakPriceEur <= 0.0)
        {
            return null;
        }

        $proceedsAtPeak = $peakPriceEur * $heldQty;
        $pnlEur         = $proceedsAtPeak - $costEur;
        $pct            = $costEur > 0.0 ? $pnlEur / $costEur * 100.0 : null;

        return [
            'eur' => round($pnlEur, 2),
            'pct' => $pct !== null ? round($pct, 2) : null,
        ];
    }
}
