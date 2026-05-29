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
}
