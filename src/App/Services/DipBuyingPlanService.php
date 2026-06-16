<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\DipBuyingSetting;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\Concerns\LoadsBenchmarkPrices;

/**
 * Dip Buying Plan engine: paces cash deployment through drawdowns.
 *
 * Given a fixed EUR pool the user sets, a front-loaded reserve ladder by drawdown depth, and the
 * cash already deployed since the current decline began, it produces a plain gap-to-target verdict
 * (no dip yet / behind / on plan / ahead), an informational VUSA trend rail (above/below its
 * 200-day MA, never a gate), and a six-month stall backstop that releases idle cash on a slow
 * schedule. This is behavioral damage-control for a dip fund the user keeps anyway; it does not
 * promise to beat staying invested.
 *
 * The scoring helpers (resolveBands, resolveBand, computePlan, buildLadder, computeStall) are pure
 * and public so the self-validation backtest (DipBuyingBacktestService) shares the exact same
 * ladder and can never diverge from the live tool. buildForUser() is the heavy live pass that
 * gathers VUSA history, the portfolio drawdown and the deployed-so-far measure, then feeds them
 * through those helpers.
 */
class DipBuyingPlanService
{
    use LoadsBenchmarkPrices;

    public const BENCHMARK_SYMBOL = 'VUSA.AS';

    public const VERDICT_NO_DIP  = 'no_dip';
    public const VERDICT_BEHIND  = 'behind';
    public const VERDICT_ON_PLAN = 'on_plan';
    public const VERDICT_AHEAD   = 'ahead';

    // Ladder-row states for the panel/email ladder table.
    public const STATE_NONE     = 'none';     // band 0 (under the first depth): keep powder dry
    public const STATE_DONE     = 'done';     // shallower than the current band: already passed
    public const STATE_CURRENT  = 'current';  // the band the effective drawdown sits in now
    public const STATE_RESERVED = 'reserved'; // deeper than now: capital held in reserve

    private const CACHE_TTL        = 7200; // 2 hours, mirrors DrawdownService
    private const CACHE_KEY_PREFIX = 'dip_buying_v1_u';

    /** @var array<string, float> currency iso code => to-EUR multiplier */
    private array $_eurRates = [];

    /**
     * Cached live plan for a user, or null when the feature cannot be evaluated (disabled, no pool,
     * or no VUSA history). The acting user does not need to be set; everything here is scoped by an
     * explicit user_id and reads historical data only.
     *
     * @param int $userId
     *
     * @return array|null
     */
    public function buildForUser(int $userId): ?array
    {
        // Caching is intentionally disabled: the live build is cheap (the /dip-buying-alerts/backtest
        // page, which runs the same heavy passes, loads in ~200ms), so a fresh compute on every page
        // load keeps the effective drawdown in step with the latest overview series and trades without
        // a 2h TTL lag or a manual cache clear. To re-enable, wrap this in:
        //   Cache::remember(self::CACHE_KEY_PREFIX . $userId, self::CACHE_TTL, fn () => $this->_compute($userId));
        return $this->_compute($userId);
    }

    /**
     * Forget the cached plan for a user (call after a trade or settings change). The plan is currently
     * recomputed live (uncached), so this is a no-op today; it keeps the invalidation correct if the
     * cache in buildForUser() is ever re-enabled.
     *
     * @param int $userId
     *
     * @return void
     */
    public static function clearCache(int $userId): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $userId);
    }

    /**
     * Current total cash across the user's accounts in EUR, taken from the latest point of the same
     * overview cash_EUR series the /positions user overview renders (so the suggested pool reconciles
     * with what the user sees there). Null when the series is missing.
     *
     * @param int $userId
     *
     * @return float|null
     */
    public function currentCashEur(int $userId): ?float
    {
        $series = $this->_parseOverviewSeries(
            ChartsBuilder::getChartOverviewUserAsJsonString($userId, 'cash_EUR')
        );

        if (empty($series)) {
            return null;
        }

        ksort($series);

        return (float) end($series);
    }

    /**
     * Resolve the reserve ladder for a user: the per-user override (DipBuyingSetting.bands) when set
     * and valid, otherwise the config default. Always returned sorted ascending by drawdown depth
     * with a band-0 ({dd:0,target:0}) floor so resolveBand() always matches something.
     *
     * @param array|null $override
     *
     * @return array<int, array{dd: float, target: float}>
     */
    public function resolveBands(?array $override): array
    {
        $raw = is_array($override) && !empty($override)
            ? $override
            : config('alerts.dip_buying.bands', []);

        $bands = [];
        foreach ($raw as $band) {
            $dd     = isset($band['dd']) ? (float) $band['dd'] : null;
            $target = isset($band['target']) ? (float) $band['target'] : null;
            if ($dd === null || $target === null || $dd < 0 || $target < 0) {
                continue;
            }
            $bands[] = ['dd' => $dd, 'target' => min(100.0, $target)];
        }

        if (empty($bands)) {
            $bands[] = ['dd' => 0.0, 'target' => 0.0];
        }

        usort($bands, fn ($a, $b) => $a['dd'] <=> $b['dd']);

        if ($bands[0]['dd'] > 0.0) {
            array_unshift($bands, ['dd' => 0.0, 'target' => 0.0]);
        }

        return $bands;
    }

    /**
     * The deepest ladder band whose drawdown floor the effective drawdown has reached.
     *
     * @param float $effectiveDdPct  effective drawdown, as a positive percentage (e.g. 16.2)
     * @param array $bands           sorted ascending (resolveBands output)
     *
     * @return array{dd: float, target: float}
     */
    public function resolveBand(float $effectiveDdPct, array $bands): array
    {
        $match = $bands[0];
        foreach ($bands as $band) {
            if ($effectiveDdPct + 1e-9 >= $band['dd']) {
                $match = $band;
            } else {
                break;
            }
        }

        return $match;
    }

    /**
     * The plan verdict for a single point in time. Pure: no DB, no config beyond what is passed in.
     *
     * gap = target% - deployed%, with a tolerance band. The stall backstop, when active, raises the
     * effective target above the ladder band so idle cash is released on schedule; the verdict still
     * reads off the same gap logic.
     *
     * @param float      $effectiveDdPct          effective drawdown, positive percentage
     * @param float      $deployedEur             net BUY minus SELL EUR since the episode anchor
     * @param float      $poolEur                 the dip-buying pool size
     * @param array      $bands                   resolveBands output
     * @param float      $tolerancePct            gap tolerance in percentage points
     * @param float|null $stallReleasedTargetPct  stall-raised target %, or null when no stall release
     *
     * @return array
     */
    public function computePlan(
        float $effectiveDdPct,
        float $deployedEur,
        float $poolEur,
        array $bands,
        float $tolerancePct,
        ?float $stallReleasedTargetPct = null
    ): array
    {
        $band       = $this->resolveBand($effectiveDdPct, $bands);
        $baseTarget = (float) $band['target'];

        $stallActive    = $stallReleasedTargetPct !== null && $stallReleasedTargetPct > $baseTarget + 1e-9;
        $effectiveTarget = max($baseTarget, $stallReleasedTargetPct ?? 0.0);

        $deployedPct = $poolEur > 0.0 ? min(100.0, $deployedEur / $poolEur * 100.0) : 0.0;
        $gapPct      = $effectiveTarget - $deployedPct;
        $tranche     = max(0.0, $gapPct / 100.0 * $poolEur);

        if ($effectiveTarget <= 0.0) {
            $verdict = self::VERDICT_NO_DIP;
            $message = 'No dip yet, keep powder dry.';
        } elseif ($gapPct > $tolerancePct) {
            $verdict = self::VERDICT_BEHIND;
            $message = 'Behind plan. Room to deploy about EUR '
                . MoneyFormat::get_formatted_number_plain($tranche, 0) . '.';
        } elseif ($gapPct < -$tolerancePct) {
            $verdict = self::VERDICT_AHEAD;
            $message = 'Ahead of plan, hold the remaining dry powder for deeper bands.';
        } else {
            $verdict = self::VERDICT_ON_PLAN;
            $message = 'On plan.';
        }

        return [
            'effective_dd_pct'      => round($effectiveDdPct, 2),
            'band'                  => $band,
            'base_target_pct'       => round($baseTarget, 1),
            'target_pct'            => round($effectiveTarget, 1),
            'target_eur'            => round($effectiveTarget / 100.0 * $poolEur, 2),
            'deployed_eur'          => round($deployedEur, 2),
            'deployed_pct'          => round($deployedPct, 1),
            'pool_amount_eur'       => round($poolEur, 2),
            'gap_pct'               => round($gapPct, 1),
            'tolerance_pct'         => round($tolerancePct, 1),
            'verdict'               => $verdict,
            'verdict_message'       => $message,
            'suggested_tranche_eur' => round($tranche, 2),
            'stall_active'          => $stallActive,
        ];
    }

    /**
     * The full ladder table for display: one row per band with its EUR target and a display state
     * relative to the current band and what has been deployed.
     *
     * @param array $bands           resolveBands output
     * @param float $poolEur
     * @param float $effectiveDdPct
     * @param float $deployedEur
     *
     * @return array<int, array{dd: float, target_pct: float, target_eur: float, state: string}>
     */
    public function buildLadder(array $bands, float $poolEur, float $effectiveDdPct, float $deployedEur): array
    {
        $current = $this->resolveBand($effectiveDdPct, $bands);

        $rows = [];
        foreach ($bands as $band) {
            // The band the effective drawdown sits in is "current" even when it deploys nothing (the
            // sub-first-band floor), so the panel can always highlight where you stand right now.
            if (abs($band['dd'] - $current['dd']) < 1e-9) {
                $state = self::STATE_CURRENT;
            } elseif ($band['target'] <= 0.0) {
                $state = self::STATE_NONE;
            } elseif ($band['dd'] < $current['dd']) {
                $state = self::STATE_DONE;
            } else {
                $state = self::STATE_RESERVED;
            }

            $rows[] = [
                'dd'         => $band['dd'],
                'target_pct' => $band['target'],
                'target_eur' => round($band['target'] / 100.0 * $poolEur, 2),
                'state'      => $state,
            ];
        }

        return $rows;
    }

    /**
     * Stall-backstop state from an effective-drawdown time series. Once an episode has been active
     * (dd >= $minEpisodeDd) for $stallMonths without entering a new, deeper band, the remaining pool
     * is released on a plain monthly schedule over the following $stallMonths. A fresh deeper band
     * resets the clock (tracked as the most recent date the running-max band increased).
     *
     * Pure and shared by the live engine and the backtest. Returns released_fraction in [0, 1]: the
     * share of the not-yet-targeted pool the schedule has freed by $asOf.
     *
     * @param array<string, float> $ddSeries     date (Y-m-d) => effective drawdown %, any order
     * @param array                $bands        resolveBands output
     * @param int                  $stallMonths
     * @param float                $minEpisodeDd
     * @param Carbon|null          $asOf         evaluation date (defaults to today)
     *
     * @return array{active: bool, last_deepen_date: ?string, months_stalled: float, released_fraction: float}
     */
    public function computeStall(
        array $ddSeries,
        array $bands,
        int $stallMonths,
        float $minEpisodeDd,
        ?Carbon $asOf = null
    ): array
    {
        $idle = ['active' => false, 'last_deepen_date' => null, 'months_stalled' => 0.0, 'released_fraction' => 0.0];

        if (empty($ddSeries) || $stallMonths <= 0) {
            return $idle;
        }

        ksort($ddSeries);
        $asOf = $asOf ? $asOf->copy()->startOfDay() : Carbon::today();

        $maxBandIdx     = -1;
        $lastDeepenDate = null;
        $currentDd      = 0.0;
        foreach ($ddSeries as $date => $dd) {
            $currentDd = (float) $dd;
            $idx       = $this->_bandIndex($currentDd, $bands);
            if ($idx > $maxBandIdx) {
                $maxBandIdx     = $idx;
                $lastDeepenDate = (string) $date;
            }
        }

        // Episode not active now, or never deepened past the floor: nothing to release.
        if ($currentDd + 1e-9 < $minEpisodeDd || $lastDeepenDate === null || $maxBandIdx < 1) {
            return $idle;
        }

        // Float months from days (average month length), avoiding Carbon's deprecated
        // floatDiffInMonths; a 6-month threshold does not need calendar-exact month counting.
        $startTs       = Carbon::parse($lastDeepenDate)->startOfDay()->getTimestamp();
        $monthsStalled = max(0.0, ($asOf->getTimestamp() - $startTs) / (30.436875 * 86400));
        if ($monthsStalled + 1e-9 < $stallMonths) {
            return $idle;
        }

        // Release begins only after the stall window, then ramps linearly over $stallMonths.
        $monthsIntoRelease = $monthsStalled - $stallMonths;
        $releasedFraction  = max(0.0, min(1.0, $monthsIntoRelease / $stallMonths));

        return [
            'active'            => true,
            'last_deepen_date'  => $lastDeepenDate,
            'months_stalled'    => round($monthsStalled, 1),
            'released_fraction' => round($releasedFraction, 4),
        ];
    }

    /**
     * Combine a base ladder target with a stall release fraction into the raised target %. The
     * schedule frees the not-yet-targeted remainder (100 - base) of the pool over time.
     *
     * @param float $baseTargetPct
     * @param float $releasedFraction
     *
     * @return float
     */
    public function stallReleasedTarget(float $baseTargetPct, float $releasedFraction): float
    {
        return min(100.0, $baseTargetPct + $releasedFraction * (100.0 - $baseTargetPct));
    }

    /**
     * The index of a drawdown's band within the sorted band list (0 = band 0 floor).
     *
     * @param float $ddPct
     * @param array $bands
     *
     * @return int
     */
    private function _bandIndex(float $ddPct, array $bands): int
    {
        $idx = 0;
        foreach ($bands as $i => $band) {
            if ($ddPct + 1e-9 >= $band['dd']) {
                $idx = $i;
            } else {
                break;
            }
        }

        return $idx;
    }

    /**
     * The heavy live build for one user. Returns null when the feature is off, no pool is set, or no
     * VUSA history is available.
     *
     * @param int $userId
     *
     * @return array|null
     */
    private function _compute(int $userId): ?array
    {
        $setting = DipBuyingSetting::where('user_id', $userId)->first();
        if ($setting === null || !$setting->isEnabled() || (float) $setting->pool_amount_eur <= 0.0) {
            return null;
        }

        $poolEur = (float) $setting->pool_amount_eur;
        $bands   = $this->resolveBands($setting->bands);

        $vusaPrices = $this->_loadBenchmarkPrices();
        if (count($vusaPrices) < 2) {
            return null;
        }

        $vusa = $this->_vusaDrawdown($vusaPrices);
        if ($vusa === null) {
            return null;
        }

        $portfolio   = $this->_portfolioDrawdown($userId);
        $vusaDd       = $vusa['dd'];
        $portfolioDd  = $portfolio['dd'];
        $effectiveDd  = max($vusaDd, $portfolioDd);
        $driver       = $portfolioDd > $vusaDd ? 'portfolio' : 'vusa';

        $deployedEur = $this->_deployedSince($userId, $vusa['anchor_date']);

        $tolerance = (float) config('alerts.dip_buying.tolerance_pct', 5);
        $minEpDd   = (float) config('alerts.dip_buying.min_episode_dd_pct', 10);
        $stallMons = (int) config('alerts.dip_buying.stall_backstop_months', 6);

        $ddSeries = $this->_effectiveDdSeries($vusaPrices, $vusa['anchor_date'], $portfolio['series']);
        $stall    = $this->computeStall($ddSeries, $bands, $stallMons, $minEpDd);

        $baseTarget        = (float) $this->resolveBand($effectiveDd, $bands)['target'];
        $stallReleasedTgt  = $stall['active']
            ? $this->stallReleasedTarget($baseTarget, $stall['released_fraction'])
            : null;

        $plan = $this->computePlan($effectiveDd, $deployedEur, $poolEur, $bands, $tolerance, $stallReleasedTgt);

        $plan['vusa_dd_pct']      = round($vusaDd, 2);
        $plan['portfolio_dd_pct'] = round($portfolioDd, 2);
        $plan['driver']           = $driver;
        $plan['anchor_date']      = $vusa['anchor_date'];
        $plan['ladder']           = $this->buildLadder($bands, $poolEur, $effectiveDd, $deployedEur);
        $plan['trend']            = $this->_trendRail($vusaPrices);
        $plan['stall']            = $stall;

        return $plan;
    }

    /**
     * VUSA trailing-peak drawdown: percent below the trailing-window peak, with the peak date as the
     * episode anchor. Returns null on empty input.
     *
     * @param array<string, float> $prices
     *
     * @return array{dd: float, anchor_date: string, peak: float, current: float}|null
     */
    private function _vusaDrawdown(array $prices): ?array
    {
        if (empty($prices)) {
            return null;
        }

        ksort($prices);

        $peak       = -INF;
        $anchorDate = (string) array_key_first($prices);
        foreach ($prices as $date => $price) {
            if ($price > $peak) {
                $peak       = $price;
                $anchorDate = (string) $date;
            }
        }

        $latestDate = (string) array_key_last($prices);
        $current    = $prices[$latestDate];
        $dd         = $peak > 0.0 ? max(0.0, ($peak - $current) / $peak * 100.0) : 0.0;

        return ['dd' => $dd, 'anchor_date' => $anchorDate, 'peak' => $peak, 'current' => $current];
    }

    /**
     * Portfolio drawdown from the user's overview changePercentage series (return on cost, already
     * flow-dampened): treat (1 + cp/100) as a value index and measure the running-peak-to-current
     * decline. Returns the current dd and the per-date dd series (for the stall backstop).
     *
     * @param int $userId
     *
     * @return array{dd: float, series: array<string, float>}
     */
    private function _portfolioDrawdown(int $userId): array
    {
        // The overview chart series are currency-suffixed; changePercentage_EUR is the EUR-based
        // return-on-cost series the /positions user overview renders.
        $series = $this->_parseOverviewSeries(
            ChartsBuilder::getChartOverviewUserAsJsonString($userId, 'changePercentage_EUR')
        );

        if (empty($series)) {
            return ['dd' => 0.0, 'series' => []];
        }

        ksort($series);

        $peakIndex = -INF;
        $ddByDate  = [];
        $currentDd = 0.0;
        foreach ($series as $date => $cp) {
            $value = 1.0 + (float) $cp / 100.0;
            if ($value > $peakIndex) {
                $peakIndex = $value;
            }
            $dd               = ($peakIndex > 0.0) ? max(0.0, ($peakIndex - $value) / $peakIndex * 100.0) : 0.0;
            $ddByDate[$date]  = $dd;
            $currentDd        = $dd;
        }

        return ['dd' => $currentDd, 'series' => $ddByDate];
    }

    /**
     * Parse a stored overview chart series ("[{ time: 'Y-m-d', value: N}, ...]") into date => value.
     *
     * @param string $json
     *
     * @return array<string, float>
     */
    private function _parseOverviewSeries(string $json): array
    {
        if (!preg_match_all("/time:\s*'([^']+)'\s*,\s*value:\s*(-?\d+(?:\.\d+)?)/", $json, $m, PREG_SET_ORDER)) {
            return [];
        }

        $series = [];
        foreach ($m as $point) {
            $series[$point[1]] = (float) $point[2];
        }

        return $series;
    }

    /**
     * Net cash deployed in EUR since the episode anchor: BUY (qty*price + fee) minus SELL proceeds
     * (qty*price - fee), converted to EUR. Counts all buys since the anchor, including routine
     * non-dip buys (a documented v1 caveat).
     *
     * @param int    $userId
     * @param string $anchorDate  Y-m-d
     *
     * @return float
     */
    private function _deployedSince(int $userId, string $anchorDate): float
    {
        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('timestamp', '>=', $anchorDate . ' 00:00:00')
            ->with(['tradeCurrencyModel', 'accountModel.currency'])
            ->get();

        $deployed = 0.0;
        foreach ($trades as $trade) {
            if (!empty($trade->is_transfer)) {
                continue; // internal moves are not deployment
            }

            $tradeCur = $trade->tradeCurrencyModel->iso_code ?? 'EUR';
            $acctCur  = $trade->accountModel->currency->iso_code ?? 'EUR';

            $principleEur = (float) $trade->quantity * (float) $trade->unit_price * $this->_eurRate($tradeCur);
            $feeEur       = (float) $trade->fee * $this->_eurRate($acctCur);

            if ($trade->action === 'BUY') {
                $deployed += $principleEur + $feeEur;
            } elseif ($trade->action === 'SELL') {
                $deployed -= ($principleEur - $feeEur);
            }
        }

        return $deployed;
    }

    /**
     * Informational VUSA trend rail: current close vs its moving average. above => uptrend (most
     * dips here are shallow pullbacks); below => downtrend (the normal state during a real drawdown,
     * when tranches fire). Never blocks a tranche. null when there is not enough history.
     *
     * @param array<string, float> $prices
     *
     * @return array{above: ?bool, ma: ?float, period: int, current: ?float, rsi_note: ?array}
     */
    private function _trendRail(array $prices): array
    {
        $period = (int) config('alerts.dip_buying.ma_trend_period', 200);

        $values = array_values($prices);
        $rail    = ['above' => null, 'ma' => null, 'period' => $period, 'current' => null, 'rsi_note' => null];

        if (count($values) < $period) {
            return $rail;
        }

        $window  = array_slice($values, -$period);
        $ma      = array_sum($window) / count($window);
        $current = end($values);

        $rail['ma']       = round($ma, 4);
        $rail['current']  = round((float) $current, 4);
        $rail['above']    = $current >= $ma;
        $rail['rsi_note'] = $this->_rsiNote($values);

        return $rail;
    }

    /**
     * Optional, off-by-default RSI(14) oversold note for the trend rail (descriptive only).
     *
     * @param array<int, float> $values  chronological closes
     *
     * @return array{rsi: float, oversold: bool}|null
     */
    private function _rsiNote(array $values): ?array
    {
        if (!config('alerts.dip_buying.rsi_note_enabled', false)) {
            return null;
        }

        $rsi = $this->_rsi($values, 14);
        if ($rsi === null) {
            return null;
        }

        $oversoldLevel = (float) config('alerts.dip_buying.rsi_oversold_level', 30);

        return ['rsi' => $rsi, 'oversold' => $rsi < $oversoldLevel];
    }

    /**
     * Wilder's RSI over a price series, or null when there is too little data.
     *
     * @param array<int, float> $prices
     * @param int                $period
     *
     * @return float|null
     */
    private function _rsi(array $prices, int $period): ?float
    {
        if (count($prices) < $period + 1) {
            return null;
        }

        $gains  = 0.0;
        $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $change = $prices[$i] - $prices[$i - 1];
            $change >= 0 ? $gains += $change : $losses += abs($change);
        }
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        for ($i = $period + 1, $n = count($prices); $i < $n; $i++) {
            $change  = $prices[$i] - $prices[$i - 1];
            $gain    = $change > 0 ? $change : 0.0;
            $loss    = $change < 0 ? abs($change) : 0.0;
            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        if ($avgLoss === 0.0) {
            return 100.0;
        }

        return round(100.0 - (100.0 / (1.0 + ($avgGain / $avgLoss))), 1);
    }

    /**
     * The effective-drawdown series (date => max(vusa_dd, portfolio_dd)) over the current episode,
     * i.e. from the VUSA anchor onward. The portfolio dd is carried forward to fill VUSA trading
     * days the overview series does not cover. Feeds the stall backstop.
     *
     * @param array<string, float> $vusaPrices
     * @param string                $anchorDate
     * @param array<string, float>  $portfolioDdSeries
     *
     * @return array<string, float>
     */
    private function _effectiveDdSeries(array $vusaPrices, string $anchorDate, array $portfolioDdSeries): array
    {
        ksort($vusaPrices);
        ksort($portfolioDdSeries);

        // VUSA running-peak drawdown from the anchor (the anchor is the trailing peak, so dd grows
        // from 0 there).
        $peak       = $vusaPrices[$anchorDate] ?? max($vusaPrices);
        $vusaDdByDate = [];
        foreach ($vusaPrices as $date => $price) {
            if ($date < $anchorDate) {
                continue;
            }
            if ($price > $peak) {
                $peak = $price;
            }
            $vusaDdByDate[$date] = $peak > 0.0 ? max(0.0, ($peak - $price) / $peak * 100.0) : 0.0;
        }

        $series      = [];
        $portfolioDd = 0.0;
        $portfolioDates = array_keys($portfolioDdSeries);
        $pIdx        = 0;
        $pCount      = count($portfolioDates);

        foreach ($vusaDdByDate as $date => $vusaDd) {
            while ($pIdx < $pCount && $portfolioDates[$pIdx] <= $date) {
                $portfolioDd = $portfolioDdSeries[$portfolioDates[$pIdx]];
                $pIdx++;
            }
            $series[$date] = max($vusaDd, $portfolioDd);
        }

        return $series;
    }

    /**
     * The to-EUR multiplier for a currency, memoized. EUR is 1; USD comes from the same latest
     * EURUSD=X the positions overview uses (so the panel reconciles with /positions); other
     * currencies are derived from their EUR..=X stat, with GBp/GBX as hundredths of GBP.
     *
     * @param string $currency
     *
     * @return float
     */
    private function _eurRate(string $currency): float
    {
        if (empty($this->_eurRates)) {
            $this->_buildEurRates();
        }

        return $this->_eurRates[$currency] ?? 1.0;
    }

    /**
     * Build the currency => to-EUR multiplier map once.
     *
     * @return void
     */
    private function _buildEurRates(): void
    {
        $rates = ['EUR' => 1.0];

        $eurUsd = ChartsBuilder::getLatestSymbolValue('EURUSD=X');
        if ($eurUsd) {
            $rates['USD'] = 1.0 / $eurUsd;
        }

        $stats = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', 'LIKE', 'EUR%=X')
            ->orderBy('date', 'desc')
            ->get()
            ->unique('symbol');

        foreach ($stats as $stat) {
            $currency = substr($stat->symbol, 3, 3);
            if (strlen($currency) === 3 && $currency !== 'EUR' && !isset($rates[$currency])) {
                $rates[$currency] = ((float) $stat->unit_price > 0.0)
                    ? 1.0 / (float) $stat->unit_price
                    : 1.0;
            }
        }

        if (isset($rates['GBP'])) {
            $rates['GBp'] = $rates['GBP'] / 100.0;
            $rates['GBX'] = $rates['GBP'] / 100.0;
        }

        $this->_eurRates = $rates;
    }
}
