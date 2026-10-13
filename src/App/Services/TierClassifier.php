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
 * Default (watchlistsymbols.tier_basis = 'market'): every symbol, owned or not, is tiered on its
 * trailing 1Y market return when that figure is trustworthy, so all symbols are judged the same way
 * and the tier moves with the price (which the rare high / rare low signals need). Your own return is
 * shown separately. Only when the market figure is missing or untrustworthy does an owned position
 * fall back to the rules below (step 3 is then skipped).
 *
 * Legacy (tier_basis = 'own'), owned position (priority order):
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

    // Trailing 12-month market returns below the floor are treated as data artifacts (typically a
    // missed split, which shows as a collapse) and are not trusted as a tier basis. Above the
    // unusual-move line the figure is used, since real rallies get there, but flagged: a missed
    // reverse split or a broken listing reference would look the same.
    private const MARKET_1Y_UNUSUAL_PCT = 200.0;
    private const MARKET_1Y_MIN_PCT     = -75.0;

    // How far the lifetime overall return must diverge from the current open window's own return
    // before the headline figure is called stale. A few points is just the prior window's small
    // contribution and not worth flagging; a large gap (e.g. a +40% lifetime return sitting behind
    // a freshly re-entered lot, or a banked winner masking a current loss) is the case to warn on.
    private const STALE_DIVERGENCE_PCT = 5.0;

    public const BASIS_MODE_MARKET = 'market';
    public const BASIS_MODE_OWN    = 'own';

    /**
     * @param bool $_marketForOwned true: owned symbols are tiered on the 1Y market return like every other
     *                              symbol; false: the legacy own-return rules.
     */
    public function __construct(
        private readonly TierCalculationService $tiers,
        private readonly bool $_marketForOwned = true
    )
    {
    }

    public static function fromConfig(TierCalculationService $tiers): self
    {
        return new self(
            $tiers,
            config('watchlistsymbols.tier_basis', self::BASIS_MODE_MARKET) !== self::BASIS_MODE_OWN
        );
    }

    /**
     * Today's tier: the plain reading on the tier lines, like every stored history day. Steadiness comes
     * from the settled tier (SettledTierResolver), not from holding yesterday's tier near a line.
     */
    public function classify(TierInputs $inputs, ?SymbolTierOverride $override): TierDecision
    {
        [$basis, $value, $confidence] = $this->_resolveBasis($inputs);

        $computedTier = $basis === TierDecision::BASIS_NONE ? null : $this->tiers->getTier($value);

        // The benchmark does not compete in its own tournament: it defines the 10% Gold line, so it
        // is the reference and sits exactly on that line. Its own noisy, timing-dependent trailing
        // return never promotes it above (or below) Gold; every other symbol is tiered relative to
        // this fixed reference. The real trailing figure is preserved as context in the explanation.
        $isBenchmark = $inputs->symbol === TierCalculationService::BENCHMARK_SYMBOL;
        if ($isBenchmark) {
            $computedTier = TierCalculationService::GOLD;
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

        // Flag the trusted-band clamp only when it actually changed the basis, and not when a manual
        // override or the pinned benchmark tier already replaces the computed decision.
        $marketArtifact = $overrideTier === null && !$isBenchmark
            && $this->_marketArtifactApplied($inputs);
        // An unusually large 1Y market return that decides the tier: used, but flagged.
        $marketUnusual = $overrideTier === null && !$isBenchmark
            && $basis === TierDecision::BASIS_MARKET_MOMENTUM && self::marketUnusual($value);

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
            isExited:     $this->_isExited($inputs),
            candidates:   [
                'annualized_pct' => $inputs->isOwned ? $inputs->annualizedReturnPct : null,
                'raw_pct'        => $inputs->rawReturnPct,
                'market_1y_pct'  => $inputs->marketMomentumPct,
            ],
            explanation:  $explanation,
            isStale:      $isStale,
            isBenchmark:  $isBenchmark,
            isBorderline: $isBorderline,
            marketArtifact:     $marketArtifact,
            marketArtifactNote: $marketArtifact ? $this->_marketArtifactNote($inputs) : '',
            marketUnusual:      $marketUnusual,
            marketUnusualNote:  $marketUnusual ? $this->_marketUnusualNote($value) : ''
        );
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
            if ($this->_marketForOwned && $marketUsable)
            {
                return [
                    TierDecision::BASIS_MARKET_MOMENTUM,
                    $inputs->marketMomentumPct,
                    TierDecision::CONFIDENCE_HIGH,
                ];
            }
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
                $inputs->priorClosedWindows > 0
                    ? 'Annualized return ' . $pct . ' on your position ('
                        . ($inputs->priorClosedWindows + 1) . ' windows, capital-and-time-weighted).'
                    : 'Annualized return (CAGR) ' . $pct . ' on your position (held over a year).',
            TierDecision::BASIS_RAW_RETURN => $this->_rawExplanation($inputs, $pct),
            TierDecision::BASIS_MARKET_MOMENTUM => match (true) {
                $inputs->isOwned && $this->_marketForOwned => 'Market 1Y return ' . $pct
                    . ' (every symbol is tiered on its market return; your own return is shown separately).',
                $inputs->isOwned => 'Market 1Y return ' . $pct . ' (position too new for a return figure).',
                default          => 'Market 1Y return ' . $pct . '.' . $this->_exitedNote($inputs),
            },
            default => 'Unrated: no return or market data available.',
        };
    }

    /**
     * For an exited position tiered on the market, surface the realized result so it is clear the
     * tier deliberately ignores it. Empty for watchlist-only symbols or when no return is known.
     */
    private function _exitedNote(TierInputs $inputs): string
    {
        if (!$this->_isExited($inputs))
        {
            return '';
        }
        $realized = $inputs->annualizedReturnPct !== null
            ? $this->_fmt($inputs->annualizedReturnPct) . '/y'
            : $this->_fmt($inputs->rawReturnPct);

        return ' Your realized return was ' . $realized
            . '; exited positions are tiered on the market, not your past result.';
    }

    private function _isExited(TierInputs $inputs): bool
    {
        return !$inputs->isOwned && $inputs->ownedEver
            && ($inputs->annualizedReturnPct !== null || $inputs->rawReturnPct !== null);
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
        $why = $inputs->marketMomentumPct === null
            ? 'market 1Y is unavailable'
            : 'market 1Y of ' . $this->_fmt($inputs->marketMomentumPct) . ' is treated as a data'
                . ' artifact because ' . $this->_marketRejectionReason($inputs->marketMomentumPct);

        return 'Raw return ' . $pct . ' on your position (held under 3 months; '
            . $why . '; not annualized).';
    }

    /**
     * Why a present-but-untrusted 1Y market return was rejected (below the trusted floor). Returns null
     * when the value is missing or usable.
     */
    private function _marketRejectionReason(?float $market): ?string
    {
        if ($market === null || $this->_marketUsable($market))
        {
            return null;
        }

        return 'it is below the trusted floor of ' . $this->_fmt(self::MARKET_1Y_MIN_PCT);
    }

    /**
     * Whether the 1Y market proxy was rejected as an artifact on a symbol where it would otherwise
     * have decided the tier: a new owned position (no settled annualized figure, held under 3
     * months) or a watchlist/exited symbol. In those cases the tier falls back to the raw return, so
     * the clamp actually changed the outcome and is worth flagging as an irregularity.
     */
    private function _marketArtifactApplied(TierInputs $inputs): bool
    {
        if ($inputs->marketMomentumPct === null || $this->_marketUsable($inputs->marketMomentumPct))
        {
            return false;
        }

        return $inputs->isOwned
            ? $this->_marketForOwned
                || ($inputs->annualizedReturnPct === null && $inputs->holdingDays < self::MATURE_HOLDING_DAYS)
            : true;
    }

    private function _marketArtifactNote(TierInputs $inputs): string
    {
        $reason = $this->_marketRejectionReason($inputs->marketMomentumPct);

        return 'Irregularity: the 1Y market return of ' . $this->_fmt($inputs->marketMomentumPct)
            . ' is not trusted because ' . $reason
            . ' (usually a missed split); it was rejected as a data artifact and the tier falls back to '
            . ($inputs->isOwned ? "this position's own return" : 'the realized return') . ' instead.';
    }

    private function _marketUnusualNote(?float $market): string
    {
        return 'Unusual move: the 1Y market return of ' . $this->_fmt($market) . ' is above '
            . $this->_fmt(self::MARKET_1Y_UNUSUAL_PCT) . '. It decides the tier; check that the price history'
            . ' shows a real rally and not a data error (e.g. a missed reverse split).';
    }

    /**
     * Both candidate tier numbers of a symbol, whichever one decides its tier today:
     * - market: the 1Y market return, null when missing or below the trusted floor;
     * - own:    your overall return as it would decide an owned symbol's tier (annualized once held a
     *           year, raw from 3 months), null when never held or held under 3 months. For an exited
     *           symbol this is the realized lifetime return.
     * The daily tier history stores both so the settled tier compares like with like.
     *
     * @return array{market: ?float, own: ?float}
     */
    public function candidateValues(TierInputs $inputs): array
    {
        $own = $inputs->annualizedReturnPct;
        if ($own === null && $inputs->holdingDays >= self::MATURE_HOLDING_DAYS) {
            $own = $inputs->rawReturnPct;
        }

        return [
            'market' => $this->_marketUsable($inputs->marketMomentumPct) ? $inputs->marketMomentumPct : null,
            'own'    => $own,
        ];
    }

    private function _marketUsable(?float $market): bool
    {
        return self::marketUsable($market);
    }

    /**
     * Whether a trailing market return can be trusted: present and not below the floor (below it, a data
     * artifact). Figures above the unusual-move line are usable; see marketUnusual().
     */
    public static function marketUsable(?float $market): bool
    {
        return $market !== null && $market >= self::MARKET_1Y_MIN_PCT;
    }

    /**
     * Whether a usable trailing market return is above the unusual-move line (used, but flagged).
     */
    public static function marketUnusual(?float $market): bool
    {
        return $market !== null && $market > self::MARKET_1Y_UNUSUAL_PCT;
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
