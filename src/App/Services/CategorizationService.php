<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use ovidiuro\myfinance2\App\Models\SymbolTierState;

/**
 * Builds and caches the per-symbol categorisation map (tier decision, quadrant,
 * drawdown) for a user. This is the ONLY producer of categorisation data:
 * both the minutely cron (pre-warm) and the dashboard (lazy on cache miss)
 * call it, so the table, the health card and the quadrant chart can never
 * disagree about a symbol's tier.
 *
 * Returns symbol => entry, where each entry merges TierDecision::toArray() with
 * the quadrant/action and drawdown fields the views consume.
 */
final class CategorizationService
{
    private const CACHE_PREFIX = 'categorization_v6_u';
    private const CACHE_TTL    = 7200; // 2 hours

    /**
     * @param array $livePerformance  Optional symbol => performance map already patched with
     *                                 live prices. When provided it overlays the cached snapshot
     *                                 so the tier basis matches exactly what the dashboard displays.
     * @param array $positionReturns   Optional symbol => ['raw_pct' => ?float, 'days' => int]
     *                                  fallback used when a symbol has no performance-service
     *                                  return (e.g. unlisted holdings valued by manual FMV).
     */
    public function forUser(
        int $userId,
        array $livePerformance = [],
        array $positionReturns = [],
        ?array $performance = null,
        array $liveEurPrices = []
    ): array
    {
        // Caching is intentionally disabled while the categorization framework is being
        // finalised, so tier-logic changes appear on the next page load without a cache
        // clear. To re-enable, wrap this in:
        //   Cache::remember(self::CACHE_PREFIX . $userId, self::CACHE_TTL, fn () => $this->build($userId, $livePerformance, $positionReturns, $performance, liveEurPrices: $liveEurPrices));
        return $this->build($userId, $livePerformance, $positionReturns, $performance, liveEurPrices: $liveEurPrices);
    }

    public function rebuild(int $userId): array
    {
        // While caching is off this just recomputes; it does not persist anything.
        // To re-enable, Cache::put(self::CACHE_PREFIX . $userId, $data, self::CACHE_TTL).
        //
        // The cron is the single writer of the per-symbol tier STATE (today's and the settled tier of
        // the run, see _persistTierStates()), so it passes persistState. The dashboard read path never
        // writes it, which keeps live-vs-snapshot price differences from churning the stored tiers.
        return $this->build($userId, persistState: true);
    }

    public static function clearCache(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . $userId);
    }

    public function build(
        int $userId,
        array $livePerformance = [],
        array $positionReturns = [],
        ?array $performance = null,
        bool $persistState = false,
        array $liveEurPrices = []
    ): array
    {
        // Reuse the caller's already-computed performance map when provided (the watchlist
        // dashboard builds it once for the table), so this does not re-run the full
        // SymbolPerformanceService query set a second time per request. Standalone callers
        // pass null and get a fresh compute.
        $performance ??= (new SymbolPerformanceService())->handle($userId);
        // Prefer the caller's live-priced performance where available, keeping any
        // snapshot-only symbols (e.g. delisted exits) that the caller did not pass.
        if (!empty($livePerformance)) {
            $performance = array_merge($performance, $livePerformance);
        }
        $drawdown    = (new DrawdownService())->handle($userId);
        // Overlay the latest live price (pre/post-market aware) onto the cached, pure-historical
        // drawdown so the quadrant's market return and peak proximity reflect the current price
        // instead of the last regular close. Only on the on-demand dashboard path (live prices
        // supplied); the cron's persisted tier states stay on settled historical data.
        if (!empty($liveEurPrices)) {
            foreach ($liveEurPrices as $sym => $live) {
                // Run the overlay when either price is present. The native price alone drives peak
                // proximity, so foreign-currency symbols with no live EUR rate still get a live
                // proximity instead of being stuck on their last settled close.
                if (isset($drawdown[$sym]) && (!empty($live['eur']) || !empty($live['native']))) {
                    $drawdown[$sym] = DrawdownService::overlayLivePrice(
                        $drawdown[$sym], (float) ($live['eur'] ?? 0.0), $live['native'] ?? null
                    );
                }
            }
        }
        $tiers       = new TierCalculationService();
        $classifier  = TierClassifier::fromConfig($tiers);
        // The legacy own-return rule, for the health card's approach comparison only (no override).
        $ownClassifier = new TierClassifier($tiers, false);
        $overrides   = $tiers->loadOverrides($userId);
        $history     = new CategorizationTierHistory($userId, Carbon::today());

        $symbols = array_values(array_unique(array_merge(
            array_keys($performance),
            array_keys($drawdown),
            array_keys($positionReturns)
        )));

        $result = [];
        foreach ($symbols as $symbol)
        {
            $perf     = $performance[$symbol] ?? ['has_data' => false];
            $dd       = $drawdown[$symbol] ?? null;
            $inputs   = TierInputs::fromData($symbol, $perf, $dd, $positionReturns[$symbol] ?? null);
            $decision    = $classifier->classify($inputs, $overrides->get($symbol));
            $ownDecision = $ownClassifier->classify($inputs, null);

            $relDrawdown = $dd['relative_drawdown'] ?? null;
            $quadrant    = QuadrantClassifier::classify($decision->basisValue, $relDrawdown);
            $action      = QuadrantClassifier::getAction($quadrant, $inputs->isOwned);

            $momenta    = $dd['momenta'] ?? [];
            $momWindows = $dd['momentum_windows'] ?? [];
            $relDds     = $dd['relative_drawdowns'] ?? [];
            $exitZones  = $dd['exit_zones'] ?? [];

            // Alpha vs the S&P 500 (VUSA.AS) over the SAME window you held: your position's
            // own CAGR minus the benchmark's CAGR across the identical span. Positive means you
            // beat the index, negative means the index would have done better. Both sides are
            // CAGRs, so the comparison is apples-to-apples. Null unless both figures exist.
            //
            // Held under a year there is no annualized CAGR on either side, so fall back to a raw
            // (non-annualized) return difference over the same dates and flag it short, letting the
            // view mark it provisional until a full-year CAGR comparison becomes available.
            $vusaSameWindowPct    = $dd['vusa_same_window_pct'] ?? null;
            $vusaSameWindowRawPct = $dd['vusa_same_window_raw_pct'] ?? null;
            $ownCagrPct           = $perf['annualized_percentage_gain'] ?? null;
            $ownRawPct            = $perf['percentage_gain'] ?? null;

            $alphaVsVusaPct = null;
            $alphaIsShort   = false;
            if ($ownCagrPct !== null && $vusaSameWindowPct !== null) {
                $alphaVsVusaPct = $ownCagrPct - $vusaSameWindowPct;
            } elseif ($ownRawPct !== null && $vusaSameWindowRawPct !== null) {
                $alphaVsVusaPct = $ownRawPct - $vusaSameWindowRawPct;
                $alphaIsShort   = true;
            }

            $periods = [];
            foreach (['3m', '6m', '1y', '2y'] as $p) {
                $gain   = isset($momenta[$p]) ? round($momenta[$p], 2) : null;
                $risk   = isset($relDds[$p])  ? round($relDds[$p], 2) : null;
                $quad   = ($gain !== null && $risk !== null)
                    ? QuadrantClassifier::classify($gain, $risk)
                    : null;
                $periods[$p] = [
                    'gain'        => $gain,
                    'risk'        => $risk,
                    'quadrant'    => $quad,
                    'action'      => QuadrantClassifier::getAction($quad, $inputs->isOwned),
                    'exit_zone'   => $exitZones[$p] ?? null,
                    'gain_window' => $momWindows[$p] ?? null,
                ];
            }

            $result[$symbol] = array_merge($decision->toArray(), [
                'quadrant'          => $quadrant,
                'action'            => $action,
                'annualized_pct'    => $decision->basisValue,
                'relative_drawdown' => $relDrawdown,
                'max_drawdown'      => $dd['max_drawdown'] ?? null,
                'vusa_max_drawdown' => $dd['vusa_max_drawdown'] ?? null,
                'exit_zone'         => $dd['exit_zone'] ?? null,
                'exit_zones'        => $exitZones,
                'closing_extremes'  => $dd['closing_extremes'] ?? null,
                'momenta'           => $momenta,
                'periods'           => $periods,
                'vusa_same_window_pct'     => $vusaSameWindowPct,
                'vusa_same_window_raw_pct' => $vusaSameWindowRawPct,
                'alpha_vs_vusa_pct'        => $alphaVsVusaPct,
                'alpha_is_short_period'    => $alphaIsShort,
                'xirr_pct'                 => $perf['xirr_pct'] ?? null,
                // Held under a year, an annualized XIRR extrapolates a sub-year window, so the view
                // flags it provisional (matching the alpha and gain/y short-period treatment).
                'xirr_is_short_period'     => $inputs->holdingDays > 0 && $inputs->holdingDays < 365,
                'own_rule'                 => [
                    'tier'        => $ownDecision->computedTier,
                    'value'       => $ownDecision->basisValue,
                    'basis'       => $ownDecision->basis,
                    'basis_label' => TierDecision::basisLabel($ownDecision->basis),
                ],
            ], $history->entryFor($symbol, $decision));
        }

        // Each symbol's tier under every approach (TierApproachCatalog), for the watchlist's "Today" line and
        // the health card's approach comparison.
        foreach ((new TierApproachComparison())->cellsForAll($result, $history->window(), Carbon::today())
            as $symbol => $cells
        ) {
            $result[$symbol]['approach_cells'] = $cells;
            // Platinum or Gold under most approaches: the star on the Tier line and the approach comparison.
            $result[$symbol]['superstar']      = TierApproachComparison::superstar($cells);
        }

        $result = $this->_attachPositionReadings($result, $drawdown, $performance, $positionReturns);

        if ($persistState) {
            $this->_persistTierStates($userId, $result);
        }

        return $result;
    }

    /**
     * The readings that look past the tier at the position: range-bound (RangeBoundPattern, on today's price: live
     * on the dashboard, the last close in the cron) and then weak tier in profit (WeakTierProfit, on the open
     * position's gain: the dashboard's position figure, else the performance windows), then the tier signal chip
     * (TierSignalChip: rare high, rare low, deteriorating). Each is null when it does not hold; a range-bound symbol
     * is never weak-tier-in-profit, and on either of them the tier signal defers (no chip of its own, named in the
     * winning reading's tooltip instead), so one symbol shows one chip.
     */
    private function _attachPositionReadings(
        array $result,
        array $drawdown,
        array $performance,
        array $positionReturns
    ): array
    {
        $rangeBound = RangeBoundPattern::fromConfig();
        $weakProfit = WeakTierProfit::fromConfig();
        $tierSignal = TierSignalChip::fromConfig();
        foreach ($result as $symbol => $entry) {
            $dd   = $drawdown[$symbol] ?? [];
            $perf = $performance[$symbol] ?? [];
            $entry['range_bound'] = $rangeBound->detect(
                $entry, $perf, $dd['live_price_eur'] ?? ($dd['latest_price_eur'] ?? null)
            );

            [$gainPct, $gainEur] = isset($positionReturns[$symbol]['raw_pct'])
                ? [(float) $positionReturns[$symbol]['raw_pct'], $positionReturns[$symbol]['gain_eur'] ?? null]
                : WeakTierProfit::openGain($perf);
            $entry['take_profit'] = $weakProfit->detect(
                $entry, $gainPct, $gainEur !== null ? (float) $gainEur : null
            );
            $entry['tier_signal_chip'] = $tierSignal->detect($entry);
            $result[$symbol] = TierSignalChip::noteOnWinner($entry);
        }

        return $result;
    }

    /**
     * Persist each symbol's tier of the run (today's reading), the settled tier (12-month median), the day the
     * settled tier was first seen and the range-bound, weak-tier-in-profit and tier signal readings (for the alerts
     * page). Written via the query builder (not Eloquent) so it bypasses the base model's creating hook, which sets
     * user_id from the authenticated user, unavailable in the cron context. Only the cron calls this (single writer);
     * the dashboard read path never persists. Rows of symbols no longer traded or watched are soft-deleted; a symbol
     * that comes back is revived.
     *
     * @param array $result symbol => categorization entry (must carry 'computed_tier'; 'settled_tier' optional)
     */
    private function _persistTierStates(int $userId, array $result): void
    {
        $query = DB::connection((new SymbolTierState())->getConnectionName())
            ->table((new SymbolTierState())->getTable());

        $previous = (clone $query)->where('user_id', $userId)->whereNull('deleted_at')
            ->get(['symbol', 'settled_tier', 'settled_since'])
            ->keyBy('symbol');

        $rows  = [];
        $now   = now();
        $today = $now->toDateString();
        foreach ($result as $symbol => $entry) {
            $tier = $entry['computed_tier'] ?? null;
            if ($tier === null) {
                // Nothing settled to remember (Unrated); leave any prior state untouched.
                continue;
            }
            $settledTier = $entry['settled_tier'] ?? null;
            $prior       = $previous->get($symbol);
            $rows[] = [
                'user_id'       => $userId,
                'symbol'        => $symbol,
                'tier'          => $tier,
                'settled_tier'  => $settledTier,
                'settled_since' => self::settledSince(
                    $settledTier,
                    $prior->settled_tier ?? null,
                    $prior->settled_since ?? null,
                    $today
                ),
                'range_bound'   => !empty($entry['range_bound']) ? json_encode($entry['range_bound']) : null,
                'take_profit'   => !empty($entry['take_profit']) ? json_encode($entry['take_profit']) : null,
                'tier_signal'   => !empty($entry['tier_signal_chip']) ? json_encode($entry['tier_signal_chip']) : null,
                'created_at'    => $now,
                'updated_at'    => $now,
                'deleted_at'    => null,
            ];
        }

        if (empty($rows)) {
            return;
        }

        $query->upsert(
            $rows,
            ['user_id', 'symbol'],
            ['tier', 'settled_tier', 'settled_since', 'range_bound', 'take_profit', 'tier_signal', 'updated_at',
                'deleted_at']
        );
        (clone $query)->where('user_id', $userId)->whereNull('deleted_at')
            ->whereNotIn('symbol', array_keys($result))
            ->update(['deleted_at' => $now, 'updated_at' => $now]);
    }

    /**
     * The day the settled tier was first seen: kept while it is unchanged, today when it changes, null while there
     * is no settled tier (history too short).
     */
    public static function settledSince(
        ?string $settledTier,
        ?string $priorTier,
        ?string $priorSince,
        string $today
    ): ?string
    {
        if ($settledTier === null) {
            return null;
        }

        return $settledTier === $priorTier && $priorSince !== null ? $priorSince : $today;
    }
}
