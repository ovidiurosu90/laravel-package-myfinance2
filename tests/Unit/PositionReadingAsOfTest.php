<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\PositionReadingAsOf;
use PHPUnit\Framework\TestCase;

/**
 * The range-bound and weak-tier-in-profit readings of a past day, rebuilt from the stored row and the closes, in the
 * live precedence; only the readings that raise an alert count.
 */
class PositionReadingAsOfTest extends TestCase
{
    private PositionReadingAsOf $reading;

    /** A 52-week range from 60 to 100 (low at 60 % of the high). */
    private array $range = [100.0, 80.0, 60.0, 75.0, 90.0];

    protected function setUp(): void
    {
        parent::setUp();
        $this->reading = new PositionReadingAsOf();
    }

    /** A held day: 1Y market +2 %, your own return +15 % (Gold), holding period +8 %. */
    private function row(array $overrides = []): array
    {
        return array_replace(['m' => 2.0, 'w' => 15.0, 'h' => 8.0, 'o' => true], $overrides);
    }

    public function testRangeBoundNearTheHighIsATrimAndNearTheLowAnAdd(): void
    {
        $this->assertSame(['signal' => 'RANGE_HIGH', 'action' => 'TRIM', 'owned' => true],
            $this->reading->read($this->row(), 'RUST', 'RUST', $this->range, 95.0, true));
        $this->assertSame(['signal' => 'RANGE_LOW', 'action' => 'ADD', 'owned' => true],
            $this->reading->read($this->row(), 'RUST', 'RUST', $this->range, 62.0, true));
    }

    public function testNotHeldNearTheLowIsABuyAndMidRangeRaisesNothing(): void
    {
        $this->assertSame(['signal' => 'RANGE_BUY', 'action' => 'BUY', 'owned' => false],
            $this->reading->read($this->row(['o' => false]), 'RUST', 'RUST', $this->range, 62.0, true));
        // Mid-range: range-bound (HOLD), so not weak-tier-in-profit either, although Rust and in profit.
        $this->assertNull($this->reading->read($this->row(), 'RUST', 'RUST', $this->range, 80.0, true));
    }

    public function testAWeakTierInProfitThatIsNotRangeBoundIsATakeProfit(): void
    {
        // 1Y market -20 %: not range-bound; Rust, held, holding period +8 %.
        $this->assertSame(['signal' => 'TAKE_PROFIT', 'action' => 'TAKE PROFIT', 'owned' => true],
            $this->reading->read($this->row(['m' => -20.0]), 'RUST', 'RUST', $this->range, 80.0, true));
        $this->assertNull($this->reading->read($this->row(['m' => -20.0, 'h' => -3.0]), 'RUST', 'RUST',
            $this->range, 80.0, true), 'at a loss');
        $this->assertNull($this->reading->read($this->row(['m' => -20.0]), 'GOLD', 'GOLD', $this->range, 80.0,
            true), 'a strong tier');
    }

    public function testRangeBoundNeedsYourRecordAWideRangeAndATrade(): void
    {
        // Own return +4 % (Bronze): not range-bound, so a Rust holding in profit reads TAKE PROFIT.
        $this->assertSame('TAKE_PROFIT',
            $this->reading->read($this->row(['w' => 4.0]), 'RUST', 'RUST', $this->range, 95.0, true)['signal']);
        // A narrow range (low at 90 % of the high).
        $this->assertSame('TAKE_PROFIT',
            $this->reading->read($this->row(), 'RUST', 'RUST', [100.0, 90.0], 95.0, true)['signal']);
        // Never traded by that day, not held: nothing.
        $this->assertNull($this->reading->read($this->row(['o' => false, 'w' => null]), 'RUST', 'RUST',
            $this->range, 62.0, false));
    }
}
