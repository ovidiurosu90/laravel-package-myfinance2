<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

class SymbolPerformanceService
{
    private const CACHE_TTL = 7200; // 2 hours
    private const CACHE_KEY_PREFIX = 'symbol_performance_v2_u';
    private const FLOAT_EPSILON = 0.0001;

    private array $_eurRates = [];

    public function handle(int $userId): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $userId;
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId)
        {
            return $this->_compute($userId);
        });
    }

    public static function clearCache(int $userId): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $userId);
    }

    private function _compute(int $userId): array
    {
        $trades = $this->_loadTrades($userId);
        if ($trades->isEmpty()) {
            return [];
        }

        $dividends = $this->_loadDividends($userId);
        $dividendsBySymbol = [];
        foreach ($dividends as $dividend) {
            $dividendsBySymbol[$dividend->symbol][] = $dividend;
        }

        $windows = (new SymbolPerformanceWindowBuilder())->build($trades);

        $symbols = array_keys($windows);
        $this->_loadEurRates($trades, $dividends);

        $latestPrices = $this->_loadLatestPrices($symbols);
        $historicalPrices = $this->_loadHistoricalPrices($symbols, $windows);

        foreach ($windows as $symbol => &$symWindows) {
            $this->_computeWindowGains($symWindows);
            $this->_attributeDividendsToWindows(
                $symWindows,
                $dividendsBySymbol[$symbol] ?? []
            );
            $this->_computeUnrealizedGains($symWindows, $latestPrices[$symbol] ?? null);
            $this->_computePeakGains($symWindows, $historicalPrices[$symbol] ?? []);
            $this->_finalizeWindows($symWindows);
        }
        unset($symWindows);

        $result = [];
        foreach ($windows as $symbol => $symWindows) {
            $result[$symbol] = $this->_buildSymbolResult(
                $symWindows,
                $dividendsBySymbol[$symbol] ?? []
            );
        }

        return $result;
    }

    private function _loadTrades(int $userId): Collection
    {
        return Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->with(['accountModel', 'accountModel.currency'])
            ->orderBy('timestamp')
            ->get();
    }

    private function _loadDividends(int $userId): Collection
    {
        return Dividend::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->with(['accountModel', 'accountModel.currency'])
            ->orderBy('timestamp')
            ->get();
    }

    private function _loadEurRates(Collection $trades, Collection $dividends): void
    {
        $this->_eurRates['EUR'] = 1.0;

        $currencies = $trades->map(fn($t) => $t->accountModel->currency->iso_code)
            ->merge($dividends->map(fn($d) => $d->accountModel->currency->iso_code))
            ->unique()
            ->filter(fn($c) => $c !== 'EUR')
            ->values()
            ->all();

        if (empty($currencies)) {
            return;
        }

        $pairs = array_map(fn($c) => "EUR{$c}=X", $currencies);
        $stats = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $pairs)
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy('symbol')
            ->map(fn($items) => $items->first());

        foreach ($currencies as $currency) {
            $stat = $stats->get("EUR{$currency}=X");
            $this->_eurRates[$currency] = ($stat && $stat->unit_price > 0)
                ? 1.0 / (float) $stat->unit_price
                : 1.0;
        }

        // GBp (pence) is used by Yahoo Finance for London-listed symbols.
        // Load EURGBP=X explicitly if not already covered by account currencies.
        if (!isset($this->_eurRates['GBP'])) {
            $gbpStat = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
                ->where('symbol', 'EURGBP=X')
                ->orderBy('date', 'desc')
                ->first();
            $this->_eurRates['GBP'] = ($gbpStat && $gbpStat->unit_price > 0)
                ? 1.0 / (float) $gbpStat->unit_price
                : 1.0;
        }
        $this->_eurRates['GBp'] = $this->_eurRates['GBP'] / 100.0;
    }

    private function _loadLatestPrices(array $symbols): array
    {
        $stats = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->select('symbol', 'unit_price', 'currency_iso_code', 'date')
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy('symbol')
            ->map(fn($items) => $items->first());

        $result = [];
        foreach ($symbols as $symbol) {
            $stat = $stats->get($symbol);
            if ($stat) {
                $result[$symbol] = [
                    'price'    => (float) $stat->unit_price,
                    'currency' => $stat->currency_iso_code,
                ];
            }
        }

        return $result;
    }

    private function _loadHistoricalPrices(array $symbols, array $windows): array
    {
        if (empty($symbols)) {
            return [];
        }

        $minDate = null;
        foreach ($windows as $symWindows) {
            foreach ($symWindows as $window) {
                $windowDate = $window['start_date']->format('Y-m-d');
                if ($minDate === null || $windowDate < $minDate) {
                    $minDate = $windowDate;
                }
            }
        }

        if ($minDate === null) {
            return [];
        }

        $allStats = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->where('date', '>=', $minDate)
            ->select('symbol', 'date', 'unit_price', 'currency_iso_code')
            ->orderBy('date')
            ->get();

        $grouped = [];
        foreach ($allStats as $stat) {
            $sym = $stat->symbol;
            $dateStr = is_string($stat->date) ? substr($stat->date, 0, 10) : $stat->date->format('Y-m-d');
            $grouped[$sym][$dateStr] = [
                'price'    => (float) $stat->unit_price,
                'currency' => $stat->currency_iso_code,
            ];
        }

        return $grouped;
    }

    private function _computeWindowGains(array &$windows): void
    {
        foreach ($windows as &$window) {
            $runningQty = 0.0;
            $runningCostEur = 0.0;
            $realizedGainEur = 0.0;
            $investedEur = 0.0;
            $feesEur = 0.0;
            $realizedGainsPerYear = [];
            foreach ($window['trades'] as $trade) {
                $accountCurrency = $trade->accountModel->currency->iso_code;
                $eurRate = $this->_eurRates[$accountCurrency] ?? 1.0;
                $exchangeRate = (float) $trade->exchange_rate;
                $qty = (float) $trade->quantity;

                $amountAc = ($exchangeRate > 0)
                    ? (1.0 / $exchangeRate) * $qty * (float) $trade->unit_price
                    : 0.0;
                $amountEur = $amountAc * $eurRate;
                $feeEur = (float) $trade->fee * $eurRate;

                if ($trade->action === 'BUY') {
                    $costEur = $amountEur + $feeEur;
                    $investedEur += $costEur;
                    $runningCostEur += $costEur;
                    $runningQty += $qty;
                    $feesEur += $feeEur;
                } elseif ($trade->action === 'SELL' && $runningQty > self::FLOAT_EPSILON) {
                    $proceedsEur = $amountEur - $feeEur;
                    $avgCostEur = $runningCostEur / $runningQty;
                    $gain = $proceedsEur - ($qty * $avgCostEur);
                    $realizedGainEur += $gain;
                    $runningQty -= $qty;
                    $runningCostEur = ($runningQty > self::FLOAT_EPSILON)
                        ? $avgCostEur * $runningQty
                        : 0.0;
                    $feesEur += $feeEur;
                    $year = $trade->timestamp->format('Y');
                    $realizedGainsPerYear[$year] = ($realizedGainsPerYear[$year] ?? 0.0) + $gain;
                }
            }

            $window['realized_gain_eur'] = $realizedGainEur;
            $window['invested_eur'] = $investedEur;
            $window['fees_eur'] = $feesEur;
            $window['remaining_qty'] = $runningQty;
            $window['remaining_cost_eur'] = $runningCostEur;
            $window['dividends_eur'] = 0.0;
            $window['dividends_per_year'] = [];
            $window['realized_gains_per_year'] = $realizedGainsPerYear;
            $window['unrealized_gain_eur'] = 0.0;
            $window['peak_gain_eur'] = null;
            $window['peak_gain_date'] = null;
        }
        unset($window);
    }

    private function _attributeDividendsToWindows(array &$windows, array $dividends): void
    {
        foreach ($dividends as $dividend) {
            $divTime = $dividend->timestamp;
            $accountCurrency = $dividend->accountModel->currency->iso_code;
            $eurRate = $this->_eurRates[$accountCurrency] ?? 1.0;
            $exchangeRate = (float) $dividend->exchange_rate;
            $amountAc = ($exchangeRate > 0)
                ? (float) $dividend->amount / $exchangeRate - (float) $dividend->fee
                : 0.0;
            $amountEur = $amountAc * $eurRate;

            $targetIdx = $this->_findWindowForDate($windows, $divTime);
            $windows[$targetIdx]['dividends_eur'] += $amountEur;
            $year = $divTime->format('Y');
            $windows[$targetIdx]['dividends_per_year'][$year] =
                ($windows[$targetIdx]['dividends_per_year'][$year] ?? 0.0) + $amountEur;
        }
    }

    private function _findWindowForDate(array $windows, Carbon $date): int
    {
        $lastClosedIdx = null;
        $openIdx = null;

        foreach ($windows as $idx => $window) {
            if ($window['is_open']) {
                $openIdx = $idx;
                if ($date >= $window['start_date']) {
                    return $idx;
                }
            } else {
                $endDate = $window['end_date'];
                if ($date >= $window['start_date'] && $date <= $endDate) {
                    return $idx;
                }
                if ($endDate <= $date) {
                    $lastClosedIdx = $idx;
                }
            }
        }

        // Fall back to the open window if present, then the most recent closed window,
        // then the very first window as a last resort (dividend predates all windows).
        return $openIdx ?? $lastClosedIdx ?? array_key_first($windows);
    }

    private function _computeUnrealizedGains(array &$windows, ?array $latestPrice): void
    {
        if ($latestPrice === null) {
            return;
        }

        $priceCurrency = $latestPrice['currency'];
        $eurRate = $this->_eurRates[$priceCurrency] ?? 1.0;
        $priceEur = $latestPrice['price'] * $eurRate;

        foreach ($windows as &$window) {
            if ($window['is_open'] && $window['remaining_qty'] > self::FLOAT_EPSILON) {
                $window['unrealized_gain_eur'] = ($priceEur * $window['remaining_qty'])
                    - $window['remaining_cost_eur'];
            }
        }
        unset($window);
    }

    private function _computePeakGains(array &$windows, array $historicalPrices): void
    {
        if (empty($historicalPrices)) {
            return;
        }

        // Peak gain is only computed for the current open position.
        // For closed windows it would be a pure hindsight metric ("you left money on the table")
        // that is not actionable. For an open position it is actionable: it shows how much
        // has been given back from the high and can inform trailing-stop or trim decisions.
        foreach ($windows as &$window) {
            if (!$window['is_open'] || $window['remaining_qty'] <= self::FLOAT_EPSILON) {
                continue;
            }
            $startStr = $window['start_date']->format('Y-m-d');
            $maxPriceEur = null;
            $maxDateStr = null;

            foreach ($historicalPrices as $dateStr => $priceData) {
                if ($dateStr < $startStr) {
                    continue;
                }
                $eurRate = $this->_eurRates[$priceData['currency']] ?? 1.0;
                $priceEur = $priceData['price'] * $eurRate;
                if ($maxPriceEur === null || $priceEur > $maxPriceEur) {
                    $maxPriceEur = $priceEur;
                    $maxDateStr = $dateStr;
                }
            }

            if ($maxPriceEur !== null) {
                $window['peak_gain_eur'] = ($maxPriceEur * $window['remaining_qty'])
                    - $window['remaining_cost_eur'];
                $window['peak_gain_date'] = Carbon::parse($maxDateStr);
            }
        }
        unset($window);
    }

    private function _finalizeWindows(array &$windows): void
    {
        $today = Carbon::today();
        foreach ($windows as &$window) {
            $window['total_gain_eur'] = $window['realized_gain_eur']
                + $window['unrealized_gain_eur']
                + $window['dividends_eur'];

            $window['percentage_gain'] = ($window['invested_eur'] > self::FLOAT_EPSILON)
                ? ($window['total_gain_eur'] / $window['invested_eur']) * 100.0
                : null;

            $window['peak_gain_percentage'] = (
                $window['peak_gain_eur'] !== null && $window['invested_eur'] > self::FLOAT_EPSILON
            )
                ? ($window['peak_gain_eur'] / $window['invested_eur']) * 100.0
                : null;

            $endDate = $window['is_open'] ? $today : $window['end_date'];
            $window['duration_days'] = (int) $window['start_date']->diffInDays($endDate);
            $window['period_display'] = $this->_formatPeriod($window['duration_days']);
            $window['status'] = $window['is_open'] ? 'Open' : 'Closed';

            $durationYears = $window['duration_days'] / 365.25;
            $window['annualized_percentage_gain'] = (
                $window['percentage_gain'] !== null && $durationYears > self::FLOAT_EPSILON
            )
                ? $window['percentage_gain'] / $durationYears
                : null;
        }
        unset($window);
    }

    private function _formatPeriod(int $days): string
    {
        if ($days < 30) {
            return $days . 'd';
        }
        $months = (int) round($days / 30.44);
        if ($months < 12) {
            return $months . 'm';
        }
        $years = intdiv($months, 12);
        $remMonths = $months % 12;
        return $years . 'y' . ($remMonths > 0 ? ' ' . $remMonths . 'm' : '');
    }

    /**
     * Assembles the full result array for a symbol.
     *
     * METRICS EXPLORED AND REMOVED (kept here to avoid re-treading the same ground):
     *
     * - "Invested" / capital_deployed_eur (displayed as "Invested: €X" in extended metrics):
     *   Removed from the view because it duplicates the "Overall: cost:" badge that already
     *   appears in the primary rows. The field is still computed and returned in case it is
     *   needed elsewhere.
     *
     * - "By year" / gains_per_year (displayed as "By year: 2024 +€X 2025 +€Y"):
     *   Still computed and returned but no longer rendered. It merges realized trade gains
     *   and dividend income per calendar year. For symbols that were never (or rarely) sold,
     *   this produces all-positive yearly figures driven purely by dividends, while the overall
     *   position is deeply underwater — misleading at a glance. The dividend story is better
     *   told by projected_annual_dividend_eur. For multi-window symbols with realized gains,
     *   the per-window breakdown in the table already provides the year context.
     *   If revisiting: consider separating realized_gains_per_year and dividends_per_year
     *   into two distinct display rows so the reader can tell them apart.
     *
     * - Year-by-year realized + unrealized breakdown (never shipped, see SymbolPerformanceMetrics):
     *   Explored in SymbolPerformanceMetrics::compute(). Unrealized losses accumulated over
     *   multiple years would all be attributed to the current year, which distorts the picture.
     *
     * - fees_pct_of_gain: computed and shown, but only when total_gain_eur > 0 (the denominator).
     *   For losing positions the percentage is meaningless (fees did not "eat" a gain that does
     *   not exist), so the field is intentionally suppressed in those cases.
     */
    private function _buildSymbolResult(array $windows, array $dividends): array
    {
        if (empty($windows)) {
            return ['has_data' => false];
        }

        $totalGainEur = array_sum(array_column($windows, 'total_gain_eur'));
        $totalInvestedEur = array_sum(array_column($windows, 'invested_eur'));
        $totalDividendsEur = array_sum(array_column($windows, 'dividends_eur'));
        $totalFeesEur = array_sum(array_column($windows, 'fees_eur'));
        $windowCount = count($windows);

        $percentageGain = ($totalInvestedEur > self::FLOAT_EPSILON)
            ? ($totalGainEur / $totalInvestedEur) * 100.0
            : null;

        $hasOpenWindow = count(array_filter($windows, fn($w) => $w['is_open'])) > 0;
        $totalDays = (int) array_sum(array_column($windows, 'duration_days'));
        if ($windowCount === 1) {
            $holdingPeriodDisplay = $windows[0]['period_display']
                . ($windows[0]['is_open'] ? ' (open)' : '');
        } else {
            $holdingPeriodDisplay = $windowCount . ' windows'
                . ' (' . $this->_formatPeriod($totalDays) . ($hasOpenWindow ? ', open' : '') . ')';
        }

        $gainsPerYear = [];
        foreach ($windows as $window) {
            foreach (($window['realized_gains_per_year'] ?? []) as $year => $gain) {
                $gainsPerYear[$year] = ($gainsPerYear[$year] ?? 0.0) + $gain;
            }
            foreach (($window['dividends_per_year'] ?? []) as $year => $div) {
                $gainsPerYear[$year] = ($gainsPerYear[$year] ?? 0.0) + $div;
            }
        }
        ksort($gainsPerYear);

        $feesPctOfGain = ($totalGainEur > self::FLOAT_EPSILON)
            ? ($totalFeesEur / $totalGainEur) * 100.0
            : null;

        $tradeGainEur = $totalGainEur - $totalDividendsEur;
        $dividendSplitPct = ($totalGainEur > self::FLOAT_EPSILON)
            ? ($totalDividendsEur / $totalGainEur) * 100.0
            : null;
        $tradeSplitPct = ($totalGainEur > self::FLOAT_EPSILON)
            ? ($tradeGainEur / $totalGainEur) * 100.0
            : null;

        $extendedMetrics = (new SymbolPerformanceMetrics())
            ->compute($windows, $dividends, $this->_eurRates);

        return [
            'has_data'                    => true,
            'windows'                     => $windows,
            'window_count'                => $windowCount,
            'total_gain_eur'              => $totalGainEur,
            'total_invested_eur'          => $totalInvestedEur,
            'percentage_gain'             => $percentageGain,
            'holding_period_display'      => $holdingPeriodDisplay,
            'total_days'                  => $totalDays,
            'total_dividends_eur'         => $totalDividendsEur,
            'capital_deployed_eur'        => $totalInvestedEur,
            'dividend_split_pct'          => $dividendSplitPct,
            'trade_split_pct'             => $tradeSplitPct,
            'fees_eur'                    => $totalFeesEur,
            'fees_pct_of_gain'            => $feesPctOfGain,
            'gains_per_year'              => $gainsPerYear,
            'sector'                      => null,
            'win_rate'                    => $extendedMetrics['win_rate'],
            'best_window_index'           => $extendedMetrics['best_window_index'],
            'projected_annual_dividend_eur' => $extendedMetrics['projected_annual_dividend_eur'],
            're_entry_flags'              => $extendedMetrics['re_entry_flags'],
            'time_pattern_summary'        => $extendedMetrics['time_pattern_summary'],
        ];
    }

}
