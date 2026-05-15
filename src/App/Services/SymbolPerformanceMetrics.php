<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

class SymbolPerformanceMetrics
{
    private const FLOAT_EPSILON = 0.0001;

    /**
     * @param array $windows   Finalized windows (with total_gain_eur, percentage_gain, etc.)
     * @param array $dividends Collection of Dividend models for this symbol
     * @param array $eurRates  currency_iso_code => EUR rate
     *
     * METRICS EXPLORED AND REMOVED (kept here to avoid re-treading the same ground):
     *
     * - Year-by-year realized + unrealized gain breakdown: implemented and removed.
     *   The intent was to show how much of the total gain was made in each calendar year.
     *   Problem: unrealized gains/losses are a running stock — all of them are attributed
     *   to the current year regardless of when shares were bought, which distorts every
     *   prior year's figure. The per-window table in the view already provides enough
     *   historical context without this noise.
     *
     * - Sharpe-style risk-adjusted return: considered but not implemented.
     *   Would require daily or monthly return series per window, which we do not store.
     *   StatHistorical has daily prices but querying it per symbol per window at render
     *   time would be too expensive without a dedicated pre-computation step.
     *
     * - Average hold time across windows: trivially derivable from duration_days but
     *   not surfaced — with the typical 1-3 windows per symbol the average adds no
     *   insight over reading the individual window durations.
     */
    public function compute(array $windows, array $dividends, array $eurRates): array
    {
        return [
            'win_rate'                      => $this->_computeWinRate($windows),
            'best_window_index'             => $this->_computeBestWindowIndex($windows),
            'projected_annual_dividend_eur' => $this->_computeProjectedDividend($windows, $dividends, $eurRates),
            're_entry_flags'                => $this->_computeReEntryFlags($windows),
            'time_pattern_summary'          => $this->_computeTimePattern($windows),
        ];
    }

    private function _computeWinRate(array $windows): ?array
    {
        $closedWindows = array_filter($windows, fn($w) => !$w['is_open']);
        if (count($closedWindows) < 2) {
            return null;
        }
        $wins = count(array_filter($closedWindows, fn($w) => $w['total_gain_eur'] > 0.0));
        return ['wins' => $wins, 'completed' => count($closedWindows)];
    }

    private function _computeBestWindowIndex(array $windows): ?int
    {
        $closedWindows = array_filter($windows, fn($w) => !$w['is_open']);
        if (empty($closedWindows)) {
            return null;
        }
        $bestReturn = null;
        $bestIdx = null;
        foreach ($closedWindows as $window) {
            $annualized = $window['annualized_percentage_gain'];
            if ($annualized === null) {
                continue;
            }
            if ($bestReturn === null || $annualized > $bestReturn) {
                $bestReturn = $annualized;
                $bestIdx = $window['index'];
            }
        }
        return $bestIdx;
    }

    private function _computeProjectedDividend(
        array $windows,
        array $dividends,
        array $eurRates
    ): ?float
    {
        $hasOpenWindow = count(array_filter($windows, fn($w) => $w['is_open'])) > 0;
        if (!$hasOpenWindow || empty($dividends)) {
            return null;
        }

        $cutoff = Carbon::now()->subMonths(12);
        $recentTotal = 0.0;
        $count = 0;

        foreach ($dividends as $dividend) {
            if ($dividend->timestamp < $cutoff) {
                continue;
            }
            $accountCurrency = $dividend->accountModel->currency->iso_code;
            $eurRate = $eurRates[$accountCurrency] ?? 1.0;
            $exchangeRate = (float) $dividend->exchange_rate;
            if ($exchangeRate > self::FLOAT_EPSILON) {
                $recentTotal += ((float) $dividend->amount / $exchangeRate
                    - (float) $dividend->fee) * $eurRate;
                $count++;
            }
        }

        return ($count > 0) ? $recentTotal : null;
    }

    private function _computeReEntryFlags(array $windows): array
    {
        $flags = [];
        $prevClosed = null;

        foreach ($windows as $window) {
            if ($prevClosed !== null && $prevClosed['total_gain_eur'] < 0.0) {
                $daysBetween = (int) $prevClosed['end_date']->diffInDays($window['start_date']);
                if ($daysBetween <= 30) {
                    $flags[] = sprintf(
                        'Window %d opened %d days after closing a loss in Window %d',
                        $window['index'],
                        $daysBetween,
                        $prevClosed['index']
                    );
                }
            }
            if (!$window['is_open']) {
                $prevClosed = $window;
            }
        }

        return $flags;
    }

    private function _computeTimePattern(array $windows): ?string
    {
        $sellMonths = [];
        foreach ($windows as $window) {
            if ($window['is_open']) {
                continue;
            }
            foreach ($window['trades'] as $trade) {
                if ($trade->action === 'SELL') {
                    $sellMonths[] = (int) $trade->timestamp->format('n');
                }
            }
        }

        if (count($sellMonths) < 3) {
            return null;
        }

        $quarterCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($sellMonths as $month) {
            $quarter = (int) ceil($month / 3);
            $quarterCounts[$quarter]++;
        }
        arsort($quarterCounts);
        $topQuarter = array_key_first($quarterCounts);
        $quarterLabels = [
            1 => 'Q1 (Jan-Mar)', 2 => 'Q2 (Apr-Jun)',
            3 => 'Q3 (Jul-Sep)', 4 => 'Q4 (Oct-Dec)',
        ];

        return 'Most exits: ' . $quarterLabels[$topQuarter];
    }
}
