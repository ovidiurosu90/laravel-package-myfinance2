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
    // Minimum holding days before a money-weighted XIRR is emitted. Below this an annualized rate
    // is mathematically meaningless, so XIRR is withheld. Public so views can explain the gap.
    public const MIN_ANNUALIZED_DAYS = 30;
    // Minimum holding days before a time-weighted CAGR (gain/y) is emitted. Below a full year,
    // compounding a sub-year window extrapolates and inflates the rate, so the raw cumulative
    // return is shown instead. Public so views can flag sub-year holds as provisional.
    public const MIN_CAGR_DAYS = 365;

    private array $_eurRates = [];

    public function handle(int $userId): array
    {
        // Caching is intentionally disabled while the returns framework (CAGR / XIRR /
        // same-window benchmark) is being finalised, so formula changes appear on the next
        // page load without a cache clear. To re-enable, wrap the compute in:
        //   return Cache::remember(self::CACHE_KEY_PREFIX . $userId, self::CACHE_TTL,
        //       fn () => $this->_compute($userId));
        return $this->_compute($userId);
    }

    public static function clearCache(int $userId): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $userId);
    }

    /**
     * Patches open-window unrealized gains in a cached result using live quote prices,
     * then recomputes all derived metrics so the performance row matches the live positions card.
     *
     * @param array $result      The array returned by handle() — modified in place.
     * @param array $liveQuotes  Map of symbol => ['price' => float, 'currency' => string].
     */
    public function applyLivePrices(array &$result, array $liveQuotes, array $liveEurRates = []): void
    {
        $currencies = array_unique(array_column($liveQuotes, 'currency'));
        $missing = array_values(array_filter($currencies, fn($c) => !isset($liveEurRates[$c])));
        $dbRates = !empty($missing) ? $this->_loadEurRatesForCurrencies($missing) : [];
        $eurRates = array_merge($dbRates, $liveEurRates);

        foreach ($liveQuotes as $symbol => $quote) {
            if (empty($result[$symbol]['has_data'])) {
                continue;
            }

            $eurRate = $eurRates[$quote['currency']] ?? 1.0;
            $priceEur = $quote['price'] * $eurRate;
            $changed = false;

            $windows = &$result[$symbol]['windows'];
            foreach ($windows as &$window) {
                if (!$window['is_open'] || $window['remaining_qty'] <= self::FLOAT_EPSILON) {
                    continue;
                }
                $newUnrealized = ($priceEur * $window['remaining_qty']) - $window['remaining_cost_eur'];
                if (abs($newUnrealized - $window['unrealized_gain_eur']) < self::FLOAT_EPSILON) {
                    continue;
                }
                $window['unrealized_gain_eur'] = $newUnrealized;
                $window['total_gain_eur'] = $window['realized_gain_eur']
                    + $window['unrealized_gain_eur']
                    + $window['dividends_eur'];
                $window['percentage_gain'] = ($window['invested_eur'] > self::FLOAT_EPSILON)
                    ? ($window['total_gain_eur'] / $window['invested_eur']) * 100.0
                    : null;
                $tooShort = $window['duration_days'] < self::MIN_ANNUALIZED_DAYS;
                $window['annualized_gain_short_window'] = $tooShort;
                $wAnn = $this->_annualizeReturn(
                    $window['percentage_gain'],
                    $window['duration_days'],
                    $window['total_gain_eur'],
                    $window['invested_eur']
                );
                $window['annualized_percentage_gain'] = $wAnn['pct'];
                $window['annualized_gain_eur']        = $wAnn['eur'];
                $changed = true;
            }
            unset($window);

            if (!$changed) {
                continue;
            }

            $totalGainEur     = array_sum(array_column($windows, 'total_gain_eur'));
            $totalInvestedEur = $result[$symbol]['total_invested_eur'];
            $totalFeesEur     = $result[$symbol]['fees_eur'];
            $totalDividendsEur = $result[$symbol]['total_dividends_eur'];
            $totalDays        = $result[$symbol]['total_days'];

            $percentageGain = ($totalInvestedEur > self::FLOAT_EPSILON)
                ? ($totalGainEur / $totalInvestedEur) * 100.0
                : null;
            $tradeGainEur = $totalGainEur - $totalDividendsEur;

            $result[$symbol]['total_gain_eur']             = $totalGainEur;
            $result[$symbol]['percentage_gain']            = $percentageGain;
            $tooShortSymbol = $totalDays < self::MIN_ANNUALIZED_DAYS;
            $result[$symbol]['annualized_gain_short_window'] = $tooShortSymbol;
            // Recompute the overall annualized return with the live-priced windows, using the
            // same blended CAGR as the cron build (total return annualized over time held; never
            // chained, so multi-window positions are not inflated). The money-weighted XIRR is
            // recomputed too, so the open window's live market value is reflected in both figures.
            $overallAnn = $this->_annualizeReturn(
                $percentageGain,
                $totalDays,
                $totalGainEur,
                $totalInvestedEur
            );
            $result[$symbol]['annualized_gain_eur']        = $overallAnn['eur'];
            $result[$symbol]['annualized_percentage_gain'] = $overallAnn['pct'];
            $result[$symbol]['xirr_pct']                   = $this->_symbolXirr($windows, $totalDays);
            $result[$symbol]['fees_pct_of_gain']           = ($totalGainEur > self::FLOAT_EPSILON)
                ? ($totalFeesEur / $totalGainEur) * 100.0 : null;
            $result[$symbol]['dividend_split_pct']         = ($totalGainEur > self::FLOAT_EPSILON)
                ? ($totalDividendsEur / $totalGainEur) * 100.0 : null;
            $result[$symbol]['trade_split_pct']            = ($totalGainEur > self::FLOAT_EPSILON)
                ? ($tradeGainEur / $totalGainEur) * 100.0 : null;
        }
    }

    private function _loadEurRatesForCurrencies(array $currencies): array
    {
        $eurRates = ['EUR' => 1.0];
        $penceRequested = in_array('GBp', $currencies) || in_array('GBX', $currencies);
        $needed = array_filter($currencies, fn($c) => !in_array($c, ['EUR', 'GBp', 'GBX']));

        if (!empty($needed)) {
            $pairs = array_map(fn($c) => "EUR{$c}=X", $needed);
            $stats = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
                ->whereIn('symbol', $pairs)
                ->orderBy('date', 'desc')
                ->get()
                ->unique('symbol');
            foreach ($stats as $stat) {
                $currency = substr($stat->symbol, 3, 3);
                $eurRates[$currency] = ($stat->unit_price > 0) ? 1.0 / (float) $stat->unit_price : 1.0;
            }
        }

        if ($penceRequested) {
            if (!isset($eurRates['GBP']) && isset($this->_eurRates['GBP'])) {
                // Reuse the GBP rate already resolved by _loadEurRates() during the main
                // compute, so applyLivePrices() does not re-query EURGBP=X in the same request.
                $eurRates['GBP'] = $this->_eurRates['GBP'];
            }
            if (!isset($eurRates['GBP'])) {
                $gbpStat = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
                    ->where('symbol', 'EURGBP=X')
                    ->orderBy('date', 'desc')
                    ->first();
                $eurRates['GBP'] = ($gbpStat && $gbpStat->unit_price > 0)
                    ? 1.0 / (float) $gbpStat->unit_price
                    : 1.0;
            }
            $eurRates['GBp'] = $eurRates['GBP'] / 100.0;
            $eurRates['GBX'] = $eurRates['GBP'] / 100.0;
        }

        return $eurRates;
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

    /**
     * Returns ['pct' => float|null, 'eur' => float|null] for a single holding window.
     * <30 days: both null (too short to annualize).
     * 30-364 days: null (sub-1Y; raw period return shown by the gain badge, not repeated here).
     * >=365 days: CAGR for the percentage; invested * CAGR for the EUR figure.
     *
     * CAGR (geometric annualization) is the steady yearly rate that, compounded over the
     * holding period, reproduces the actual total return: (1 + period_return)^(1/years) - 1.
     * It is used instead of simple annualization (period_return / years) for two reasons:
     *   1. Comparability. An index's "annualized return" is a CAGR, so reporting CAGR here
     *      lets the figure be placed directly next to the VUSA.AS benchmark.
     *   2. Honesty on big/long winners. Simple annualization over-states them badly
     *      (e.g. +200% over 2y reads as 100%/yr simple but 73%/yr CAGR); CAGR does not.
     * The EUR/y figure is the first-year euro equivalent of that compound rate
     * (invested * CAGR); the verifiable cumulative figure is shown separately as total gain.
     *
     * NOTE: the same formula is reused for the overall multi-window figure, applied to the
     * blended total return over total days held (NOT a geometric chain of the windows, which
     * would inflate separate buy/sell episodes). The money-weighted counterpart is Xirr.
     */
    private function _annualizeReturn(
        ?float $pct,
        int $days,
        float $gainEur,
        float $investedEur
    ): array
    {
        if ($days < self::MIN_CAGR_DAYS || $pct === null) {
            return ['pct' => null, 'eur' => null];
        }
        $years = $days / 365.0;
        $cagr  = (pow(1.0 + $pct / 100.0, 1.0 / $years) - 1.0) * 100.0;
        $eur   = $investedEur > self::FLOAT_EPSILON ? $investedEur * ($cagr / 100.0) : null;
        return ['pct' => $cagr, 'eur' => $eur];
    }

    /**
     * Money-weighted annualized return (XIRR) for the whole symbol, across every window.
     *
     * Collects the dated EUR cash flows already recorded on each window (BUYs negative,
     * SELLs and dividends positive) and, for any still-open window, adds the current market
     * value as a terminal positive flow dated today. See the Xirr service for the math and
     * for why this answers a different question than CAGR.
     *
     * Returns null for holdings under 30 days (too short to annualize meaningfully) or when
     * the rate cannot be solved.
     *
     * @param array $windows  Finalized windows (each with cash_flows, remaining_qty, etc.).
     */
    private function _symbolXirr(array $windows, int $totalDays): ?float
    {
        if ($totalDays < self::MIN_ANNUALIZED_DAYS) {
            return null;
        }

        $cashFlows = [];
        foreach ($windows as $w) {
            foreach ($w['cash_flows'] ?? [] as $cf) {
                $cashFlows[] = $cf;
            }
            // Open window: book today's market value as if sold now, so the unrealized
            // gain is reflected in the money-weighted rate.
            if (!empty($w['is_open']) && $w['remaining_qty'] > self::FLOAT_EPSILON) {
                $marketValueEur = $w['remaining_cost_eur'] + $w['unrealized_gain_eur'];
                $cashFlows[]    = ['date' => Carbon::today(), 'amount' => $marketValueEur];
            }
        }

        return (new Xirr())->compute($cashFlows);
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

        if (!empty($currencies)) {
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
        }

        // GBp/GBX (pence) is used by Yahoo Finance for London-listed symbols.
        // Must be set even when all accounts are EUR, so it can't be inside the
        // early-return block above.
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
        $this->_eurRates['GBX'] = $this->_eurRates['GBP'] / 100.0;
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
            // Dated EUR cash flows for the money-weighted return (XIRR): BUYs are money out
            // (negative), SELLs money in (positive). Dividends are appended later in
            // _attributeDividendsToWindows; the open position's terminal value is added at
            // XIRR time. Mirrors the same CLOSED-trade filter as the cost basis below so the
            // XIRR is consistent with the displayed figures.
            $cashFlows = [];
            foreach ($window['trades'] as $trade) {
                // In an open window, skip all CLOSED trades. CLOSED BUY/SELL pairs
                // represent lots that have been fully exited (e.g. via closeSymbol)
                // and net to zero effect on current holdings. Skipping both sides
                // keeps the cost basis and remaining qty in sync with the Positions
                // overview, which only counts OPEN-status trades.
                // For closed windows every trade counts — no filter applied.
                if ($window['is_open'] && $trade->status === 'CLOSED') {
                    continue;
                }

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
                    $cashFlows[] = ['date' => $trade->timestamp, 'amount' => -$costEur];
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
                    $cashFlows[] = ['date' => $trade->timestamp, 'amount' => $proceedsEur];
                }
            }

            $window['cash_flows'] = $cashFlows;
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
            // Dividends are money returning to you: a positive cash flow for XIRR.
            $windows[$targetIdx]['cash_flows'][] = ['date' => $divTime, 'amount' => $amountEur];
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

        // If the dividend falls in the gap between the last closed window and the open window
        // (e.g. received after the sell date but before buying back in), prefer the closed window.
        if ($openIdx !== null && $lastClosedIdx !== null
            && $date < $windows[$openIdx]['start_date']
        ) {
            return $lastClosedIdx;
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

            $tooShort = $window['duration_days'] < self::MIN_ANNUALIZED_DAYS;
            $window['annualized_gain_short_window'] = $tooShort;
            $ann = $this->_annualizeReturn(
                $window['percentage_gain'],
                $window['duration_days'],
                $window['total_gain_eur'],
                $window['invested_eur']
            );
            $window['annualized_percentage_gain'] = $ann['pct'];
            $window['annualized_gain_eur']        = $ann['eur'];
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

        // Overall annualized return (CAGR), multi-window safe.
        //
        // The figure is the BLENDED total return annualized over the time held: take the total
        // profit relative to all capital ever deployed (percentage_gain = totalGain/totalInvested)
        // and CAGR-annualize it over totalDays (the sum of the windows' own durations, so the gaps
        // when nothing was held are excluded). This is the same formula used for a single window.
        //
        // We deliberately do NOT geometrically chain the windows (product of (1 + r_i)). Chaining
        // is correct only when each window's proceeds compound into the next, like a fund's NAV.
        // Here the windows are separate buy/sell episodes funded by fresh capital, not reinvested
        // proceeds, so chaining would compound returns that never actually compounded and inflate
        // the rate (e.g. two +50% episodes would read as 50%/yr instead of the real ~22.5%/yr).
        // The money-weighted XIRR below is the rigorous "your euros" answer across the same flows.
        $tooShort                 = $totalDays < self::MIN_ANNUALIZED_DAYS;
        $overallAnn               = $this->_annualizeReturn(
            $percentageGain,
            $totalDays,
            $totalGainEur,
            $totalInvestedEur
        );
        $annualizedGainEur        = $overallAnn['eur'];
        $annualizedPercentageGain = $overallAnn['pct'];

        // Money-weighted annualized return (XIRR): the "how did my actual money do" figure,
        // accounting for the timing and size of every buy, sell and dividend. Shown alongside
        // CAGR, not instead of it (see Xirr for why they answer different questions).
        $xirrPct = $this->_symbolXirr($windows, $totalDays);
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
            'annualized_gain_eur'              => $annualizedGainEur,
            'annualized_percentage_gain'       => $annualizedPercentageGain,
            'annualized_gain_short_window'     => $tooShort,
            'xirr_pct'                    => $xirrPct,
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
