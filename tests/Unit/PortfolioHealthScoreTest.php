<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\PortfolioHealthScore;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Locks down the portfolio health aggregation: how owned positions roll up into the
 * platinum+gold / silver / bronze+rust buckets, the market-value and cost percentages,
 * the FX conversion, the exclusion of unrated symbols, and the healthy / caution /
 * warning signal thresholds. compute() depends only on a static colour map, so it runs
 * without a database.
 */
class PortfolioHealthScoreTest extends TestCase
{
    private PortfolioHealthScore $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PortfolioHealthScore();
    }

    /**
     * One position contributing $mvalue / $cost in $currency. The currency is read off
     * accountModel->currency->iso_code, exactly as the real position arrays expose it.
     */
    private function _position(float $mvalue, float $cost, string $currency = 'EUR'): array
    {
        return [
            'accountModel' => (object) ['currency' => (object) ['iso_code' => $currency]],
            'market_value_in_account_currency' => $mvalue,
            'cost2_in_account_currency'        => $cost,
        ];
    }

    public function testEmptyPortfolioIsHealthyWithNoPositionsMessage(): void
    {
        $result = $this->service->compute([], []);

        $this->assertSame(0.0, $result['total_mvalue_eur']);
        $this->assertSame(0.0, $result['total_cost_eur']);
        $this->assertSame('healthy', $result['signal']);
        $this->assertSame('No owned positions to evaluate.', $result['signal_message']);
    }

    public function testBucketsAndPercentages(): void
    {
        $tiers = [
            'AAA' => TierCalculationService::PLATINUM,
            'BBB' => TierCalculationService::SILVER,
            'CCC' => TierCalculationService::BRONZE,
        ];
        $positions = [
            'AAA' => [$this->_position(7000.0, 5000.0)],
            'BBB' => [$this->_position(2000.0, 2000.0)],
            'CCC' => [$this->_position(1000.0, 1500.0)],
        ];

        $result = $this->service->compute($tiers, $positions);

        // Market value rolls up to 10,000; cost to 8,500.
        $this->assertEqualsWithDelta(10000.0, $result['total_mvalue_eur'], 0.01);
        $this->assertEqualsWithDelta(8500.0, $result['total_cost_eur'], 0.01);

        // Market-value mix: 70% strong core, 20% silver, 10% weak.
        $this->assertEqualsWithDelta(70.0, $result['platinum_gold_pct'], 0.05);
        $this->assertEqualsWithDelta(20.0, $result['silver_pct'], 0.05);
        $this->assertEqualsWithDelta(10.0, $result['bronze_rust_pct'], 0.05);

        // Cost side is tracked independently of market value.
        $this->assertEqualsWithDelta(5000.0, $result['platinum_gold_cost_eur'], 0.01);
        $this->assertEqualsWithDelta(58.8, $result['platinum_gold_cost_pct'], 0.1); // 5000 / 8500

        // Strong core >= 60% and weak < 15% => healthy.
        $this->assertSame('healthy', $result['signal']);
    }

    public function testPlatinumAndGoldShareTheStrongCoreBucket(): void
    {
        $tiers = [
            'AAA' => TierCalculationService::PLATINUM,
            'BBB' => TierCalculationService::GOLD,
        ];
        $positions = [
            'AAA' => [$this->_position(6000.0, 6000.0)],
            'BBB' => [$this->_position(4000.0, 4000.0)],
        ];

        $result = $this->service->compute($tiers, $positions);

        $this->assertEqualsWithDelta(10000.0, $result['platinum_gold_mvalue_eur'], 0.01);
        $this->assertEqualsWithDelta(100.0, $result['platinum_gold_pct'], 0.05);
    }

    public function testUnratedSymbolsAreExcludedFromTotals(): void
    {
        $tiers = [
            'AAA' => TierCalculationService::PLATINUM,
            'XXX' => null, // unrated: no usable return data
        ];
        $positions = [
            'AAA' => [$this->_position(10000.0, 10000.0)],
            'XXX' => [$this->_position(5000.0, 5000.0)],
        ];

        $result = $this->service->compute($tiers, $positions);

        // The unrated 5,000 must not appear in any total or bucket.
        $this->assertEqualsWithDelta(10000.0, $result['total_mvalue_eur'], 0.01);
        $this->assertEqualsWithDelta(100.0, $result['platinum_gold_pct'], 0.05);
    }

    public function testPositionsWithoutOpenHoldingsAreSkipped(): void
    {
        $tiers     = ['AAA' => TierCalculationService::PLATINUM, 'BBB' => TierCalculationService::SILVER];
        $positions = ['AAA' => [$this->_position(8000.0, 8000.0)]]; // BBB has no positions

        $result = $this->service->compute($tiers, $positions);

        $this->assertEqualsWithDelta(8000.0, $result['total_mvalue_eur'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['silver_pct'], 0.05);
    }

    public function testForeignCurrencyIsConvertedToEur(): void
    {
        $tiers     = ['AAA' => TierCalculationService::PLATINUM];
        $positions = ['AAA' => [$this->_position(1000.0, 1000.0, 'USD')]];

        // 1 USD = 0.9 EUR for this test.
        $result = $this->service->compute($tiers, $positions, ['EUR' => 1.0, 'USD' => 0.9]);

        $this->assertEqualsWithDelta(900.0, $result['total_mvalue_eur'], 0.01);
        $this->assertEqualsWithDelta(900.0, $result['total_cost_eur'], 0.01);
    }

    public function testWarningWhenWeakTiersAtOrAboveFifteenPercent(): void
    {
        $tiers = [
            'AAA' => TierCalculationService::PLATINUM,
            'CCC' => TierCalculationService::RUST,
        ];
        $positions = [
            'AAA' => [$this->_position(8000.0, 8000.0)],
            'CCC' => [$this->_position(2000.0, 2000.0)], // 20% bronze+rust
        ];

        $result = $this->service->compute($tiers, $positions);

        $this->assertEqualsWithDelta(20.0, $result['bronze_rust_pct'], 0.05);
        $this->assertSame('warning', $result['signal']);
        $this->assertSame('Portfolio is weighed down.', $result['signal_message']);
    }

    public function testCautionWhenStrongCoreBelowSixtyPercent(): void
    {
        $tiers = [
            'AAA' => TierCalculationService::PLATINUM,
            'BBB' => TierCalculationService::SILVER,
        ];
        $positions = [
            'AAA' => [$this->_position(5000.0, 5000.0)], // 50% strong core
            'BBB' => [$this->_position(5000.0, 5000.0)], // 50% silver, 0% weak
        ];

        $result = $this->service->compute($tiers, $positions);

        $this->assertEqualsWithDelta(50.0, $result['platinum_gold_pct'], 0.05);
        $this->assertEqualsWithDelta(0.0, $result['bronze_rust_pct'], 0.05);
        $this->assertSame('caution', $result['signal']);
        $this->assertSame('Strong core below target. Add to best positions.', $result['signal_message']);
    }
}
