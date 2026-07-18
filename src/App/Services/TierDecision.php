<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Immutable result of categorising one symbol into a tier.
 *
 * Beyond the effective tier it records *why* that tier was chosen: the basis
 * (which return measure decided it), the deciding value, a confidence level,
 * and a ready-to-render explanation. The FE renders these verbatim so the
 * categorisation decision is fully transparent without duplicating logic.
 */
final class TierDecision
{
    const BASIS_ANNUALIZED_RETURN = 'annualized_return';
    const BASIS_RAW_RETURN        = 'raw_return';
    const BASIS_MARKET_MOMENTUM   = 'market_momentum';
    const BASIS_NONE              = 'none';

    const CONFIDENCE_HIGH   = 'high';
    const CONFIDENCE_MEDIUM = 'medium';
    const CONFIDENCE_LOW    = 'low';

    public function __construct(
        public readonly ?string $tier,
        public readonly ?string $computedTier,
        public readonly ?string $overrideTier,
        public readonly bool    $hasOverride,
        public readonly ?string $overrideNote,
        public readonly string  $basis,
        public readonly ?float  $basisValue,
        public readonly string  $confidence,
        public readonly bool    $isOwned,
        public readonly array   $candidates,
        public readonly string  $explanation,
        // True when the symbol's headline lifetime return (which decides the tier) diverges
        // materially from the return on the holding you currently have, because it folds in one or
        // more earlier closed positions (a re-entry). The tier is unchanged; this only flags that
        // the big "overall" number on screen no longer reflects the current lot.
        public readonly bool    $isStale = false,
        // True for the benchmark itself (VUSA.AS): it does not compete in its own tournament. Its
        // tier is pinned to at least Gold and the view renders it as a labelled reference row.
        public readonly bool    $isBenchmark = false,
        // True when the deciding value sits within the borderline band of a tier line, so the
        // label is soft. Cosmetic only; never changes the tier.
        public readonly bool    $isBorderline = false,
        // True when the symbol's 1Y market return was the proxy we would otherwise have used to
        // tier it, but it fell outside the trusted band and was rejected as a data artifact, so the
        // tier fell back to the position's own (or realized) return. An irregularity worth flagging.
        public readonly bool    $marketArtifact = false,
        // Human-readable explanation of that irregularity (empty when $marketArtifact is false).
        public readonly string  $marketArtifactNote = ''
    )
    {
    }

    public function isUnrated(): bool
    {
        return $this->tier === null;
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence === self::CONFIDENCE_HIGH;
    }

    public static function basisLabel(string $basis): string
    {
        return match ($basis)
        {
            self::BASIS_ANNUALIZED_RETURN => 'Annualized return (CAGR)',
            self::BASIS_RAW_RETURN        => 'Raw return',
            self::BASIS_MARKET_MOMENTUM   => 'Market 1Y',
            default                       => 'Unrated',
        };
    }

    public function toArray(): array
    {
        return [
            'effective_tier' => $this->tier,
            'computed_tier'  => $this->computedTier,
            'override_tier'  => $this->overrideTier,
            'has_override'   => $this->hasOverride,
            'override_note'  => $this->overrideNote,
            'basis'          => $this->basis,
            'basis_label'    => self::basisLabel($this->basis),
            'basis_value'    => $this->basisValue,
            'confidence'     => $this->confidence,
            'is_owned'       => $this->isOwned,
            'candidates'     => $this->candidates,
            'explanation'    => $this->explanation,
            'is_stale'       => $this->isStale,
            'is_benchmark'   => $this->isBenchmark,
            'is_borderline'  => $this->isBorderline,
            'market_artifact'      => $this->marketArtifact,
            'market_artifact_note' => $this->marketArtifactNote,
        ];
    }
}
