<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

use ovidiuro\myfinance2\App\Models\DipBuyingSetting;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\Concerns\LoadsBenchmarkPrices;

/**
 * Self-validation backtest for the Dip Buying Plan (spec section 5).
 *
 * Replays the user's own Trade history through the shared ladder engine over a window (default Jan
 * 2025 to now, which contains the deep April 2025 episode) and, per drop episode, builds two
 * timelines on the same pool: ACTUAL (what was deployed) vs GUIDED (what the ladder would have had
 * the user deploy). Drops are isolated on the effective-drawdown axis (max of the VUSA and portfolio
 * drawdowns, the same axis the live tool acts on) with a configurable depth threshold and hysteresis
 * boundaries, so shallower dips can be surfaced alongside the deep ones. It explicitly looks for the
 * two recurring mistakes:
 *
 *   - early exhaustion: deployed ~100% while still shallow, then it went deeper;
 *   - cash drag: the stall backstop would have released cash that was actually left idle.
 *
 * Honesty guardrails: the only levers are pool size and the band table (no curve fitting); the
 * stay-fully-invested and monthly-DCA baselines are always shown alongside; and it is labelled a
 * single-path personal sanity check, not statistical proof. The ladder, band resolution and stall
 * backstop come straight from DipBuyingPlanService so the guided curve can never diverge from the
 * live tool.
 */
class DipBuyingBacktestService
{
    use LoadsBenchmarkPrices;

    private const DEFAULT_FROM     = '2025-01-01';
    private const EXHAUSTION_PCT   = 95.0; // "all in" threshold for the early-exhaustion test
    private const DEEPER_MARGIN_DD = 5.0;  // extra drawdown that counts as "then it went deeper"
    private const DEFAULT_MODE     = 'effective'; // drop axis: max(portfolio_dd, vusa_dd)

    private DipBuyingPlanService $_engine;

    public function __construct(?DipBuyingPlanService $engine = null)
    {
        $this->_engine = $engine ?? new DipBuyingPlanService();
    }

    /**
     * Build the backtest report for a user.
     *
     * @param int         $userId
     * @param string|null $from    Y-m-d window start (default Jan 2025)
     * @param float|null  $poolEur override the user's configured pool
     * @param float|null  $minDrop minimum drawdown (%) that counts as a drop episode
     * @param string|null $mode    drop axis: effective (default), change (portfolio) or vusa
     *
     * @return array
     */
    public function build(
        int $userId,
        ?string $from = null,
        ?float $poolEur = null,
        ?float $minDrop = null,
        ?string $mode = null
    ): array
    {
        $from  = $from ?: self::DEFAULT_FROM;
        $to    = Carbon::today()->format('Y-m-d');
        $minDd = $this->_resolveMinDrop($minDrop);
        $mode  = $this->_resolveMode($mode);

        $setting = DipBuyingSetting::where('user_id', $userId)->first();

        // Pool basis: an explicit --pool / ?pool override applies to every episode; otherwise the
        // pool is automatic, taken from the user's actual EUR cash on the episode's start date (the
        // same cash_EUR series the /positions user overview plots), with the settings pool, then a
        // flat default, as fallbacks when no cash is recorded for that date.
        $explicitPool = $poolEur !== null && $poolEur > 0.0 ? $poolEur : null;
        $cashSeries   = $this->_loadCashSeries($userId);
        $fallbackPool = ($setting && (float) $setting->pool_amount_eur > 0.0)
            ? (float) $setting->pool_amount_eur
            : 10000.0;
        $poolSource = $explicitPool !== null
            ? 'override'
            : (!empty($cashSeries) ? 'cash_at_episode_start' : 'fallback');

        $bands = $this->_engine->resolveBands($setting?->bands);

        $vusaPrices = $this->_loadBenchmarkPrices($from);
        if (count($vusaPrices) < 2) {
            return $this->_emptyReport($from, $to, 'No VUSA.AS history in this window.');
        }

        // Drops are measured on the selected axis: the effective drawdown (max of the VUSA and
        // portfolio drawdowns, the live default), the portfolio change %, or VUSA.AS alone.
        $effDd     = $this->_ddSeries($vusaPrices, $userId, $mode);
        $eurRates  = $this->_buildEurRates();
        $buys      = $this->_loadBuysInEur($userId, $from, $to, $eurRates, $effDd);
        $sellDates = $this->_loadSellDates($userId, $from, $to);
        $episodes  = $this->_detectEpisodes(
            $effDd, $bands, $buys, $cashSeries, $explicitPool, $fallbackPool, $minDd
        );

        $currentEpisode = $this->_buildCurrentEpisode(
            $effDd, $episodes, $bands, $buys, $sellDates, $cashSeries, $explicitPool, $fallbackPool,
            $vusaPrices, $userId
        );

        $baselines = $this->_baselines($vusaPrices, $bands);

        return [
            'from'            => $from,
            'to'              => $to,
            'min_drop'        => round($minDd, 2),
            'drop_mode'       => $mode,
            'pool_source'     => $poolSource,
            'pool_eur'        => $explicitPool !== null ? round($explicitPool, 2) : null,
            'episodes'        => $episodes,
            'current_episode' => $currentEpisode,
            'first_band'      => $this->_firstBand($bands),
            'baselines'       => $baselines,
            'headline'        => $this->_headline($episodes),
            'note'            => 'Single-path personal sanity check, not statistical proof.',
        ];
    }

    /**
     * The extra panel detail the /positions Dip Buying panel and the daily email both layer on top of
     * the plan: the three-basis regime breakdown (with its "Drawdown" column pinned to the plan's
     * canonical effective / portfolio / VUSA drawdowns, so the starred value cannot diverge from the
     * band the ladder highlights), the in-progress current episode and the first deploying band. One
     * computation, shared by the controller and the alert command, so the panel and the email can
     * never drift. Never fatal: any failure yields the empty defaults and the caller still renders the
     * core plan.
     *
     * @param int   $userId
     * @param array $plan    the DipBuyingPlanService plan (for the pinned drawdown column)
     *
     * @return array{regime: array, current: ?array, firstBand: ?array}
     */
    public function panelDetail(int $userId, array $plan): array
    {
        $regime    = $this->regimeSummary($userId);
        if (!empty($regime)) {
            $regime['effective']['dd_pct'] = round((float) ($plan['effective_dd_pct'] ?? 0), 2);
            $regime['change']['dd_pct']    = round((float) ($plan['portfolio_dd_pct'] ?? 0), 2);
            $regime['vusa']['dd_pct']      = round((float) ($plan['vusa_dd_pct'] ?? 0), 2);
        }

        $report = $this->build($userId);

        return [
            'regime'    => $regime,
            'current'   => $report['current_episode'] ?? null,
            'firstBand' => $report['first_band'] ?? null,
        ];
    }

    /**
     * Lightweight context for the standalone drawdown chart card: the resolved drop threshold, the
     * selected drop axis (mode) and the detected episode spans (peak, low, recovery), so the card can
     * shade them. Shares the same axis and hysteresis detection as the full report, so the chart and
     * the episode list can never disagree.
     *
     * @param int         $userId
     * @param string|null $from
     * @param float|null  $minDrop
     * @param string|null $mode    drop axis: effective (default), change or vusa
     *
     * @return array{from: string, min_drop: float, drop_mode: string, series: array<int, array{time: string, value: float}>, episodes: array<int, array{peak_date: string, low_date: string, end_date: string, max_dd: float}>}
     */
    public function chartContext(
        int $userId,
        ?string $from = null,
        ?float $minDrop = null,
        ?string $mode = null
    ): array
    {
        $from  = $from ?: self::DEFAULT_FROM;
        $minDd = $this->_resolveMinDrop($minDrop);
        $mode  = $this->_resolveMode($mode);

        $empty = ['from' => $from, 'min_drop' => round($minDd, 2), 'drop_mode' => $mode,
                  'series' => [], 'vusa_mvalue_eur' => null, 'episodes' => [], 'current_drop' => null];

        $vusaPrices = $this->_loadBenchmarkPrices($from);
        if (count($vusaPrices) < 2) {
            return $empty;
        }

        // Episodes are isolated on the selected basis (the dropdown decides the windows), but the
        // plotted red line is ALWAYS the effective drawdown (Portfolio vs VUSA.AS), so the effective
        // signal stays visible no matter which basis the user is isolating windows on.
        $episodeDd = $this->_ddSeries($vusaPrices, $userId, $mode);
        $lineDd    = $mode === 'effective'
            ? $episodeDd
            : $this->_ddSeries($vusaPrices, $userId, 'effective');

        $episodes = [];
        foreach ($this->_episodeSpans($episodeDd, $minDd) as $span) {
            $episodes[] = [
                'peak_date' => $span['peak_date'],
                'low_date'  => $span['low_date'],
                'end_date'  => $span['end_date'],
                'max_dd'    => round($span['max_dd'], 1),
            ];
        }

        return [
            'from'      => $from,
            'min_drop'  => round($minDd, 2),
            'drop_mode' => $mode,
            // Always the effective drawdown (negated so the line dips into the drop), independent of the
            // selected episode basis, so the red line is the same reference signal in every mode.
            'series'    => $this->_ddChartSeries($lineDd),
            // Current EUR market value of the user's VUSA.AS holding across accounts, shown above the
            // VUSA bar (the gain % stays on the bar itself). Null when the user holds no VUSA.AS.
            'vusa_mvalue_eur' => $this->_symbolMvalueEur($userId, DipBuyingPlanService::BENCHMARK_SYMBOL),
            'episodes'  => $episodes,
            // "Where are we now": the current drop in the tail, measured from the most recent local
            // peak (a navigation aid, not a historical episode). Threshold-independent (T=0), so the
            // blue overlay is always drawn however shallow, matching the current-episode card. Null
            // only at a genuine fresh high (no drawdown).
            'current_drop' => $this->_currentDrop($episodeDd, $episodes, 0.0),
        ];
    }

    /**
     * Market-regime summary for the live /positions panel: for each of the three drawdown bases (the
     * effective Portfolio vs VUSA.AS axis, the portfolio change %, and VUSA.AS alone) the current
     * drawdown from its running peak and the current drop from its most recent local peak ("down X
     * now"). The local drop is always reported, even under the episode threshold, so the panel can
     * show shallow pullbacks too. VUSA is loaded over the live peak-lookback window so these figures
     * match the panel headline (which the live engine computes on the same window). Empty when there
     * is no VUSA history.
     *
     * @param int $userId
     *
     * @return array<string, array{label: string, dd_pct: float, current_drop_pct: float, since: ?string, peak_dd: float}>
     */
    public function regimeSummary(int $userId): array
    {
        $years      = (int) config('alerts.dip_buying.peak_lookback_years', 3);
        $from       = Carbon::today()->subYears($years)->format('Y-m-d');
        $vusaPrices = $this->_loadBenchmarkPrices($from);
        if (count($vusaPrices) < 2) {
            return [];
        }

        $minDd = $this->_resolveMinDrop(null);

        return [
            'effective' => $this->_regimeRow(
                'Portfolio vs VUSA.AS', $this->_effectiveDdSeries($vusaPrices, $userId), $minDd
            ),
            'change' => $this->_regimeRow('Change %', $this->_loadPortfolioDdSeries($userId), $minDd),
            'vusa'   => $this->_regimeRow('VUSA.AS', $this->_vusaDrawdownSeries($vusaPrices), $minDd),
        ];
    }

    /**
     * One regime-table row from a drawdown series: the latest drawdown from the running peak, plus the
     * current drop from the most recent local peak and that peak's date.
     *
     * @param string               $label
     * @param array<string, float> $ddSeries  date => drawdown %, from the running peak
     * @param float                $minDd     episode threshold used to locate the most recent local peak
     *
     * @return array{label: string, dd_pct: float, current_drop_pct: float, since: ?string, peak_dd: float}
     */
    private function _regimeRow(string $label, array $ddSeries, float $minDd): array
    {
        ksort($ddSeries);
        $ddPct = empty($ddSeries) ? 0.0 : (float) end($ddSeries);
        $local = $this->currentLocalDropFromSeries($ddSeries, $minDd);

        return [
            'label'            => $label,
            'dd_pct'           => round($ddPct, 2),
            'current_drop_pct' => $local['drop_pct'] ?? 0.0,
            'since'            => $local['peak_date'] ?? null,
            'peak_dd'          => $local['peak_dd'] ?? 0.0,
        ];
    }

    /**
     * The current drop from the most recent local peak of a drawdown series, always returned: the
     * episode threshold only locates the most recent significant trough, while the drop itself is
     * reported even when shallow (T = 0 at the final cutoff). Reuses the same episode and local-peak
     * detection as the chart's "down X now" blue band, so the panel and the chart agree. Null only
     * when the series is too short to measure.
     *
     * @param array<string, float> $ddSeries  date => drawdown %, from the running peak
     * @param float|null           $minDrop   episode threshold (defaults to the configured drop %)
     *
     * @return array{drop_pct: float, peak_date: string, low_date: string, peak_dd: float}|null
     */
    public function currentLocalDropFromSeries(array $ddSeries, ?float $minDrop = null): ?array
    {
        if (empty($ddSeries)) {
            return null;
        }

        ksort($ddSeries);
        $minDd    = $this->_resolveMinDrop($minDrop);
        $episodes = $this->_episodeSpans($ddSeries, $minDd);
        $ctx      = $this->_localDropContext($ddSeries, $episodes, 0.0);
        if ($ctx === null) {
            return null;
        }

        return [
            'drop_pct'  => round($ctx['current_dd'], 2),
            'peak_date' => $ctx['peak_date'],
            'low_date'  => $ctx['low_date'],
            // The local peak's own drawdown from the running peak: the floor "down now" is measured
            // from. Higher here means a less-calm recent peak, which yields a smaller "down now".
            'peak_dd'   => round($ctx['peak_dd'], 2),
        ];
    }

    /**
     * Current market value (EUR) of the user's open holding in a symbol across all accounts, reused
     * from the cached SymbolPerformanceService (open-window market value = remaining cost + unrealized
     * gain, on the latest stored price). Null when there is no open position.
     *
     * @param int    $userId
     * @param string $symbol
     *
     * @return float|null
     */
    private function _symbolMvalueEur(int $userId, string $symbol): ?float
    {
        $perf = (new SymbolPerformanceService())->handle($userId);
        $row  = $perf[$symbol] ?? null;
        if (empty($row['has_data']) || empty($row['windows'])) {
            return null;
        }

        $mvalue = 0.0;
        $held   = false;
        foreach ($row['windows'] as $window) {
            if (!empty($window['is_open']) && (float) $window['remaining_qty'] > 1e-4) {
                $mvalue += (float) $window['remaining_cost_eur'] + (float) $window['unrealized_gain_eur'];
                $held    = true;
            }
        }

        return $held ? round($mvalue, 2) : null;
    }

    /**
     * The drop-axis drawdown series formatted for the chart card: chronological {time, value} points
     * with the drawdown negated, so the plotted line dips downward into each drop and lines up with
     * the price decline on the percentage axis.
     *
     * @param array<string, float> $effDd drawdown % by date (positive), ascending
     *
     * @return array<int, array{time: string, value: float}>
     */
    private function _ddChartSeries(array $effDd): array
    {
        ksort($effDd);

        $series = [];
        foreach ($effDd as $date => $dd) {
            $series[] = ['time' => (string) $date, 'value' => -round($dd, 2)];
        }

        return $series;
    }

    /**
     * Metric metadata for the standalone drawdown chart card (no cost / mvalue / currency exchange):
     * Change % and VUSA.AS on the left percentage axis, Change and Cash in EUR on the right. Kept
     * here so the card markup and its script never drift on colors, styles or axis assignment.
     *
     * @return array<string, array{title: string, color: string, style: int, border: string, axis: string}>
     */
    public static function chartMetrics(): array
    {
        return [
            'effective'        => ['title' => 'Portfolio vs VUSA.AS', 'color' => 'rgba(220, 53, 69, 1)',
                                   'style' => 0, 'border' => 'solid', 'axis' => 'right'],
            'changePercentage' => ['title' => 'Change %', 'color' => 'rgba(156, 39, 176, 1)',
                                   'style' => 0, 'border' => 'solid', 'axis' => 'right'],
            'vusa'             => ['title' => 'VUSA.AS', 'color' => 'rgba(38, 166, 154, 1)',
                                   'style' => 0, 'border' => 'solid', 'axis' => 'right'],
            'cash'             => ['title' => 'Cash', 'color' => 'rgba(255, 192, 0, 1)',
                                   'style' => 0, 'border' => 'solid', 'axis' => 'left'],
        ];
    }

    /**
     * The selectable drop axes (mode => human label), default first. A "drop" can be isolated on the
     * portfolio-vs-VUSA effective drawdown (the live default), the portfolio change % alone, or
     * VUSA.AS alone. Kept here so the card selector and the resolver never drift.
     *
     * @return array<string, string>
     */
    public static function dropModes(): array
    {
        return [
            'effective' => 'Portfolio vs VUSA.AS',
            'change'    => 'Change %',
            'vusa'      => 'VUSA.AS',
        ];
    }

    /**
     * Resolve the drop axis (mode): a known value wins, else the default (effective).
     *
     * @param string|null $mode
     *
     * @return string
     */
    private function _resolveMode(?string $mode): string
    {
        return array_key_exists((string) $mode, self::dropModes()) ? (string) $mode : self::DEFAULT_MODE;
    }

    /**
     * The drawdown series (date => dd %) drops are isolated on, per the selected mode: the effective
     * drawdown (max of portfolio vs VUSA, the live axis), the portfolio change % drawdown, or the
     * VUSA.AS drawdown alone. The change and vusa axes are independent single-source series; effective
     * merges both.
     *
     * @param array<string, float> $vusaPrices
     * @param int                  $userId
     * @param string               $mode
     *
     * @return array<string, float>
     */
    private function _ddSeries(array $vusaPrices, int $userId, string $mode): array
    {
        switch ($mode) {
            case 'change':
                return $this->_loadPortfolioDdSeries($userId);
            case 'vusa':
                return $this->_vusaDrawdownSeries($vusaPrices);
            case 'effective':
            default:
                return $this->_effectiveDdSeries($vusaPrices, $userId);
        }
    }

    /**
     * Resolve the drop threshold: a positive request/override value wins, else the configured default
     * (5%); clamped to a sane 1..50% range.
     *
     * @param float|null $minDrop
     *
     * @return float
     */
    private function _resolveMinDrop(?float $minDrop): float
    {
        $default = (float) config('alerts.dip_buying.min_drop_pct', 5);
        if ($minDrop === null || $minDrop <= 0.0) {
            return $default;
        }

        return min(50.0, max(1.0, $minDrop));
    }

    /**
     * The user's EUR cash overview series (date => cash_EUR), the same series the /positions user
     * overview plots. Empty when the chart file is missing.
     *
     * @param int $userId
     *
     * @return array<string, float>
     */
    private function _loadCashSeries(int $userId): array
    {
        return $this->_parseOverviewSeries(
            ChartsBuilder::getChartOverviewUserAsJsonString($userId, 'cash_EUR')
        );
    }

    /**
     * Parse a stored overview chart series ("[{ time: 'Y-m-d', value: N}, ...]") into date => value,
     * ascending. Shared by the cash and changePercentage loaders.
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
        ksort($series);

        return $series;
    }

    /**
     * The effective-drawdown series (date => max(vusa_dd, portfolio_dd)) over the full window, on the
     * VUSA trading-day axis, with the portfolio drawdown carried forward to fill days the overview
     * series does not cover. This is the axis on which drops are defined, matching the live tool.
     *
     * @param array<string, float> $vusaPrices
     * @param int                  $userId
     *
     * @return array<string, float>
     */
    private function _effectiveDdSeries(array $vusaPrices, int $userId): array
    {
        $vusaDd      = $this->_vusaDrawdownSeries($vusaPrices);
        $portfolioDd = $this->_loadPortfolioDdSeries($userId);
        ksort($vusaDd);

        $pDates = array_keys($portfolioDd);
        $pCount = count($pDates);
        $pIdx   = 0;
        $pVal   = 0.0;
        $lastVd = 0.0;

        $series = [];
        foreach ($vusaDd as $date => $vd) {
            while ($pIdx < $pCount && $pDates[$pIdx] <= $date) {
                $pVal = $portfolioDd[$pDates[$pIdx]];
                $pIdx++;
            }
            $lastVd        = $vd;
            $series[$date] = max($vd, $pVal);
        }

        // Safety net for any residual benchmark lag: _loadBenchmarkPrices already appends today's live VUSA
        // point, so the series normally reaches today, but if that live point is unavailable the VUSA
        // axis could still end before the (daily-rebuilt) portfolio overview. Keying on VUSA dates
        // alone would then end the series on the last VUSA bar and leave the "current" readout stale.
        // Extend past the last VUSA bar by carrying its drawdown forward onto the remaining portfolio
        // dates, so "now" tracks the latest portfolio point and matches the live engine.
        while ($pIdx < $pCount) {
            $date          = $pDates[$pIdx];
            $series[$date] = max($lastVd, $portfolioDd[$date]);
            $pIdx++;
        }

        return $series;
    }

    /**
     * Portfolio running-peak drawdown from the user's changePercentage_EUR overview series (treat
     * 1 + cp/100 as a value index), the same derivation the live tool uses. Empty when missing.
     *
     * @param int $userId
     *
     * @return array<string, float>
     */
    private function _loadPortfolioDdSeries(int $userId): array
    {
        $cp = $this->_parseOverviewSeries(
            ChartsBuilder::getChartOverviewUserAsJsonString($userId, 'changePercentage_EUR')
        );
        if (empty($cp)) {
            return [];
        }

        $peak   = -INF;
        $series = [];
        foreach ($cp as $date => $val) {
            $index = 1.0 + (float) $val / 100.0;
            if ($index > $peak) {
                $peak = $index;
            }
            $series[$date] = $peak > 0.0 ? max(0.0, ($peak - $index) / $peak * 100.0) : 0.0;
        }

        return $series;
    }

    /**
     * The all-time high the effective drawdown is measured below as of a date, on whichever basis is
     * binding then: the portfolio return index when it is the deeper drawdown, otherwise the VUSA.AS
     * price path (also the tie-break, matching max(vusa_dd, portfolio_dd)). Returns that basis's
     * running-peak value and the date it was set, so the current-dip card can name the high in the
     * same terms the drawdown uses. Null when the binding basis has no point on or before the date.
     *
     * @param string                $asOf
     * @param array<string, float>  $vusaPrices
     * @param int                   $userId
     *
     * @return array{basis: string, label: string, date: string, value: float, unit: string}|null
     */
    private function _effectiveAth(string $asOf, array $vusaPrices, int $userId): ?array
    {
        $vusaDd      = $this->_vusaDrawdownSeries($vusaPrices);
        $portfolioDd = $this->_loadPortfolioDdSeries($userId);
        $vd          = $this->_cashOnOrBefore($asOf, $vusaDd) ?? 0.0;
        $pd          = $this->_cashOnOrBefore($asOf, $portfolioDd) ?? 0.0;

        if ($pd > $vd && !empty($portfolioDd)) {
            // The portfolio's all-time-high return: the peak of its changePercentage index (the same
            // series _loadPortfolioDdSeries derives its drawdown from), expressed as a percentage.
            $cp   = $this->_parseOverviewSeries(
                ChartsBuilder::getChartOverviewUserAsJsonString($userId, 'changePercentage_EUR')
            );
            $peak = $this->_runningPeakOnOrBefore($asOf, $cp);

            return $peak === null ? null : [
                'basis' => 'portfolio',
                'label' => 'your portfolio',
                'date'  => $peak['date'],
                'value' => $peak['value'],
                'unit'  => 'pct',
            ];
        }

        $peak = $this->_runningPeakOnOrBefore($asOf, $vusaPrices);

        return $peak === null ? null : [
            'basis' => 'vusa',
            'label' => 'VUSA.AS',
            'date'  => $peak['date'],
            'value' => $peak['value'],
            'unit'  => 'eur',
        ];
    }

    /**
     * The highest value in a date => value series at or before a date, with the date it was reached.
     * Null when the series has no point on or before the date.
     *
     * @param string                $asOf
     * @param array<string, float>  $series
     *
     * @return array{date: string, value: float}|null
     */
    private function _runningPeakOnOrBefore(string $asOf, array $series): ?array
    {
        ksort($series);

        $peakDate  = null;
        $peakValue = -INF;
        foreach ($series as $date => $value) {
            if ((string) $date > $asOf) {
                break;
            }
            if ((float) $value > $peakValue) {
                $peakValue = (float) $value;
                $peakDate  = (string) $date;
            }
        }

        return $peakDate === null ? null : ['date' => $peakDate, 'value' => $peakValue];
    }

    /**
     * The user's EUR cash on or before a date, carried forward from the latest earlier point.
     *
     * @param string                $date
     * @param array<string, float>  $cashSeries
     *
     * @return float|null
     */
    private function _cashOnOrBefore(string $date, array $cashSeries): ?float
    {
        $cash = null;
        foreach ($cashSeries as $d => $value) {
            if ($d > $date) {
                break;
            }
            $cash = $value;
        }

        return $cash;
    }

    /**
     * VUSA running-peak drawdown series (date => dd %). One of the two inputs to the effective
     * drawdown (merged with the portfolio drawdown in _effectiveDdSeries); also used directly by the
     * guided-ladder baseline, which is measured on the VUSA price path.
     *
     * @param array<string, float> $prices
     *
     * @return array<string, float>
     */
    private function _vusaDrawdownSeries(array $prices): array
    {
        ksort($prices);

        $peak   = -INF;
        $series = [];
        foreach ($prices as $date => $price) {
            if ($price > $peak) {
                $peak = $price;
            }
            $series[$date] = $peak > 0.0 ? max(0.0, ($peak - $price) / $peak * 100.0) : 0.0;
        }

        return $series;
    }

    /**
     * Detect drop episodes on the effective-drawdown axis and build the actual-vs-guided comparison
     * for each.
     *
     * @param array<string, float> $effDd        effective drawdown % by date
     * @param array                $bands
     * @param array                $buys         chronological [date, eur, dd_at_buy]
     * @param array<string, float> $cashSeries   date => cash_EUR for the automatic per-episode pool
     * @param float|null           $explicitPool fixed override pool, or null for the cash-based pool
     * @param float                $fallbackPool used when no cash is recorded at the episode start
     * @param float                $minDd        the drop threshold (episode enter depth)
     *
     * @return array<int, array>
     */
    private function _detectEpisodes(
        array $effDd,
        array $bands,
        array $buys,
        array $cashSeries,
        ?float $explicitPool,
        float $fallbackPool,
        float $minDd
    ): array
    {
        $stallMons  = (int) config('alerts.dip_buying.stall_backstop_months', 6);
        // The stall backstop is a ladder mechanic tied to the live band-1 floor, independent of the
        // (possibly lower) drop-visibility threshold used to isolate episodes.
        $stallFloor = (float) config('alerts.dip_buying.min_episode_dd_pct', 10);
        ksort($effDd);

        $episodes = [];
        foreach ($this->_episodeSpans($effDd, $minDd) as $span) {
            $segment = array_filter(
                $effDd,
                fn ($d) => $d >= $span['peak_date'] && $d <= $span['end_date'],
                ARRAY_FILTER_USE_KEY
            );
            if (empty($segment)) {
                continue;
            }

            $episodes[] = $this->_buildEpisode(
                $span['peak_date'], $span['end_date'], $segment,
                $bands, $buys, $cashSeries, $explicitPool, $fallbackPool, $stallFloor, $stallMons
            );
        }

        return $episodes;
    }

    /**
     * Isolate drop episodes on the effective-drawdown series with hysteresis: an episode opens when
     * the drawdown crosses the threshold T, and closes when it recovers back under T/2, so the April
     * crash and a later, shallower dip become separate episodes instead of one. An episode still open
     * at the end of the window runs through the last available date.
     *
     * Each episode is anchored at its trailing peak: the calmest day (lowest drawdown, i.e. the
     * highest price) since the previous episode closed, which is the running-peak high the trough
     * draws down from. That, not the last day still inside the exit band, is where the "peak to
     * trough" band the card shades actually begins (e.g. the Feb high before the April 2025 low,
     * rather than the last day that was merely within 2.5% of it).
     *
     * @param array<string, float> $effDd  effective drawdown % by date, ascending
     * @param float                $T      enter threshold (the configured drop %)
     *
     * @return array<int, array{peak_date: string, low_date: string, end_date: string, max_dd: float}>
     */
    private function _episodeSpans(array $effDd, float $T): array
    {
        if (empty($effDd)) {
            return [];
        }

        ksort($effDd);
        $exit = $T / 2.0;

        $spans     = [];
        $inEpisode = false;
        $anchor    = (string) array_key_first($effDd);
        $anchorDd  = INF;
        $cur       = null;

        foreach ($effDd as $date => $dd) {
            $date = (string) $date;

            if (!$inEpisode) {
                // Track the trailing peak: the lowest-drawdown (highest-price) day since the last
                // episode closed. Ties resolve to the most recent such day (the last touch of the
                // peak before the slide). This becomes the band's start when the drop opens.
                if ($dd <= $anchorDd) {
                    $anchorDd = $dd;
                    $anchor   = $date;
                }
                if ($dd + 1e-9 >= $T) {
                    $inEpisode = true;
                    $cur = ['peak_date' => $anchor, 'low_date' => $date,
                            'end_date' => $date, 'max_dd' => $dd];
                }
                continue;
            }

            $cur['end_date'] = $date;
            if ($dd > $cur['max_dd']) {
                $cur['max_dd']   = $dd;
                $cur['low_date'] = $date;
            }
            if ($dd <= $exit) {
                $spans[]   = $cur;
                $cur       = null;
                $inEpisode = false;
                $anchor    = $date;
                $anchorDd  = $dd; // restart the trailing-peak search from this recovery day
            }
        }
        if ($inEpisode && $cur !== null) {
            $spans[] = $cur;
        }

        return $spans;
    }

    /**
     * A "where are we now" overlay for the tail after the last detected episode: the current drop
     * measured from the most recent LOCAL peak, not the all-time high the real episodes use. This is a
     * navigation aid only (a different reference point, so not comparable to the historical episodes);
     * the historical detection above is left untouched.
     *
     * The local drawdown is derived from the same all-time-high drawdown series the episodes use:
     * with d = 1 - P/P_allHigh, a drawdown measured from a local peak (drawdown d_peak) is
     * 1 - (1 - d) / (1 - d_peak) = (d - d_peak) / (1 - d_peak), so no extra raw series is needed and
     * it works in every mode. The tail starts at the last episode's low (the most recent trough); the
     * local peak is the calmest day (lowest d) from there on. Null when no tail drop clears T.
     *
     * @param array<string, float> $effDd     drop-axis drawdown % by date, ascending
     * @param array<int, array{peak_date: string, low_date: string, end_date: string, max_dd: float}> $episodes
     * @param float                $T         the configured drop threshold (%)
     *
     * @return array{peak_date: string, low_date: string, end_date: string, max_dd: float, current_dd: float}|null
     */
    private function _currentDrop(array $effDd, array $episodes, float $T): ?array
    {
        $ctx = $this->_localDropContext($effDd, $episodes, $T);
        if ($ctx === null) {
            return null;
        }

        return [
            'peak_date'  => $ctx['peak_date'],
            'low_date'   => $ctx['low_date'],
            'end_date'   => $ctx['end_date'],
            'max_dd'     => round($ctx['max_dd'], 1),
            'current_dd' => round($ctx['current_dd'], 1),
        ];
    }

    /**
     * The raw local-drop context the "where are we now" overlay is built from: the tail after the
     * last episode, its most recent local peak, and the per-date LOCAL drawdown series measured from
     * that peak (drawdown rebased off the most recent local high, not the all-time high the episodes
     * use). Returns the unrounded depths, the local peak's all-time-high drawdown (so callers can
     * rebase buys onto the same axis), and the local series, so both the chart summary and the report
     * episode share one definition. Null when no tail drop clears T.
     *
     * @param array<string, float> $effDd     drop-axis drawdown % by date, ascending
     * @param array<int, array{peak_date: string, low_date: string, end_date: string, max_dd: float}> $episodes
     * @param float                $T         the configured drop threshold (%)
     *
     * @return array{peak_date: string, low_date: string, end_date: string, max_dd: float, current_dd: float, peak_dd: float, segment: array<string, float>}|null
     */
    private function _localDropContext(array $effDd, array $episodes, float $T): ?array
    {
        if (empty($effDd)) {
            return null;
        }
        ksort($effDd);

        // Tail = the stretch after the last episode's trough (or the whole window if none).
        $tailStart = empty($episodes) ? (string) array_key_first($effDd) : end($episodes)['low_date'];
        $tail = array_filter(
            $effDd,
            static fn ($date) => (string) $date >= (string) $tailStart,
            ARRAY_FILTER_USE_KEY
        );
        if (count($tail) < 2) {
            return null;
        }

        // Local peak: the most recent calmest day (lowest all-time-high drawdown) in the tail.
        $peakDate = (string) array_key_first($tail);
        $peakDd   = INF;
        foreach ($tail as $date => $dd) {
            if ($dd <= $peakDd) {
                $peakDd   = $dd;
                $peakDate = (string) $date;
            }
        }

        $denom = 1.0 - $peakDd / 100.0;
        if ($denom <= 0.0) {
            return null;
        }

        // Local drawdown from that peak forward; track the deepest point and the current (latest) one,
        // and keep the per-date local series so the report can rebuild the episode on the same basis.
        $segment  = [];
        $lowDate  = $peakDate;
        $maxLocal = 0.0;
        $endDate  = $peakDate;
        $curLocal = 0.0;
        foreach ($tail as $date => $dd) {
            if ((string) $date < $peakDate) {
                continue;
            }
            $local = max(0.0, ($dd - $peakDd) / $denom);
            $segment[(string) $date] = $local;
            $endDate  = (string) $date;
            $curLocal = $local;
            if ($local > $maxLocal) {
                $maxLocal = $local;
                $lowDate  = (string) $date;
            }
        }

        if ($curLocal + 1e-9 < $T) {
            return null;
        }

        return [
            'peak_date'  => $peakDate,
            'low_date'   => $lowDate,
            'end_date'   => $endDate,
            'max_dd'     => $maxLocal,
            'current_dd' => $curLocal,
            'peak_dd'    => $peakDd,
            'segment'    => $segment,
        ];
    }

    /**
     * Build the in-progress "current drop" as a full episode (actual vs guided), on the same
     * local-from-recent-peak axis the blue overlay uses, so the report can show where the user stands
     * now and whether the ladder would have them deploy more cash. It is flagged is_current and
     * carries the latest depth alongside the deepest reached; it is deliberately kept out of the
     * historical episode list (different reference point, in progress), matching the chart's framing.
     *
     * @param array<string, float> $effDd
     * @param array                $episodes     historical episodes (used only to find the tail start)
     * @param array                $bands
     * @param array                $buys         chronological [date, eur, dd] on the all-time-high axis
     * @param array<int, string>   $sellDates    SELL trade dates (Y-m-d) in the window, for the count
     * @param array<string, float> $cashSeries
     * @param float|null           $explicitPool
     * @param float                $fallbackPool
     * @param array<string, float> $vusaPrices   for the all-time-high reference behind the effective drop
     * @param int                  $userId
     *
     * @return array|null
     */
    private function _buildCurrentEpisode(
        array $effDd,
        array $episodes,
        array $bands,
        array $buys,
        array $sellDates,
        array $cashSeries,
        ?float $explicitPool,
        float $fallbackPool,
        array $vusaPrices,
        int $userId
    ): ?array
    {
        // Ignore the drop threshold here: the current standing ("where are we now") is always shown,
        // however shallow, like the regime table's "Down now" and the chart's blue band. The threshold
        // governs only the numbered historical episodes. Null only at a genuine fresh high (no drawdown).
        $ctx = $this->_localDropContext($effDd, $episodes, 0.0);
        if ($ctx === null) {
            return null;
        }

        $stallMons  = (int) config('alerts.dip_buying.stall_backstop_months', 6);
        $stallFloor = (float) config('alerts.dip_buying.min_episode_dd_pct', 10);

        // Rebase each buy's entry drawdown onto the same local-from-recent-peak axis the band uses, so
        // the actual entry depths line up with the local drop rather than the all-time-high drawdown.
        $denom     = 1.0 - $ctx['peak_dd'] / 100.0;
        $localBuys = array_map(
            function (array $buy) use ($ctx, $denom): array
            {
                $local = $denom > 0.0 ? max(0.0, ($buy['dd'] - $ctx['peak_dd']) / $denom) : $buy['dd'];

                return ['date' => $buy['date'], 'eur' => $buy['eur'], 'dd' => round($local, 2)];
            },
            $buys
        );

        $episode = $this->_buildEpisode(
            $ctx['peak_date'], $ctx['end_date'], $ctx['segment'],
            $bands, $localBuys, $cashSeries, $explicitPool, $fallbackPool, $stallFloor, $stallMons
        );

        // Deployment, net of money that came back: the cash actually consumed since the local peak
        // (cash at the peak minus cash now), so sells that returned cash reduce it, instead of the
        // gross sum of buys. This is the honest "how much of the pool have I committed" read for the
        // deploy-more decision. Keep the gross buy total only when no cash series is available.
        if (!empty($cashSeries)) {
            $pool      = (float) $episode['pool_eur'];
            $startCash = $this->_cashOnOrBefore($ctx['peak_date'], $cashSeries) ?? $pool;
            $endCash   = $this->_cashOnOrBefore($ctx['end_date'], $cashSeries) ?? $startCash;
            $netEur    = max(0.0, $startCash - $endCash);

            $episode['actual']['deployed_eur'] = round($netEur, 2);
            $episode['actual']['deployed_pct'] = $pool > 0.0
                ? round(min(100.0, $netEur / $pool * 100.0), 1)
                : 0.0;
            $episode['actual']['net_of_sells'] = true;
        }

        // Sells in the episode window, so the card can show what came back alongside the buys.
        $episode['actual']['sell_count'] = count(array_filter(
            $sellDates,
            fn ($d) => $d >= $ctx['peak_date'] && $d <= $ctx['end_date']
        ));

        $episode['is_current'] = true;
        $episode['current_dd'] = round($ctx['current_dd'], 1);
        // The two rulers and the bridge between them, so the card can show and connect both:
        //   running_dd = how far below the ALL-TIME high you are now (the effective drawdown the ladder
        //                acts on); peak_dd = how far the most recent local peak itself sat below that
        //                high. current_dd (above) is the drop from the local peak. They compound:
        //   (1 - running_dd) = (1 - peak_dd) * (1 - current_dd).
        $episode['running_dd'] = round((float) ($effDd[$ctx['end_date']] ?? 0.0), 2);
        $episode['peak_dd']    = round((float) $ctx['peak_dd'], 2);
        // The all-time high that peak_dd is measured below, on the basis driving the effective drop at
        // the local peak (VUSA price or the portfolio return index), so the card can name it.
        $episode['ath']        = $this->_effectiveAth($ctx['peak_date'], $vusaPrices, $userId);

        return $episode;
    }

    /**
     * The ladder's first active band: the shallowest band that actually deploys something (target
     * > 0), as {dd, target}. Used to explain, when a drop is still shallower than this, where the
     * ladder would first start buying. Null when no band deploys.
     *
     * @param array $bands
     *
     * @return array{dd: float, target: float}|null
     */
    private function _firstBand(array $bands): ?array
    {
        usort($bands, static fn ($a, $b) => $a['dd'] <=> $b['dd']);
        foreach ($bands as $band) {
            if ((float) $band['target'] > 0.0) {
                return ['dd' => round((float) $band['dd'], 1), 'target' => round((float) $band['target'], 1)];
            }
        }

        return null;
    }

    /**
     * Build one episode's actual-vs-guided comparison, its per-episode pool, and a plain-language
     * assessment (good / average / bad, with how far off and the impact).
     *
     * @param string                $peakDate     the episode anchor (cycle's trailing-peak high)
     * @param string                $endDate      the cycle end (recovery day before a new high, or today)
     * @param array<string, float>  $segment      date => dd within the episode
     * @param array                 $bands
     * @param array                 $buys
     * @param array<string, float>  $cashSeries
     * @param float|null            $explicitPool
     * @param float                 $fallbackPool
     * @param float                 $stallFloor   live ladder floor for the stall backstop (band 1)
     * @param int                   $stallMons
     *
     * @return array
     */
    private function _buildEpisode(
        string $peakDate,
        string $endDate,
        array $segment,
        array $bands,
        array $buys,
        array $cashSeries,
        ?float $explicitPool,
        float $fallbackPool,
        float $stallFloor,
        int $stallMons
    ): array
    {
        $lowDate = (string) array_keys($segment, max($segment))[0];
        $maxDd   = max($segment);

        // Per-episode pool: the explicit override, else the user's cash at the episode start.
        $poolEur = $explicitPool
            ?? ($this->_cashOnOrBefore($peakDate, $cashSeries) ?? $fallbackPool);
        $poolEur = $poolEur > 0.0 ? $poolEur : $fallbackPool;

        // Actual: buys from the episode anchor (its peak) through its end.
        $episodeBuys = array_values(array_filter(
            $buys,
            fn ($b) => $b['date'] >= $peakDate && $b['date'] <= $endDate
        ));

        $actual = $this->_actualTimeline($episodeBuys, $poolEur, $maxDd);
        $guided = $this->_guidedTimeline($bands, $poolEur, $maxDd, $actual['exhaustion_dd']);

        // Cash drag: the stall backstop would have released cash the user left idle. True when the
        // episode stalled long enough to trigger the backstop yet actual deployment lagged guided.
        $stall    = $this->_engine->computeStall($segment, $bands, $stallMons, $stallFloor, Carbon::parse($endDate));
        $cashDrag = $stall['active'] && $actual['deployed_pct'] + 1e-9 < $guided['target_pct'];

        return [
            'peak_date'        => $peakDate,
            'low_date'         => $lowDate,
            'max_dd'           => round($maxDd, 1),
            'pool_eur'         => round($poolEur, 2),
            'actual'           => $actual,
            'guided'           => $guided,
            'early_exhaustion' => $actual['early_exhaustion'],
            'cash_drag'        => $cashDrag,
            'stall'            => $stall,
            'assessment'       => $this->_assessEpisode($actual, $guided, $maxDd, $cashDrag),
        ];
    }

    /**
     * Score one episode for the report: a good/average/bad status plus how far the user was from the
     * ladder (entry-drawdown gap and deployment gap) and the impact (reserve the ladder would have
     * kept). Positive entry_dd_delta means the ladder would have bought that many points lower.
     *
     * @param array $actual
     * @param array $guided
     * @param float $maxDd
     * @param bool  $cashDrag
     *
     * @return array{status: string, entry_dd_delta: float, deploy_gap_pct: float, reserve_kept_eur: float, headline: string}
     */
    private function _assessEpisode(array $actual, array $guided, float $maxDd, bool $cashDrag): array
    {
        $entryDdDelta = round((float) $guided['avg_entry_dd'] - (float) $actual['avg_entry_dd'], 1);
        $deployGap    = round((float) $guided['target_pct'] - (float) $actual['deployed_pct'], 1);
        $reserveKept  = (float) $guided['reserve_kept_eur'];

        if ($actual['early_exhaustion'] || $cashDrag) {
            $status = 'bad';
        } elseif (abs($entryDdDelta) <= 3.0 && abs($deployGap) <= 10.0) {
            $status = 'good';
        } else {
            $status = 'average';
        }

        $reserve   = MoneyFormat::get_formatted_number_plain($reserveKept, 0);
        $maxDdText = round($maxDd, 2);

        $entryDeeper = abs($entryDdDelta);

        if ($actual['early_exhaustion']) {
            $headline = "You went all-in around -{$actual['exhaustion_dd']}%, but the drop reached "
                . "-{$maxDdText}%. The ladder would have entered about {$entryDeeper} drawdown points "
                . "deeper on average and kept EUR {$reserve} of the pool in reserve for that deeper leg.";
        } elseif ($cashDrag) {
            $headline = "Cash sat idle as the dip dragged on; the stall backstop would have kept "
                . "deploying toward the {$guided['target_pct']}%-of-pool target instead of leaving it parked.";
        } elseif ($status === 'good') {
            $headline = 'Your pacing tracked the ladder closely (entry depth within '
                . $entryDeeper . ' drawdown points, amount deployed within ' . abs($deployGap)
                . ' percentage points of the pool).';
        } elseif ($deployGap > 0.0) {
            $headline = "You deployed {$deployGap} percentage points less of your pool than the ladder "
                . "targets at this depth; it would also have entered about {$entryDeeper} drawdown points "
                . "deeper on average.";
        } else {
            $headline = 'You deployed ' . abs($deployGap) . ' percentage points more of your pool than '
                . 'the ladder targets at this depth, leaving less in reserve for a deeper leg.';
        }

        return [
            'status'           => $status,
            'entry_dd_delta'   => $entryDdDelta,
            'deploy_gap_pct'   => $deployGap,
            'reserve_kept_eur' => round($reserveKept, 2),
            'headline'         => $headline,
        ];
    }

    /**
     * The actual deployment timeline for an episode's buys: deployed %, weighted average entry
     * drawdown, and whether the user exhausted the pool while still shallow before it went deeper.
     *
     * @param array $buys     [date, eur, dd_at_buy]
     * @param float $poolEur
     * @param float $maxDd    deepest drawdown the episode eventually reached
     *
     * @return array
     */
    private function _actualTimeline(array $buys, float $poolEur, float $maxDd): array
    {
        $cumEur       = 0.0;
        $weightedDd   = 0.0;
        $exhaustionDd = null;

        foreach ($buys as $buy) {
            $cumEur     += $buy['eur'];
            $weightedDd += $buy['eur'] * $buy['dd'];
            if ($exhaustionDd === null
                && $poolEur > 0.0
                && $cumEur / $poolEur * 100.0 >= self::EXHAUSTION_PCT
            ) {
                $exhaustionDd = $buy['dd'];
            }
        }

        $deployedPct = $poolEur > 0.0 ? min(100.0, $cumEur / $poolEur * 100.0) : 0.0;
        $avgEntryDd  = $cumEur > 0.0 ? $weightedDd / $cumEur : 0.0;

        // Early exhaustion: hit ~100% at a shallow drawdown, then the episode went materially deeper.
        $earlyExhaustion = $exhaustionDd !== null
            && $maxDd > $exhaustionDd + self::DEEPER_MARGIN_DD;

        return [
            'deployed_eur'     => round($cumEur, 2),
            'deployed_pct'     => round($deployedPct, 1),
            'avg_entry_dd'     => round($avgEntryDd, 1),
            'exhaustion_dd'    => $exhaustionDd !== null ? round($exhaustionDd, 1) : null,
            'early_exhaustion' => $earlyExhaustion,
            'buy_count'        => count($buys),
        ];
    }

    /**
     * The guided deployment timeline for an episode: how much the ladder would have deployed by the
     * episode low, the weighted average entry drawdown of those tranches (each tranche enters at its
     * band's drawdown floor), and the reserve the ladder kept for the deep part relative to where
     * the user actually exhausted.
     *
     * @param array      $bands
     * @param float      $poolEur
     * @param float      $maxDd
     * @param float|null $actualExhaustionDd
     *
     * @return array
     */
    private function _guidedTimeline(array $bands, float $poolEur, float $maxDd, ?float $actualExhaustionDd): array
    {
        $targetPct  = (float) $this->_engine->resolveBand($maxDd, $bands)['target'];

        // Weighted average entry drawdown: each band increment enters at that band's floor.
        $prevTarget = 0.0;
        $weighted   = 0.0;
        foreach ($bands as $band) {
            if ($band['dd'] > $maxDd + 1e-9 || $band['target'] <= 0.0) {
                continue;
            }
            $increment   = $band['target'] - $prevTarget;
            $weighted   += $increment * $band['dd'];
            $prevTarget  = $band['target'];
        }
        $avgEntryDd = $targetPct > 0.0 ? $weighted / $targetPct : 0.0;

        // Reserve kept for the deep part: at the drawdown where the user actually exhausted the pool,
        // the ladder would still have held back this much.
        $reserveRefDd  = $actualExhaustionDd ?? $maxDd;
        $deployedAtRef = (float) $this->_engine->resolveBand($reserveRefDd, $bands)['target'];
        $reserveKept   = $poolEur * (100.0 - $deployedAtRef) / 100.0;

        return [
            'target_pct'      => round($targetPct, 1),
            'target_eur'      => round($targetPct / 100.0 * $poolEur, 2),
            'avg_entry_dd'    => round($avgEntryDd, 1),
            'reserve_kept_eur' => round($reserveKept, 2),
        ];
    }

    /**
     * The three baseline returns to clear, measured on VUSA over the full window: stay fully
     * invested (deploy the whole pool at the start), monthly DCA (equal monthly tranches), and the
     * guided ladder (deploy each band's tranche when its drawdown floor is first reached).
     *
     * The baselines are pool-independent (they are percentage returns), so they need no pool size.
     *
     * @param array<string, float> $vusaPrices
     * @param array                $bands
     *
     * @return array{stay_invested_pct: ?float, dca_pct: ?float, guided_pct: ?float}
     */
    private function _baselines(array $vusaPrices, array $bands): array
    {
        ksort($vusaPrices);
        $dates   = array_keys($vusaPrices);
        $endDate = end($dates);
        $endPx   = $vusaPrices[$endDate];

        return [
            'stay_invested_pct' => $this->_stayInvestedReturn($vusaPrices, $endPx),
            'dca_pct'           => $this->_dcaReturn($vusaPrices, $endPx),
            'guided_pct'        => $this->_guidedReturn($vusaPrices, $bands, $endPx),
        ];
    }

    /**
     * Stay-fully-invested baseline: deploy the whole pool on day one, hold to the end.
     */
    private function _stayInvestedReturn(array $vusaPrices, float $endPx): ?float
    {
        $startPx = reset($vusaPrices);
        if (!$startPx || $startPx <= 0.0) {
            return null;
        }

        return round(($endPx / $startPx - 1.0) * 100.0, 1);
    }

    /**
     * Monthly-DCA baseline: equal tranche on the first trading day of each month, held to the end.
     */
    private function _dcaReturn(array $vusaPrices, float $endPx): ?float
    {
        $byMonth = [];
        foreach ($vusaPrices as $date => $price) {
            $month = substr($date, 0, 7);
            if (!isset($byMonth[$month])) {
                $byMonth[$month] = $price; // first trading day of the month
            }
        }
        if (empty($byMonth)) {
            return null;
        }

        $units = 0.0;
        $spent = 0.0;
        $per   = 1.0 / count($byMonth);
        foreach ($byMonth as $price) {
            if ($price > 0.0) {
                $units += $per / $price;
                $spent += $per;
            }
        }
        if ($spent <= 0.0) {
            return null;
        }

        return round(($units * $endPx / $spent - 1.0) * 100.0, 1);
    }

    /**
     * Guided-ladder baseline: deploy each band's incremental tranche at the VUSA price on the day
     * its drawdown floor is first reached, hold the rest in cash, value everything at the end.
     */
    private function _guidedReturn(array $vusaPrices, array $bands, float $endPx): ?float
    {
        ksort($vusaPrices);
        $ddSeries = $this->_vusaDrawdownSeries($vusaPrices);

        $deployedTarget = 0.0;
        $units          = 0.0;
        $cash           = 1.0; // one unit of pool

        foreach ($ddSeries as $date => $dd) {
            $target = (float) $this->_engine->resolveBand($dd, $bands)['target'] / 100.0;
            if ($target > $deployedTarget + 1e-9) {
                $tranche        = $target - $deployedTarget;
                $price          = $vusaPrices[$date];
                if ($price > 0.0) {
                    $units += $tranche / $price;
                    $cash  -= $tranche;
                }
                $deployedTarget = $target;
            }
        }

        $endValue = $units * $endPx + max(0.0, $cash);

        return round(($endValue - 1.0) * 100.0, 1);
    }

    /**
     * The user's BUY trades in the window, in EUR, each tagged with the effective drawdown on its
     * date (the same axis episodes are detected on).
     *
     * @param array<string, float> $effDd effective drawdown % by date
     *
     * @return array<int, array{date: string, eur: float, dd: float}>
     */
    private function _loadBuysInEur(int $userId, string $from, string $to, array $eurRates, array $effDd): array
    {
        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('action', 'BUY')
            ->where('timestamp', '>=', $from . ' 00:00:00')
            ->where('timestamp', '<=', $to . ' 23:59:59')
            ->with(['tradeCurrencyModel', 'accountModel.currency'])
            ->orderBy('timestamp')
            ->get();

        $buys = [];
        foreach ($trades as $trade) {
            if (!empty($trade->is_transfer)) {
                continue;
            }
            $tradeCur = $trade->tradeCurrencyModel->iso_code ?? 'EUR';
            $acctCur  = $trade->accountModel->currency->iso_code ?? 'EUR';
            $eur      = (float) $trade->quantity * (float) $trade->unit_price * ($eurRates[$tradeCur] ?? 1.0)
                + (float) $trade->fee * ($eurRates[$acctCur] ?? 1.0);

            $date = $trade->timestamp->format('Y-m-d');
            $buys[] = ['date' => $date, 'eur' => $eur, 'dd' => $this->_ddOnOrBefore($date, $effDd)];
        }

        return $buys;
    }

    /**
     * The user's SELL trade dates (Y-m-d) in the window, transfers excluded. Only the dates are
     * needed (to count sells per episode); the EUR proceeds are already reflected in the net-deployed
     * cash figure, so they are not reloaded here.
     *
     * @param int    $userId
     * @param string $from
     * @param string $to
     *
     * @return array<int, string>
     */
    private function _loadSellDates(int $userId, string $from, string $to): array
    {
        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('action', 'SELL')
            ->where('timestamp', '>=', $from . ' 00:00:00')
            ->where('timestamp', '<=', $to . ' 23:59:59')
            ->orderBy('timestamp')
            ->get(['timestamp', 'is_transfer']);

        $dates = [];
        foreach ($trades as $trade) {
            if (!empty($trade->is_transfer)) {
                continue;
            }
            $dates[] = $trade->timestamp->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * The VUSA drawdown on a date, carried forward from the latest earlier trading day.
     */
    private function _ddOnOrBefore(string $date, array $ddSeries): float
    {
        ksort($ddSeries);
        $dd = 0.0;
        foreach ($ddSeries as $d => $value) {
            if ($d > $date) {
                break;
            }
            $dd = $value;
        }

        return $dd;
    }

    /**
     * Currency => to-EUR multiplier map (same approach as the live engine, kept local so the
     * backtest is self-contained).
     *
     * @return array<string, float>
     */
    private function _buildEurRates(): array
    {
        $rates  = ['EUR' => 1.0];
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
                $rates[$currency] = ((float) $stat->unit_price > 0.0) ? 1.0 / (float) $stat->unit_price : 1.0;
            }
        }
        if (isset($rates['GBP'])) {
            $rates['GBp'] = $rates['GBP'] / 100.0;
            $rates['GBX'] = $rates['GBP'] / 100.0;
        }

        return $rates;
    }

    /**
     * A short plain-language headline summarising the episodes.
     *
     * @param array $episodes
     *
     * @return string
     */
    private function _headline(array $episodes): string
    {
        if (empty($episodes)) {
            return 'No drawdown episodes in this window.';
        }

        $count        = count($episodes);
        $maxReserve   = 0.0;
        $bestDelta    = 0.0;
        foreach ($episodes as $ep) {
            $maxReserve = max($maxReserve, (float) $ep['guided']['reserve_kept_eur']);
            $delta      = (float) $ep['guided']['avg_entry_dd'] - (float) $ep['actual']['avg_entry_dd'];
            $bestDelta  = max($bestDelta, $delta);
        }

        $reserve = MoneyFormat::get_formatted_number_plain($maxReserve, 0);
        $deeper  = MoneyFormat::get_formatted_number_plain($bestDelta, 0);

        return "Across {$count} episode(s), the ladder would have kept up to EUR {$reserve} in reserve "
            . "for drops you actually saw and entered up to {$deeper} drawdown points lower on average.";
    }

    /**
     * @param string $from
     * @param string $to
     * @param string $note
     *
     * @return array
     */
    private function _emptyReport(string $from, string $to, string $note): array
    {
        return [
            'from'            => $from,
            'to'              => $to,
            'min_drop'        => (float) config('alerts.dip_buying.min_drop_pct', 5),
            'drop_mode'       => self::DEFAULT_MODE,
            'pool_source'     => 'fallback',
            'pool_eur'        => null,
            'episodes'        => [],
            'current_episode' => null,
            'first_band'      => null,
            'baselines'       => ['stay_invested_pct' => null, 'dca_pct' => null, 'guided_pct' => null],
            'headline'        => $note,
            'note'            => 'Single-path personal sanity check, not statistical proof.',
        ];
    }
}
