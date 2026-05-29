<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Cache;

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
        ?array $performance = null
    ): array
    {
        // Caching is intentionally disabled while the categorization framework is being
        // finalised, so tier-logic changes appear on the next page load without a cache
        // clear. To re-enable, wrap this in:
        //   Cache::remember(self::CACHE_PREFIX . $userId, self::CACHE_TTL, fn () => $this->build($userId, $livePerformance, $positionReturns, $performance));
        return $this->build($userId, $livePerformance, $positionReturns, $performance);
    }

    public function rebuild(int $userId): array
    {
        // While caching is off this just recomputes; it does not persist anything.
        // To re-enable, Cache::put(self::CACHE_PREFIX . $userId, $data, self::CACHE_TTL).
        return $this->build($userId);
    }

    public static function clearCache(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . $userId);
    }

    public function build(
        int $userId,
        array $livePerformance = [],
        array $positionReturns = [],
        ?array $performance = null
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
        $tiers       = new TierCalculationService();
        $classifier  = new TierClassifier($tiers);
        $overrides   = $tiers->loadOverrides($userId);

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
            $decision = $classifier->classify($inputs, $overrides->get($symbol));

            $relDrawdown = $dd['relative_drawdown'] ?? null;
            $quadrant    = QuadrantClassifier::classify($decision->basisValue, $relDrawdown);
            $action      = QuadrantClassifier::getAction($quadrant, $inputs->isOwned);

            $momenta   = $dd['momenta'] ?? [];
            $relDds    = $dd['relative_drawdowns'] ?? [];
            $exitZones = $dd['exit_zones'] ?? [];

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
                    'gain'      => $gain,
                    'risk'      => $risk,
                    'quadrant'  => $quad,
                    'action'    => QuadrantClassifier::getAction($quad, $inputs->isOwned),
                    'exit_zone' => $exitZones[$p] ?? null,
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
            ]);
        }

        return $result;
    }
}
