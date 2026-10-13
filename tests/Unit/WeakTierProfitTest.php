<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\WeakTierProfit;
use PHPUnit\Framework\TestCase;

/**
 * Weak tier in profit: a held, not range-bound position in profit on a Rust or Bronze tier in use reads TAKE PROFIT.
 */
class WeakTierProfitTest extends TestCase
{
    private WeakTierProfit $signal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signal = new WeakTierProfit();
    }

    /**
     * A held symbol: settled Rust, not the benchmark, not range-bound.
     */
    private function cat(array $overrides = []): array
    {
        return array_replace([
            'effective_tier' => 'RUST',
            'settled_tier'   => 'RUST',
            'has_override'   => false,
            'is_benchmark'   => false,
            'range_bound'    => null,
        ], $overrides);
    }

    public function testRustInProfitReadsTakeProfit(): void
    {
        $reading = $this->signal->detect($this->cat(), 71.44, 1234.567);

        $this->assertNotNull($reading);
        $this->assertSame(WeakTierProfit::ACTION, $reading['action']);
        $this->assertSame(WeakTierProfit::LABEL, $reading['label']);
        $this->assertSame('RUST', $reading['tier']);
        $this->assertSame(71.44, $reading['gain_pct']);
        $this->assertSame(1234.57, $reading['gain_eur']);
        $this->assertSame('settled over the last 12 months', $reading['tier_source']);
        $this->assertStringContainsString('Rust', $reading['title']);
        $this->assertStringContainsString('TAKE PROFIT', $reading['title']);
        $this->assertStringContainsString("open position is up +\u{00A0}71.4\u{00A0}%. A weak", $reading['title']);
        $this->assertSame(1, substr_count($reading['title'], 'open position'));
    }

    public function testDeterioratingTierIsNotCalledSettled(): void
    {
        $reading = $this->signal->detect($this->cat(['tier_signal' => ['status' => 'deteriorating']]), 3.8);

        $this->assertSame('following today after a lasting drop', $reading['tier_source']);
        $this->assertStringContainsString('(following today after a lasting drop, a weak trend)', $reading['title']);
    }

    public function testBronzeCountsAndAnyProfitQualifies(): void
    {
        $this->assertNotNull($this->signal->detect($this->cat(['effective_tier' => 'BRONZE']), 0.5));
    }

    public function testSilverAndBetterDoNotQualify(): void
    {
        foreach (['SILVER', 'GOLD', 'PLATINUM'] as $tier) {
            $this->assertNull($this->signal->detect($this->cat(['effective_tier' => $tier]), 40.0), $tier);
        }
    }

    public function testLossOrFlatOrNotHeldDoesNotQualify(): void
    {
        $this->assertNull($this->signal->detect($this->cat(), -3.0));
        $this->assertNull($this->signal->detect($this->cat(), 0.0));
        $this->assertNull($this->signal->detect($this->cat(), null));
    }

    public function testRangeBoundAndBenchmarkAreLeftOut(): void
    {
        $this->assertNull($this->signal->detect($this->cat(['range_bound' => ['action' => 'TRIM']]), 20.0));
        $this->assertNull($this->signal->detect($this->cat(['is_benchmark' => true]), 20.0));
    }

    public function testMinimumGainIsConfigurable(): void
    {
        $signal = new WeakTierProfit(['RUST'], 5.0);

        $this->assertNull($signal->detect($this->cat(), 5.0));
        $this->assertNotNull($signal->detect($this->cat(), 5.1));
        $this->assertNull($signal->detect($this->cat(['effective_tier' => 'BRONZE']), 20.0));
    }

    public function testTierSourceNamesOverrideOrTodaysReading(): void
    {
        $override = $this->signal->detect($this->cat(['has_override' => true]), 10.0);
        $today    = $this->signal->detect($this->cat(['settled_tier' => null]), 10.0);

        $this->assertSame('your manual tier', $override['tier_source']);
        $this->assertSame("today's reading", $today['tier_source']);
    }

    public function testOpenGainSumsTheOpenWindowsOnly(): void
    {
        $perf = ['windows' => [
            ['is_open' => false, 'remaining_cost_eur' => 0.0, 'unrealized_gain_eur' => 0.0],
            ['is_open' => true, 'remaining_cost_eur' => 1000.0, 'unrealized_gain_eur' => 250.0],
            ['is_open' => true, 'remaining_cost_eur' => 1000.0, 'unrealized_gain_eur' => -50.0],
        ]];

        [$pct, $eur] = WeakTierProfit::openGain($perf);

        $this->assertEqualsWithDelta(10.0, $pct, 1e-9);
        $this->assertEqualsWithDelta(200.0, $eur, 1e-9);
        $this->assertSame([null, null], WeakTierProfit::openGain(['windows' => []]));
    }
}
