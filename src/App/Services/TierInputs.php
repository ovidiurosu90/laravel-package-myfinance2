<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Normalised return metrics for a single symbol, extracted once from the
 * performance and drawdown snapshots so the TierClassifier never has to dig
 * through raw window data. This is the single place where "what numbers does
 * this symbol have" is decided; the classifier only applies the priority rules.
 */
final class TierInputs
{
    private function __construct(
        public readonly string $symbol,
        public readonly bool   $isOwned,
        public readonly bool   $ownedEver,
        public readonly ?float $annualizedReturnPct,
        public readonly ?float $rawReturnPct,
        public readonly int    $holdingDays,
        public readonly ?float $marketMomentumPct
    )
    {
    }

    /**
     * @param array      $perf            SymbolPerformanceService entry (or ['has_data' => false])
     * @param array|null $drawdown        DrawdownService entry (momenta, relative_drawdown, ...)
     * @param array|null $positionReturn  Optional fallback ['raw_pct' => ?float, 'days' => int]
     *                                     derived from the open position (market value minus cost),
     *                                     used when the performance service has no usable return,
     *                                     e.g. an unlisted holding valued by a manual FMV.
     */
    public static function fromData(
        string $symbol,
        array $perf,
        ?array $drawdown,
        ?array $positionReturn = null
    ): self
    {
        $market = $drawdown['momentum_annualized_pct']
            ?? ($drawdown['momenta']['1y'] ?? null);
        $market = $market !== null ? (float) $market : null;

        $hasData = !empty($perf['has_data']);

        // Both return figures are the overall position return across every buy/sell
        // window (realized + unrealized + dividends), so the tier always reflects the
        // lifetime performance, not just the latest open lot. annualized is only emitted
        // by SymbolPerformanceService once total holding time reaches a year.
        $annualized = $hasData && isset($perf['annualized_percentage_gain'])
            && $perf['annualized_percentage_gain'] !== null
            ? (float) $perf['annualized_percentage_gain']
            : null;
        $raw = $hasData && isset($perf['percentage_gain']) && $perf['percentage_gain'] !== null
            ? (float) $perf['percentage_gain']
            : null;

        if ($annualized !== null || $raw !== null)
        {
            $totalDays = (int) ($perf['total_days'] ?? 0);
            $isOwned   = (bool) array_filter($perf['windows'] ?? [], fn ($w) => !empty($w['is_open']));

            return new self($symbol, $isOwned, true, $annualized, $raw, $totalDays, $market);
        }

        // No performance-service return. Fall back to the position-derived return when the
        // caller supplied one (unlisted/FMV holdings), annualizing once it is a year old so a
        // long-held position is not over-tiered by its cumulative return.
        if ($positionReturn !== null && ($positionReturn['raw_pct'] ?? null) !== null)
        {
            $posRaw  = (float) $positionReturn['raw_pct'];
            $posDays = (int) ($positionReturn['days'] ?? 0);
            $posAnn  = $posDays >= 365
                ? (pow(1.0 + $posRaw / 100.0, 365.0 / $posDays) - 1.0) * 100.0
                : null;

            return new self($symbol, true, true, $posAnn, $posRaw, $posDays, $market);
        }

        // Owned (an open window exists) but no return at all, or no data: let the classifier
        // fall back to market 1Y or mark it Unrated.
        $isOwned = $hasData && (bool) array_filter($perf['windows'] ?? [], fn ($w) => !empty($w['is_open']));

        return new self($symbol, $isOwned, $hasData, null, null, 0, $market);
    }
}
