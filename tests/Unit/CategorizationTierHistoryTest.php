<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\CategorizationTierHistory;
use ovidiuro\myfinance2\App\Services\SettledTier;
use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\App\Services\TierDecision;
use PHPUnit\Framework\TestCase;

/**
 * The effective tier (override ?? settled ?? today), the tooltip and the "today" chip that
 * CategorizationTierHistory adds to each categorization entry.
 */
class CategorizationTierHistoryTest extends TestCase
{
    private const DAY = '2026-06-01';

    private function _assertEffective(?string $expected, TierDecision $decision, SettledTier $settled): void
    {
        $this->assertSame($expected, CategorizationTierHistory::effectiveTier($decision, $settled));
    }

    private function _decision(
        ?string $computed,
        ?string $override = null,
        string $symbol = 'TEST',
        string $explanation = 'Basis explanation.'
    ): TierDecision
    {
        $isBenchmark = $symbol === TierCalculationService::BENCHMARK_SYMBOL;

        return new TierDecision(
            tier:         $override ?? $computed,
            computedTier: $computed,
            overrideTier: $override,
            hasOverride:  $override !== null,
            overrideNote: null,
            basis:        $computed === null ? TierDecision::BASIS_NONE : TierDecision::BASIS_MARKET_MOMENTUM,
            basisValue:   $computed === null ? null : 12.0,
            confidence:   TierDecision::CONFIDENCE_HIGH,
            isOwned:      true,
            candidates:   [],
            explanation:  $explanation,
            isBenchmark:  $isBenchmark
        );
    }

    /**
     * @param float[] $values one market value per trading day, oldest first, ending the day before DAY
     */
    private function _settled(TierDecision $decision, array $values, string $symbol = 'TEST'): SettledTier
    {
        $lookback = [];
        $date     = strtotime(self::DAY);
        foreach (array_reverse($values) as $value) {
            $date -= 86400;
            $lookback[date('Y-m-d', $date)] = ['v' => $value, 'm' => $value, 'w' => null, 'o' => true];
        }

        return (new SettledTierResolver())->resolve(
            $symbol, $decision->computedTier, $lookback, true, self::DAY, SettledTierResolver::MEASURE_MARKET
        );
    }

    public function test_settled_tier_becomes_the_effective_tier(): void
    {
        // Silver (7 %) on all 100 days, Gold today.
        $decision = $this->_decision(TierCalculationService::GOLD);
        $settled  = $this->_settled($decision, array_fill(0, 100, 7.0));

        $this->assertSame(TierCalculationService::SILVER, $settled->settledTier);
        $this->_assertEffective(TierCalculationService::SILVER, $decision, $settled);
    }

    public function test_override_wins_over_the_settled_tier(): void
    {
        $decision = $this->_decision(TierCalculationService::GOLD, TierCalculationService::BRONZE);
        $settled  = $this->_settled($decision, array_fill(0, 100, 7.0));

        $this->_assertEffective(TierCalculationService::BRONZE, $decision, $settled);
        $this->assertNull(CategorizationTierHistory::todayChip($decision, TierCalculationService::BRONZE));
    }

    public function test_insufficient_history_uses_todays_tier(): void
    {
        $decision = $this->_decision(TierCalculationService::GOLD);
        $settled  = $this->_settled($decision, array_fill(0, 30, 7.0));

        $this->assertSame(SettledTierResolver::STATUS_INSUFFICIENT, $settled->status);
        $this->_assertEffective(TierCalculationService::GOLD, $decision, $settled);
        $this->assertStringStartsWith(
            'Tier from today only; history too short (30 days).',
            CategorizationTierHistory::tierTooltip($decision, $settled)
        );
    }

    public function test_deteriorating_follows_todays_tier(): void
    {
        // Platinum (20 %) for 80 days, then Silver (7 %) for the last 25 days and today.
        $decision = $this->_decision(TierCalculationService::SILVER);
        $settled  = $this->_settled($decision, array_merge(array_fill(0, 80, 20.0), array_fill(0, 25, 7.0)));

        $this->assertSame(SettledTierResolver::STATUS_DETERIORATING, $settled->status);
        $this->_assertEffective(TierCalculationService::SILVER, $decision, $settled);
    }

    public function test_benchmark_keeps_the_classifier_tier(): void
    {
        $symbol   = TierCalculationService::BENCHMARK_SYMBOL;
        $decision = $this->_decision(TierCalculationService::GOLD, null, $symbol);
        $settled  = $this->_settled($decision, array_fill(0, 100, 20.0), $symbol);

        $this->_assertEffective(TierCalculationService::GOLD, $decision, $settled);
        $this->assertNull(CategorizationTierHistory::todayChip($decision, TierCalculationService::GOLD));
    }

    public function test_unrated_today_is_not_rated_from_the_history(): void
    {
        $decision = $this->_decision(null);
        $settled  = $this->_settled($decision, array_fill(0, 100, 12.0));

        $this->assertSame(TierCalculationService::GOLD, $settled->settledTier);
        $this->_assertEffective(null, $decision, $settled);
        $this->assertNull(CategorizationTierHistory::todayChip($decision, null));
    }

    public function test_today_chip_only_when_today_differs(): void
    {
        $decision = $this->_decision(TierCalculationService::GOLD);

        $chip = CategorizationTierHistory::todayChip($decision, TierCalculationService::SILVER);
        $this->assertSame(TierCalculationService::GOLD, $chip['tier']);
        $this->assertSame('Gold', $chip['label']);
        $this->assertStringStartsWith('Today: Gold', $chip['title']);

        $this->assertNull(CategorizationTierHistory::todayChip($decision, TierCalculationService::GOLD));
    }

    public function test_tooltip_puts_the_settled_explanation_before_the_basis(): void
    {
        $decision = $this->_decision(TierCalculationService::GOLD);
        $settled  = $this->_settled($decision, array_fill(0, 100, 7.0));
        $tooltip  = CategorizationTierHistory::tierTooltip($decision, $settled);

        $this->assertStringStartsWith('Settled Silver:', $tooltip);
        $this->assertStringEndsWith('Basis explanation.', $tooltip);

        $overridden = $this->_decision(TierCalculationService::GOLD, TierCalculationService::BRONZE, 'TEST', 'Why.');
        $this->assertSame('Why.', CategorizationTierHistory::tierTooltip($overridden, $settled));
    }
}
