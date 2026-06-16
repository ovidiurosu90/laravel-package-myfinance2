<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Models\SymbolTierOverride;
use ovidiuro\myfinance2\App\Services\TierClassifier;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\App\Services\TierDecision;
use ovidiuro\myfinance2\App\Services\TierInputs;
use PHPUnit\Framework\TestCase;

/**
 * Locks down the categorisation framework: which return measure decides the
 * tier, in which priority order, and with what confidence. See TierClassifier
 * for the documented rules these tests enforce.
 */
class TierClassifierTest extends TestCase
{
    private TierClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new TierClassifier(new TierCalculationService());
    }

    private function _classify(
        array $perf,
        ?array $drawdown,
        ?SymbolTierOverride $override = null,
        ?array $positionReturn = null
    ): TierDecision
    {
        return $this->classifier->classify(
            TierInputs::fromData('TEST', $perf, $drawdown, $positionReturn),
            $override
        );
    }

    private function _openWindow(float $invested, float $gain, int $days): array
    {
        return [
            'is_open'        => true,
            'invested_eur'   => $invested,
            'total_gain_eur' => $gain,
            'duration_days'  => $days,
        ];
    }

    public function test_owned_long_held_uses_annualized_return(): void
    {
        $decision = $this->_classify(
            [
                'has_data'                    => true,
                'annualized_percentage_gain'  => 20.0,
                'percentage_gain'             => 45.0,
                'total_days'                  => 800,
                'windows'                     => [$this->_openWindow(1000.0, 450.0, 800)],
            ],
            ['momenta' => ['1y' => 4.0]]
        );

        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertSame(TierDecision::BASIS_ANNUALIZED_RETURN, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_HIGH, $decision->confidence);
        $this->assertSame(20.0, $decision->basisValue);
    }

    public function test_owned_under_a_year_uses_overall_raw_return_not_annualized(): void
    {
        // Held ~10 months, +8% raw. Must NOT be extrapolated to a higher tier, and a
        // settled (>= 3 month) hold is medium confidence, i.e. not flagged.
        $decision = $this->_classify(
            [
                'has_data'                   => true,
                'annualized_percentage_gain' => null,
                'percentage_gain'            => 8.0,
                'total_days'                 => 300,
                'windows'                    => [$this->_openWindow(1000.0, 80.0, 300)],
            ],
            ['momenta' => ['1y' => 60.0]]
        );

        $this->assertSame(TierCalculationService::SILVER, $decision->tier);
        $this->assertSame(TierDecision::BASIS_RAW_RETURN, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_MEDIUM, $decision->confidence);
        $this->assertEqualsWithDelta(8.0, $decision->basisValue, 0.0001);
    }

    public function test_owned_under_three_months_uses_market_when_usable(): void
    {
        // Held 20 days: too new for its own return, so the symbol's market 1Y decides.
        $decision = $this->_classify(
            [
                'has_data'                   => true,
                'annualized_percentage_gain' => null,
                'percentage_gain'            => 17.55,
                'total_days'                 => 20,
                'windows'                    => [$this->_openWindow(1000.0, 175.5, 20)],
            ],
            ['momenta' => ['1y' => 25.0]]
        );

        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertSame(TierDecision::BASIS_MARKET_MOMENTUM, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_MEDIUM, $decision->confidence);
        $this->assertSame(25.0, $decision->basisValue);
    }

    public function test_owned_under_three_months_guards_implausible_market_and_uses_raw(): void
    {
        // Market 1Y is a data artifact (+3,773%); fall back to the real raw return.
        $decision = $this->_classify(
            [
                'has_data'                   => true,
                'annualized_percentage_gain' => null,
                'percentage_gain'            => 13.25,
                'total_days'                 => 10,
                'windows'                    => [$this->_openWindow(1000.0, 132.5, 10)],
            ],
            ['momenta' => ['1y' => 3773.0]]
        );

        $this->assertSame(TierCalculationService::GOLD, $decision->tier);
        $this->assertSame(TierDecision::BASIS_RAW_RETURN, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_LOW, $decision->confidence);
        $this->assertEqualsWithDelta(13.25, $decision->basisValue, 0.0001);
    }

    public function test_owned_under_three_months_with_no_market_uses_raw_low_confidence(): void
    {
        $decision = $this->_classify(
            [
                'has_data'                   => true,
                'annualized_percentage_gain' => null,
                'percentage_gain'            => 8.0,
                'total_days'                 => 45,
                'windows'                    => [$this->_openWindow(1000.0, 80.0, 45)],
            ],
            null
        );

        $this->assertSame(TierCalculationService::SILVER, $decision->tier);
        $this->assertSame(TierDecision::BASIS_RAW_RETURN, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_LOW, $decision->confidence);
    }

    public function test_owned_negative_raw_return_is_rust(): void
    {
        $decision = $this->_classify(
            [
                'has_data'                   => true,
                'annualized_percentage_gain' => null,
                'percentage_gain'            => -5.0,
                'total_days'                 => 120,
                'windows'                    => [$this->_openWindow(1000.0, -50.0, 120)],
            ],
            null
        );

        $this->assertSame(TierCalculationService::RUST, $decision->tier);
        $this->assertSame(TierDecision::BASIS_RAW_RETURN, $decision->basis);
    }

    public function test_owned_without_position_return_falls_back_to_market_momentum(): void
    {
        // Open window exists (owned) but no computable position return.
        $decision = $this->_classify(
            [
                'has_data'                   => true,
                'annualized_percentage_gain' => null,
                'percentage_gain'            => null,
                'total_days'                 => 10,
                'windows'                    => [$this->_openWindow(0.0, 0.0, 10)],
            ],
            ['momenta' => ['1y' => 18.0]]
        );

        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertSame(TierDecision::BASIS_MARKET_MOMENTUM, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_MEDIUM, $decision->confidence);
    }

    public function test_watchlist_with_implausible_market_and_no_other_data_is_unrated(): void
    {
        $decision = $this->_classify(
            ['has_data' => false],
            ['momenta' => ['1y' => 3773.0]]
        );

        $this->assertNull($decision->tier);
        $this->assertSame(TierDecision::BASIS_NONE, $decision->basis);
    }

    public function test_watchlist_only_uses_market_momentum_at_high_confidence(): void
    {
        $decision = $this->_classify(
            ['has_data' => false],
            ['momenta' => ['1y' => 7.0]]
        );

        $this->assertSame(TierCalculationService::SILVER, $decision->tier);
        $this->assertSame(TierDecision::BASIS_MARKET_MOMENTUM, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_HIGH, $decision->confidence);
    }

    public function test_no_data_is_unrated(): void
    {
        $decision = $this->_classify(['has_data' => false], null);

        $this->assertNull($decision->tier);
        $this->assertTrue($decision->isUnrated());
        $this->assertSame(TierDecision::BASIS_NONE, $decision->basis);
    }

    public function test_exited_without_market_data_uses_realized_raw_return(): void
    {
        $decision = $this->_classify(
            [
                'has_data'        => true,
                'percentage_gain' => 30.0,
                'windows'         => [['is_open' => false, 'invested_eur' => 1000.0, 'total_gain_eur' => 300.0, 'duration_days' => 400]],
            ],
            null
        );

        $this->assertFalse($decision->isOwned);
        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertSame(TierDecision::BASIS_RAW_RETURN, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_MEDIUM, $decision->confidence);
    }

    public function test_unlisted_long_held_uses_position_return_annualized(): void
    {
        // No performance-service return (unlisted/FMV). +4.25% over ~3.5 years => ~1.2% CAGR.
        $decision = $this->_classify(
            ['has_data' => false],
            null,
            null,
            ['raw_pct' => 4.25, 'days' => 1279]
        );

        $this->assertTrue($decision->isOwned);
        $this->assertSame(TierCalculationService::BRONZE, $decision->tier);
        $this->assertSame(TierDecision::BASIS_ANNUALIZED_RETURN, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_HIGH, $decision->confidence);
        $this->assertEqualsWithDelta(1.2, $decision->basisValue, 0.2);
    }

    public function test_unlisted_short_held_uses_position_raw_return(): void
    {
        $decision = $this->_classify(
            ['has_data' => false],
            null,
            null,
            ['raw_pct' => 8.0, 'days' => 100]
        );

        $this->assertTrue($decision->isOwned);
        $this->assertSame(TierCalculationService::SILVER, $decision->tier);
        $this->assertSame(TierDecision::BASIS_RAW_RETURN, $decision->basis);
        $this->assertSame(TierDecision::CONFIDENCE_MEDIUM, $decision->confidence);
        $this->assertSame(8.0, $decision->basisValue);
    }

    public function test_performance_return_wins_over_position_return(): void
    {
        // When the performance service has a return, the position-return fallback is ignored.
        $decision = $this->_classify(
            [
                'has_data'                   => true,
                'annualized_percentage_gain' => null,
                'percentage_gain'            => 8.0,
                'total_days'                 => 200,
                'windows'                    => [$this->_openWindow(1000.0, 80.0, 200)],
            ],
            null,
            null,
            ['raw_pct' => 50.0, 'days' => 200]
        );

        $this->assertSame(TierCalculationService::SILVER, $decision->tier);
        $this->assertSame(TierDecision::BASIS_RAW_RETURN, $decision->basis);
        $this->assertSame(8.0, $decision->basisValue);
    }

    public function test_no_return_and_no_position_data_is_unrated(): void
    {
        $decision = $this->_classify(['has_data' => false], null, null, ['raw_pct' => null, 'days' => 0]);

        $this->assertNull($decision->tier);
        $this->assertSame(TierDecision::BASIS_NONE, $decision->basis);
    }

    public function test_override_wins_and_reports_high_confidence(): void
    {
        $override = new SymbolTierOverride();
        $override->tier_override = TierCalculationService::PLATINUM;
        $override->note          = 'long-term conviction';

        $decision = $this->_classify(
            [
                'has_data'                   => true,
                'annualized_percentage_gain' => 3.0,
                'windows'                    => [$this->_openWindow(1000.0, 90.0, 800)],
            ],
            null,
            $override
        );

        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertSame(TierCalculationService::BRONZE, $decision->computedTier);
        $this->assertTrue($decision->hasOverride);
        $this->assertSame(TierDecision::CONFIDENCE_HIGH, $decision->confidence);
        // The free-text reason is quoted so its boundaries are unambiguous.
        $this->assertStringContainsString('Reason: "long-term conviction".', $decision->explanation);
        // The override explanation also surfaces the original, un-overridden assessment.
        $this->assertStringContainsString('Original assessment:', $decision->explanation);
        $this->assertStringContainsString('Annualized return (CAGR)', $decision->explanation);
    }

    public function test_override_applies_even_when_computed_is_unrated(): void
    {
        $override = new SymbolTierOverride();
        $override->tier_override = TierCalculationService::GOLD;

        $decision = $this->_classify(['has_data' => false], null, $override);

        $this->assertSame(TierCalculationService::GOLD, $decision->tier);
        $this->assertNull($decision->computedTier);
        $this->assertTrue($decision->hasOverride);
    }

    public function test_tier_threshold_boundaries(): void
    {
        $tiers = new TierCalculationService();

        $this->assertSame(TierCalculationService::PLATINUM, $tiers->getTier(15.01));
        $this->assertSame(TierCalculationService::GOLD, $tiers->getTier(15.0));
        $this->assertSame(TierCalculationService::GOLD, $tiers->getTier(10.01));
        $this->assertSame(TierCalculationService::SILVER, $tiers->getTier(10.0));
        $this->assertSame(TierCalculationService::SILVER, $tiers->getTier(5.01));
        $this->assertSame(TierCalculationService::BRONZE, $tiers->getTier(5.0));
        $this->assertSame(TierCalculationService::BRONZE, $tiers->getTier(0.0));
        $this->assertSame(TierCalculationService::RUST, $tiers->getTier(-0.01));
        $this->assertNull($tiers->getTier(null));
    }

    public function test_benchmark_with_soft_return_is_pinned_to_gold(): void
    {
        // VUSA.AS trailing at 9.8% would bucket as Silver, but the benchmark anchors the Gold
        // line and must never render below it: pin to Gold and flag it as the benchmark.
        $decision = $this->classifier->classify(
            TierInputs::fromData(
                TierCalculationService::BENCHMARK_SYMBOL,
                [
                    'has_data'                   => true,
                    'annualized_percentage_gain' => 9.8,
                    'percentage_gain'            => 26.0,
                    'total_days'                 => 980,
                    'windows'                    => [$this->_openWindow(1000.0, 260.0, 980)],
                ],
                ['momenta' => ['1y' => 9.0]]
            ),
            null
        );

        $this->assertSame(TierCalculationService::GOLD, $decision->tier);
        $this->assertTrue($decision->isBenchmark);
        $this->assertFalse($decision->isBorderline); // suppressed for the pinned benchmark
        $this->assertSame(9.8, $decision->basisValue); // real trailing CAGR preserved for context
    }

    public function test_benchmark_running_hot_can_still_show_platinum(): void
    {
        // The pin is a floor, not a clamp: a genuinely hot benchmark keeps its earned Platinum.
        $decision = $this->classifier->classify(
            TierInputs::fromData(
                TierCalculationService::BENCHMARK_SYMBOL,
                [
                    'has_data'                   => true,
                    'annualized_percentage_gain' => 18.0,
                    'percentage_gain'            => 40.0,
                    'total_days'                 => 900,
                    'windows'                    => [$this->_openWindow(1000.0, 400.0, 900)],
                ],
                ['momenta' => ['1y' => 17.0]]
            ),
            null
        );

        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertTrue($decision->isBenchmark);
    }

    public function test_previous_tier_drives_hysteresis_through_the_classifier(): void
    {
        // A non-benchmark symbol settled at Gold, now trailing 9.8%: inside the dead-band, so the
        // classifier keeps Gold rather than flipping to Silver.
        $perf = [
            'has_data'                   => true,
            'annualized_percentage_gain' => 9.8,
            'percentage_gain'            => 22.0,
            'total_days'                 => 900,
            'windows'                    => [$this->_openWindow(1000.0, 220.0, 900)],
        ];

        $sticky = $this->classifier->classify(
            TierInputs::fromData('TEST', $perf, ['momenta' => ['1y' => 9.0]]),
            null,
            TierCalculationService::GOLD
        );
        $this->assertSame(TierCalculationService::GOLD, $sticky->tier);

        // With no prior tier the same value buckets plainly as Silver.
        $fresh = $this->classifier->classify(
            TierInputs::fromData('TEST', $perf, ['momenta' => ['1y' => 9.0]]),
            null
        );
        $this->assertSame(TierCalculationService::SILVER, $fresh->tier);
    }
}
