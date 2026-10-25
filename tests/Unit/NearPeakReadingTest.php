<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\NearPeakReading;
use PHPUnit\Framework\TestCase;

/**
 * Near peak: a held position within a window's threshold of its peak, the near-peak alert's rule and its chip. The
 * quadrant's action shows only when the alert emails (a meaningful window on an exit-worthy tier).
 */
class NearPeakReadingTest extends TestCase
{
    private NearPeakReading $reading;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reading = new NearPeakReading();
    }

    /**
     * A held Bronze symbol 3 % under its 6M peak (threshold 5) and 20 % under its 2Y peak (threshold 10).
     */
    private function cat(array $overrides = []): array
    {
        return array_replace([
            'is_owned'       => true,
            'is_benchmark'   => false,
            'range_bound'    => null,
            'take_profit'    => null,
            'effective_tier' => 'BRONZE',
            'action'         => 'REDUCE',
            'exit_zones'     => [
                '3m' => ['proximity_pct' => -2.5],
                '6m' => ['proximity_pct' => -3.0],
                '1y' => ['proximity_pct' => -3.0],
                '2y' => ['proximity_pct' => -20.0],
            ],
        ], $overrides);
    }

    public function testWeakTierNearALongPeakShowsTheQuadrantAction(): void
    {
        $chip = $this->reading->detect($this->cat());

        $this->assertNotNull($chip);
        $this->assertSame(NearPeakReading::LABEL, $chip['label']);
        $this->assertSame('REDUCE', $chip['action']);
        $this->assertTrue($chip['actionable']);
        $this->assertSame(['6m', '1y'], $chip['windows']);
        $this->assertSame(-3.0, $chip['closest_pct']);
        $this->assertStringContainsString('near its 6M, 1Y peaks (closest -' . "\u{00A0}" . '3.0' . "\u{00A0}%)",
            $chip['title']);
        $this->assertStringContainsString('A weak Bronze tier near a high', $chip['title']);
        $this->assertStringContainsString('REDUCE: low returns and low volatility', $chip['title']);
    }

    public function testTheTierSignalActionWinsAsInTheEmail(): void
    {
        $chip = $this->reading->detect($this->cat([
            'action'           => 'EXIT',
            'tier_signal_chip' => ['action' => 'SELL', 'deferred_to' => null],
        ]));

        $this->assertSame('SELL', $chip['action']);
        $this->assertStringContainsString('SELL: as the tier signal reads it', $chip['title']);
    }

    public function testStrongTierIsContextWithoutAnAction(): void
    {
        $chip = $this->reading->detect($this->cat(['effective_tier' => 'GOLD', 'action' => 'ACCUMULATE']));

        $this->assertNotNull($chip);
        $this->assertNull($chip['action']);
        $this->assertFalse($chip['actionable']);
        $this->assertStringContainsString('Gold tier: context only', $chip['title']);
    }

    public function testThe3mWindowAloneIsContext(): void
    {
        $chip = $this->reading->detect($this->cat(['exit_zones' => ['3m' => ['proximity_pct' => -1.0]]]));

        $this->assertSame(['3m'], $chip['windows']);
        $this->assertNull($chip['action']);
        $this->assertStringContainsString('near its 3M peak (', $chip['title']);
    }

    public function testNotHeldBenchmarkRangeBoundTakeProfitOrFarFromPeakIsNull(): void
    {
        $this->assertNull($this->reading->detect($this->cat(['is_owned' => false])));
        $this->assertNull($this->reading->detect($this->cat(['is_benchmark' => true])));
        $this->assertNull($this->reading->detect($this->cat(['range_bound' => ['action' => 'TRIM']])));
        $this->assertNull($this->reading->detect($this->cat(['take_profit' => ['action' => 'TAKE PROFIT']])));
        $this->assertNull($this->reading->detect($this->cat(['exit_zones' => [
            '3m' => ['proximity_pct' => -2.1], '2y' => ['proximity_pct' => -10.1],
        ]])));
    }

    public function testThresholdIsInclusiveAndPerWindow(): void
    {
        $triggered = $this->reading->triggeredWindows([
            '3m' => ['proximity_pct' => -2.0],
            '6m' => ['proximity_pct' => -5.01],
            '2y' => ['proximity_pct' => 0.0],
        ]);

        $this->assertSame(['3m', '2y'], array_keys($triggered));
    }

    public function testExitFocusedOffMakesAnyWindowActionable(): void
    {
        $reading = new NearPeakReading(['3m' => 2.0], ['6m'], ['RUST'], false);

        $this->assertTrue($reading->actionable(['3m' => ['proximity_pct' => -1.0]], 'PLATINUM'));
        $this->assertFalse($reading->actionable([], 'RUST'));
    }
}
