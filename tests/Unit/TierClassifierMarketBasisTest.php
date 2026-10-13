<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\App\Services\TierClassifier;
use ovidiuro\myfinance2\App\Services\TierDecision;
use ovidiuro\myfinance2\App\Services\TierInputs;
use PHPUnit\Framework\TestCase;

/**
 * The default tier basis (tier_basis = 'market'): owned symbols are tiered on their trailing 1Y market
 * return like every other symbol, and fall back to their own return only without a trustworthy market
 * figure. The legacy own-return rules are covered by TierClassifierTest.
 */
class TierClassifierMarketBasisTest extends TestCase
{
    private function _classify(array $perf, ?array $drawdown, string $symbol = 'TEST'): TierDecision
    {
        return (new TierClassifier(new TierCalculationService()))->classify(
            TierInputs::fromData($symbol, $perf, $drawdown),
            null
        );
    }

    private function _ownedLongHeld(float $annualized): array
    {
        return [
            'has_data'                   => true,
            'annualized_percentage_gain' => $annualized,
            'percentage_gain'            => 50.0,
            'total_days'                 => 1500,
            'windows'                    => [
                ['is_open' => false, 'invested_eur' => 1000.0, 'total_gain_eur' => 300.0, 'duration_days' => 1000],
                ['is_open' => true, 'invested_eur' => 1000.0, 'total_gain_eur' => 200.0, 'duration_days' => 500],
            ],
        ];
    }

    public function test_owned_symbol_is_tiered_on_the_market_return(): void
    {
        // Your lifetime return is Silver (6.8 %/y) but the stock is up 22 % over the last year.
        $decision = $this->_classify($this->_ownedLongHeld(6.8), ['momenta' => ['1y' => 22.0]]);

        $this->assertTrue($decision->isOwned);
        $this->assertSame(TierDecision::BASIS_MARKET_MOMENTUM, $decision->basis);
        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertSame(TierDecision::CONFIDENCE_HIGH, $decision->confidence);
        $this->assertStringContainsString('your own return is shown separately', $decision->explanation);
        // Your own return stays available as a candidate for display.
        $this->assertSame(6.8, $decision->candidates['annualized_pct']);
    }

    public function test_owned_symbol_without_market_data_falls_back_to_own_return(): void
    {
        $decision = $this->_classify($this->_ownedLongHeld(6.8), null);

        $this->assertSame(TierDecision::BASIS_ANNUALIZED_RETURN, $decision->basis);
        $this->assertSame(TierCalculationService::SILVER, $decision->tier);
        $this->assertFalse($decision->marketArtifact);
    }

    public function test_untrusted_market_return_on_owned_symbol_is_flagged_and_falls_back(): void
    {
        // A collapse below the floor (-90 %, typically a missed split): rejected, the own return decides,
        // and it is flagged.
        $decision = $this->_classify($this->_ownedLongHeld(6.8), ['momenta' => ['1y' => -90.0]]);

        $this->assertSame(TierDecision::BASIS_ANNUALIZED_RETURN, $decision->basis);
        $this->assertTrue($decision->marketArtifact);
        $this->assertFalse($decision->marketUnusual);
        $this->assertStringContainsString('below the trusted floor', $decision->marketArtifactNote);
    }

    public function test_unusual_market_rally_decides_the_tier_and_is_flagged(): void
    {
        // A real rally of +992 %: used for the tier (Platinum), not rejected, but flagged.
        $decision = $this->_classify($this->_ownedLongHeld(6.8), ['momenta' => ['1y' => 992.0]]);

        $this->assertSame(TierDecision::BASIS_MARKET_MOMENTUM, $decision->basis);
        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertFalse($decision->marketArtifact);
        $this->assertTrue($decision->marketUnusual);
        $this->assertStringStartsWith('Unusual move:', $decision->marketUnusualNote);
        $this->assertTrue($decision->toArray()['market_unusual']);
    }

    public function test_watchlist_rally_is_tiered_and_stored_as_the_market_value(): void
    {
        // Never owned, +282 % over 1Y: tiered on it (no longer Unrated) and kept for the stored history.
        $decision = $this->_classify(['has_data' => false], ['momenta' => ['1y' => 282.0]]);

        $this->assertSame(TierCalculationService::PLATINUM, $decision->tier);
        $this->assertTrue($decision->marketUnusual);
        $this->assertSame(
            282.0,
            (new TierClassifier(new TierCalculationService()))->candidateValues(
                TierInputs::fromData('TEST', ['has_data' => false], ['momenta' => ['1y' => 282.0]])
            )['market']
        );
    }

    public function test_ordinary_market_return_is_not_flagged(): void
    {
        $decision = $this->_classify($this->_ownedLongHeld(6.8), ['momenta' => ['1y' => 150.0]]);

        $this->assertFalse($decision->marketUnusual);
        $this->assertSame('', $decision->marketUnusualNote);
    }

    public function test_benchmark_stays_pinned_to_gold(): void
    {
        $decision = $this->_classify(
            $this->_ownedLongHeld(6.8), ['momenta' => ['1y' => 25.0]], TierCalculationService::BENCHMARK_SYMBOL
        );

        $this->assertSame(TierCalculationService::GOLD, $decision->tier);
    }

    public function test_candidate_values_expose_both_measures(): void
    {
        $classifier = new TierClassifier(new TierCalculationService());
        $inputs     = TierInputs::fromData('TEST', $this->_ownedLongHeld(6.8), ['momenta' => ['1y' => 22.0]]);

        $this->assertSame(['market' => 22.0, 'own' => 6.8], $classifier->candidateValues($inputs));
    }
}
