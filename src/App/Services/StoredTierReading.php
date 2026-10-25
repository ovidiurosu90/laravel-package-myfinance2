<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Models\SymbolTierOverride;
use ovidiuro\myfinance2\App\Models\SymbolTierState;

/**
 * A symbol's tier reading as of the last hourly cron run (symbol_tier_states, plus a manual tier override), for an
 * email sent outside the dashboard (the price alert), so it names the tier and chip as the peak-proximity alerts do
 * without a categorization run. Returned in the shape CategorizationService gives those alerts: 'effective_tier',
 * 'range_bound', 'take_profit', 'tier_signal_chip'. The quadrant action and the superstar need the
 * dashboard, so they are left out.
 */
class StoredTierReading
{
    /**
     * The reading, or null when the symbol has no stored tier (never categorized for this user, or Unrated).
     *
     * @param int    $userId
     * @param string $symbol
     *
     * @return array|null
     */
    public function forSymbol(int $userId, string $symbol): ?array
    {
        $state = SymbolTierState::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('symbol', $symbol)
            ->first();
        if ($state === null || $state->tier === null) {
            return null;
        }

        $override = SymbolTierOverride::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('symbol', $symbol)
            ->value('tier_override');

        return self::fromState($state->tier, $state->settled_tier, $override, [
            'range_bound' => $state->range_bound,
            'take_profit' => $state->take_profit,
            'tier_signal' => $state->tier_signal,
        ]);
    }

    /**
     * The reading from the stored fields: the effective tier is the override, else the settled tier, else today's
     * (CategorizationTierHistory::effectiveTier()).
     *
     * @param string      $todayTier
     * @param string|null $settledTier
     * @param string|null $overrideTier
     * @param array       $readings    'range_bound', 'take_profit', 'tier_signal' (the stored chip)
     *
     * @return array
     */
    public static function fromState(
        string $todayTier,
        ?string $settledTier,
        ?string $overrideTier,
        array $readings
    ): array
    {
        return [
            'effective_tier'   => $overrideTier ?? $settledTier ?? $todayTier,
            'range_bound'      => $readings['range_bound'] ?: null,
            'take_profit'      => $readings['take_profit'] ?: null,
            'tier_signal_chip' => $readings['tier_signal'] ?: null,
        ];
    }
}
