<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\SymbolTierOverride;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

class TierCalculationService
{
    const PLATINUM = 'PLATINUM';
    const GOLD     = 'GOLD';
    const SILVER   = 'SILVER';
    const BRONZE   = 'BRONZE';
    const RUST     = 'RUST';

    const TIERS = [self::PLATINUM, self::GOLD, self::SILVER, self::BRONZE, self::RUST];

    // The benchmark the whole framework measures against (Vanguard S&P 500 UCITS ETF, EUR).
    // Its 10% Gold line is anchored to its LONG-RUN return, not its trailing realized one, so
    // the benchmark is always pinned to exactly Gold (see TierClassifier): it defines the reference
    // line and cannot out- or under-perform itself, whatever its noisy trailing return does.
    public const BENCHMARK_SYMBOL = 'VUSA.AS';

    // The Gold/Silver boundary. A fixed structural anchor (long-run S&P 500 EUR expectation),
    // deliberately NOT the benchmark's noisy trailing CAGR.
    public const GOLD_THRESHOLD_PCT = 10.0;

    // Tier boundaries in ascending order: Rust|Bronze at 0, Bronze|Silver at 5,
    // Silver|Gold at 10, Gold|Platinum at 15.
    private const TIER_BOUNDARIES = [0.0, 5.0, self::GOLD_THRESHOLD_PCT, 15.0];

    // Band used only for the cosmetic "near a tier line" indicator; it flags labels that are soft
    // without changing the tier itself.
    public const BORDERLINE_BAND_PCT = 1.0;

    private const TIER_RANK = [
        self::RUST     => 0,
        self::BRONZE   => 1,
        self::SILVER   => 2,
        self::GOLD     => 3,
        self::PLATINUM => 4,
    ];

    public function getTier(?float $annualizedPct): ?string
    {
        if ($annualizedPct === null) {
            return null;
        }
        if ($annualizedPct > 15.0)  return self::PLATINUM;
        if ($annualizedPct > 10.0)  return self::GOLD;
        if ($annualizedPct > 5.0)   return self::SILVER;
        if ($annualizedPct >= 0.0)  return self::BRONZE;
        return self::RUST;
    }

    /**
     * Whether a value sits within $band percentage points of any tier boundary, i.e. its tier
     * label is "soft" and a small move could reclassify it. Drives a cosmetic indicator only.
     */
    public function isBorderline(?float $value, float $band = self::BORDERLINE_BAND_PCT): bool
    {
        if ($value === null) {
            return false;
        }
        foreach (self::TIER_BOUNDARIES as $boundary) {
            if (abs($value - $boundary) <= $band) {
                return true;
            }
        }
        return false;
    }

    /**
     * Load all overrides for a user as a keyed collection (symbol => override).
     * Use this to batch-load overrides instead of N per-symbol queries.
     */
    public function loadOverrides(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return SymbolTierOverride::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('symbol');
    }

    public static function tierLabel(?string $tier): ?string
    {
        $labels = [
            self::PLATINUM => 'Platinum',
            self::GOLD     => 'Gold',
            self::SILVER   => 'Silver',
            self::BRONZE   => 'Bronze',
            self::RUST     => 'Rust',
        ];
        return $tier !== null ? ($labels[$tier] ?? $tier) : null;
    }

    public static function tierInitial(?string $tier): ?string
    {
        $label = self::tierLabel($tier);
        return $label !== null ? mb_substr($label, 0, 1) : null;
    }

    public static function tierBadgeClass(?string $tier): string
    {
        return match ($tier) {
            self::PLATINUM => 'bg-info text-dark',
            self::GOLD     => 'bg-warning text-dark',
            self::SILVER   => 'bg-secondary',
            self::BRONZE   => 'bg-orange text-white',
            self::RUST     => 'bg-danger',
            default        => 'bg-light text-dark',
        };
    }

    /**
     * Rank of a tier, Rust 0 up to Platinum 4. Null for an unknown or missing tier.
     */
    public static function tierRank(?string $tier): ?int
    {
        return $tier !== null ? (self::TIER_RANK[$tier] ?? null) : null;
    }

    /**
     * Solid colour of a tier, matching tierBadgeClass() (Bootstrap info / warning / secondary /
     * danger, and the same orange the badges use for Bronze). Used where a CSS class cannot be:
     * charts and proportional bars.
     */
    public static function tierColor(?string $tier): string
    {
        return match ($tier) {
            self::PLATINUM => '#0dcaf0',
            self::GOLD     => '#ffc107',
            self::SILVER   => '#6c757d',
            self::BRONZE   => '#e67e22',
            self::RUST     => '#dc3545',
            default        => '#dee2e6',
        };
    }
}
