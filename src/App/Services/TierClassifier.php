<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\SymbolTierOverride;

/**
 * Single source of truth for tier categorisation.
 *
 * THE FRAMEWORK
 * -------------
 * A tier (Platinum/Gold/Silver/Bronze/Rust) is just a bucketing of one return
 * percentage against fixed thresholds (see TierCalculationService::getTier).
 * The only real decision is *which* return percentage to bucket. This class
 * makes that decision with one ordered rule set, identical for every caller.
 *
 * Owned position (priority order):
 *   1. Annualized return  - held >= 1 year, your CAGR (geometric,    confidence: high
 *                           time-weighted across all windows; comparable to an index).
 *   2. Raw return         - held 3-12 months, your overall         confidence: medium
 *                           position return compared directly (NOT annualized, so a
 *                           fast short-term move cannot be extrapolated into Platinum).
 *   3. Market 1Y          - held < 3 months: too new for its own   confidence: medium
 *                           return to be comparable to the annual thresholds, so the
 *                           symbol's trailing 12-month market return is the proxy.
 *                           Only used when that figure is trustworthy (see guard below).
 *   4. Raw return         - held < 3 months AND market 1Y is       confidence: low
 *                           missing/untrustworthy: the real but noisy raw return.
 *   5. Unrated            - nothing available; tier = null.
 *
 * Watchlist-only / exited (no current holding):
 *   1. Market 1Y          - the forward-looking signal (guarded).  confidence: high
 *   2. Raw return         - realized return when no market data.   confidence: medium
 *   3. Unrated            - nothing available; tier = null.
 *
 * Market guard: a trailing 12-month return is only trusted when it falls in a
 * plausible band. Recently listed or spun-off symbols can leave a broken price
 * reference that yields returns in the thousands of percent; those are rejected so
 * they cannot mislabel a tier (owned positions fall back to raw return; watchlist
 * symbols with no other data become Unrated).
 *
 * A manual override always wins (even over Unrated) and reports high confidence.
 * Confidence drives the FE warning icon, which is shown ONLY for low confidence
 * (a brand-new position with no trustworthy market proxy). A settled raw return or
 * a market-proxy tier is a legitimate basis and is not flagged.
 *
 * EDGE CASES
 * ----------
 * - Owned positions almost always have a raw return (market value minus cost),
 *   so Unrated is effectively reserved for watchlist symbols with no price
 *   history. Unrated symbols are excluded from the portfolio health buckets.
 * - "Held >= 1 year" tracks SymbolPerformanceService, which only emits an
 *   annualized figure once total holding time reaches 365 days. Raw return is
 *   used below that, deliberately un-annualized.
 * - isOwned is derived from open performance windows, so the cron (no live
 *   positions) and the dashboard (live positions) agree on the same tier.
 */
final class TierClassifier
{
    // A position must be held at least this long before its own raw return is
    // trusted. Below this it is too new for its day-level return to be comparable
    // against the annual tier thresholds, so the symbol's market 1Y return is used
    // as an apples-to-apples proxy instead.
    private const MATURE_HOLDING_DAYS = 90;

    // Plausible band for a trailing 12-month market return. Values outside this band
    // are treated as data artifacts (e.g. a recent listing or spinoff leaving a
    // broken price reference, which can produce returns in the thousands of percent)
    // and are not trusted as a tier basis.
    private const MARKET_1Y_MAX_PCT = 200.0;
    private const MARKET_1Y_MIN_PCT = -50.0;

    // How far the lifetime overall return must diverge from the current open window's own return
    // before the headline figure is called stale. A few points is just the prior window's small
    // contribution and not worth flagging; a large gap (e.g. a +40% lifetime return sitting behind
    // a freshly re-entered lot, or a banked winner masking a current loss) is the case to warn on.
    private const STALE_DIVERGENCE_PCT = 5.0;

    public function __construct(private readonly TierCalculationService $tiers)
    {
    }

    /**
     * @param ?string $previousTier  The symbol's last settled computed tier (from SymbolTierState),
     *                               used to damp boundary chatter via hysteresis. Null on first sight.
     */
    public function classify(
        TierInputs $inputs,
        ?SymbolTierOverride $override,
        ?string $previousTier = null
    ): TierDecision
    {
        [$basis, $value, $confidence] = $this->_resolveBasis($inputs);

        // Hysteresis: keep the previously settled tier unless the value crosses the boundary
        // dead-band, so a position on a tier line does not flip week to week.
        $computedTier = $basis === TierDecision::BASIS_NONE
            ? null
            : $this->tiers->getTierWithHysteresis($value, $previousTier);

        // The benchmark does not compete in its own tournament: pin it to at least Gold so a
        // soft trailing return never renders VUSA.AS below its own 10% line. A genuinely hot
        // benchmark can still show Platinum.
        $isBenchmark = $inputs->symbol === TierCalculationService::BENCHMARK_SYMBOL;
        if ($isBenchmark) {
            $computedTier = $this->_pinToGold($computedTier);
        }

        // A soft label sitting on a tier line. Suppressed for the benchmark, whose tier is pinned
        // and therefore stable by construction.
        $isBorderline = !$isBenchmark && $this->tiers->isBorderline($value);

        $overrideTier  = $override?->tier_override;
        $effectiveTier = $overrideTier ?? $computedTier;

        // A manual override replaces the computed basis, so the stale-headline warning no longer
        // applies (the override note explains the tier instead).
        $isStale     = $overrideTier === null && $this->_isStale($inputs);
        $explanation = $this->_explain(
            $inputs, $basis, $value, $override, $computedTier, $isStale, $isBenchmark
        );

        return new TierDecision(
            tier:         $effectiveTier,
            computedTier: $computedTier,
            overrideTier: $overrideTier,
            hasOverride:  $overrideTier !== null,
            overrideNote: $override?->note,
            basis:        $basis,
            basisValue:   $value,
            confidence:   $overrideTier !== null ? TierDecision::CONFIDENCE_HIGH : $confidence,
            isOwned:      $inputs->isOwned,
            candidates:   [
                'annualized_pct' => $inputs->isOwned ? $inputs->annualizedReturnPct : null,
                'raw_pct'        => $inputs->rawReturnPct,
                'market_1y_pct'  => $inputs->marketMomentumPct,
            ],
            explanation:  $explanation,
            isStale:      $isStale,
            isBenchmark:  $isBenchmark,
            isBorderline: $isBorderline
        );
    }

    /**
     * Raise a computed tier to at least Gold (the benchmark floor). A null computed tier (e.g. the
     * benchmark briefly without a usable return) still becomes Gold so the reference line holds.
     */
    private function _pinToGold(?string $computedTier): string
    {
        $order = [
            TierCalculationService::RUST     => 0,
            TierCalculationService::BRONZE   => 1,
            TierCalculationService::SILVER   => 2,
            TierCalculationService::GOLD     => 3,
            TierCalculationService::PLATINUM => 4,
        ];
        $rank = $computedTier !== null ? ($order[$computedTier] ?? 0) : 0;

        return $rank >= $order[TierCalculationService::GOLD]
            ? $computedTier
            : TierCalculationService::GOLD;
    }

    /**
     * The headline lifetime return is stale when an owned, re-entered position (>= 1 prior closed
     * window) carries an overall return that diverges materially from the return on the holding you
     * actually have now (the current open window). The tier itself stays on the lifetime return;
     * this only drives the warning. A manual override replaces the basis, so staleness no longer
     * applies. Watchlist/exited symbols are never stale: their realized return is the point.
     */
    private function _isStale(TierInputs $inputs): bool
    {
        return $inputs->isOwned
            && $inputs->priorClosedWindows >= 1
            && $inputs->currentWindowRawReturnPct !== null
            && $inputs->overallRawReturnPct !== null
            && abs($inputs->overallRawReturnPct - $inputs->currentWindowRawReturnPct) >= self::STALE_DIVERGENCE_PCT;
    }

    /**
     * @return array{0: string, 1: ?float, 2: string} [basis, value, confidence]
     */
    private function _resolveBasis(TierInputs $inputs): array
    {
        $marketUsable = $this->_marketUsable($inputs->marketMomentumPct);

        if ($inputs->isOwned)
        {
            if ($inputs->annualizedReturnPct !== null)
            {
                return [
                    TierDecision::BASIS_ANNUALIZED_RETURN,
                    $inputs->annualizedReturnPct,
                    TierDecision::CONFIDENCE_HIGH,
                ];
            }
            // Held long enough for its own raw return to be a settled basis (3-12 months).
            if ($inputs->holdingDays >= self::MATURE_HOLDING_DAYS && $inputs->rawReturnPct !== null)
            {
                return [TierDecision::BASIS_RAW_RETURN, $inputs->rawReturnPct, TierDecision::CONFIDENCE_MEDIUM];
            }
            // Too new for its own return; the symbol's market 1Y return is the proxy.
            if ($marketUsable)
            {
                return [
                    TierDecision::BASIS_MARKET_MOMENTUM,
                    $inputs->marketMomentumPct,
                    TierDecision::CONFIDENCE_MEDIUM,
                ];
            }
            // New position and no trustworthy market figure: fall back to the real,
            // if noisy, raw return and flag it.
            if ($inputs->rawReturnPct !== null)
            {
                return [TierDecision::BASIS_RAW_RETURN, $inputs->rawReturnPct, TierDecision::CONFIDENCE_LOW];
            }

            return [TierDecision::BASIS_NONE, null, TierDecision::CONFIDENCE_LOW];
        }

        // Watchlist-only or exited.
        if ($marketUsable)
        {
            return [
                TierDecision::BASIS_MARKET_MOMENTUM,
                $inputs->marketMomentumPct,
                TierDecision::CONFIDENCE_HIGH,
            ];
        }
        if ($inputs->rawReturnPct !== null)
        {
            return [
                TierDecision::BASIS_RAW_RETURN,
                $inputs->rawReturnPct,
                TierDecision::CONFIDENCE_MEDIUM,
            ];
        }

        return [TierDecision::BASIS_NONE, null, TierDecision::CONFIDENCE_LOW];
    }

    private function _explain(
        TierInputs $inputs,
        string $basis,
        ?float $value,
        ?SymbolTierOverride $override,
        ?string $computedTier,
        bool $isStale,
        bool $isBenchmark = false
    ): string
    {
        $computed = $this->_computedExplanation($inputs, $basis, $value);
        if ($isBenchmark)
        {
            // The benchmark anchors the 10% Gold line; surface its trailing figure as context
            // while making clear the tier is pinned, not earned.
            $computed = 'Benchmark (' . $inputs->symbol . '): the '
                . $this->_fmt(TierCalculationService::GOLD_THRESHOLD_PCT)
                . ' Gold line is anchored to its long-run return, so it is pinned to Gold. '
                . 'Trailing ' . $computed;
        }
        if ($isStale)
        {
            $computed .= ' ' . $this->_stalenessNote($inputs);
        }

        if ($override?->tier_override)
        {
            $computedLabel = TierCalculationService::tierLabel($computedTier) ?? 'Unrated';
            $text = 'Manual override to ' . TierCalculationService::tierLabel($override->tier_override)
                . ' (computed: ' . $computedLabel . ').';

            if ($override->note)
            {
                // Quote the free-text reason so its start and end are unambiguous
                // against the surrounding generated sentences.
                $text .= ' Reason: "' . $override->note . '".';
            }

            // Surface the original, un-overridden assessment so the reason the symbol
            // would otherwise have been categorised differently stays visible.
            return $text . ' Original assessment: ' . $computed;
        }

        return $computed;
    }

    private function _stalenessNote(TierInputs $inputs): string
    {
        $episodes = $inputs->priorClosedWindows === 1
            ? 'an earlier closed position'
            : $inputs->priorClosedWindows . ' earlier closed positions';

        return 'Note: the overall return of ' . $this->_fmt($inputs->overallRawReturnPct)
            . ' includes ' . $episodes . ' and is stale; your current holding is '
            . $this->_fmt($inputs->currentWindowRawReturnPct) . '.';
    }

    private function _computedExplanation(TierInputs $inputs, string $basis, ?float $value): string
    {
        $pct = $this->_fmt($value);

        return match ($basis)
        {
            TierDecision::BASIS_ANNUALIZED_RETURN =>
                'Annualized return (CAGR) ' . $pct . ' on your position (held over a year).',
            TierDecision::BASIS_RAW_RETURN => $this->_rawExplanation($inputs, $pct),
            TierDecision::BASIS_MARKET_MOMENTUM => $inputs->isOwned
                ? 'Market 1Y return ' . $pct . ' (position too new for a return figure).'
                : 'Market 1Y return ' . $pct . '.',
            default => 'Unrated: no return or market data available.',
        };
    }

    private function _rawExplanation(TierInputs $inputs, string $pct): string
    {
        if (!$inputs->isOwned)
        {
            return 'Realized return ' . $pct . ' (no market data available).';
        }
        if ($inputs->holdingDays >= self::MATURE_HOLDING_DAYS)
        {
            return 'Raw return ' . $pct . ' on your position (held under a year, not annualized).';
        }

        // Held < 3 months: market 1Y was the preferred proxy but could not be trusted.
        $market = $inputs->marketMomentumPct;
        if ($market === null)
        {
            $why = 'market 1Y is unavailable';
        }
        elseif ($market > self::MARKET_1Y_MAX_PCT)
        {
            $why = 'market 1Y of ' . $this->_fmt($market) . ' is treated as a data artifact'
                . ' because it exceeds the trusted ceiling of ' . $this->_fmt(self::MARKET_1Y_MAX_PCT);
        }
        else
        {
            $why = 'market 1Y of ' . $this->_fmt($market) . ' is treated as a data artifact'
                . ' because it is below the trusted floor of ' . $this->_fmt(self::MARKET_1Y_MIN_PCT);
        }

        return 'Raw return ' . $pct . ' on your position (held under 3 months; '
            . $why . '; not annualized).';
    }

    private function _marketUsable(?float $market): bool
    {
        return $market !== null
            && $market <= self::MARKET_1Y_MAX_PCT
            && $market >= self::MARKET_1Y_MIN_PCT;
    }

    private function _fmt(?float $value): string
    {
        // Match the table's percentage rounding (0 decimals once |value| >= 100), so the
        // tooltip and the displayed figure read identically (e.g. +3,773 %, not 3,772.92%).
        return $value !== null
            ? MoneyFormat::get_formatted_pct($value) . '%'
            : 'n/a';
    }
}
