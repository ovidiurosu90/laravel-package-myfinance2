<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use ovidiuro\myfinance2\App\Services\Xirr;
use PHPUnit\Framework\TestCase;

/**
 * Locks down the money-weighted return (XIRR) math. XIRR answers "how did my actual
 * euros do", crediting the timing and size of each flow; these cases pin the rate the
 * solver returns for histories where the answer is known analytically.
 */
class XirrTest extends TestCase
{
    private Xirr $xirr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->xirr = new Xirr();
    }

    private function _flow(string $date, float $amount): array
    {
        return ['date' => Carbon::parse($date), 'amount' => $amount];
    }

    public function testSingleYearTenPercent(): void
    {
        // -1000 today, +1100 in one year: (1+r) = 1.10, so r = 10%.
        $rate = $this->xirr->compute([
            $this->_flow('2023-01-01', -1000.0),
            $this->_flow('2024-01-01', 1100.0),
        ]);

        $this->assertNotNull($rate);
        $this->assertEqualsWithDelta(10.0, $rate, 0.1);
    }

    public function testTwoYearCompounding(): void
    {
        // -1000 today, +1210 in two years: (1+r)^2 = 1.21, so r = 10%.
        $rate = $this->xirr->compute([
            $this->_flow('2023-01-01', -1000.0),
            $this->_flow('2025-01-01', 1210.0),
        ]);

        $this->assertNotNull($rate);
        $this->assertEqualsWithDelta(10.0, $rate, 0.1);
    }

    public function testLossIsNegative(): void
    {
        // -1000 today, +500 in one year: half the money back, r = -50%.
        $rate = $this->xirr->compute([
            $this->_flow('2023-01-01', -1000.0),
            $this->_flow('2024-01-01', 500.0),
        ]);

        $this->assertNotNull($rate);
        $this->assertEqualsWithDelta(-50.0, $rate, 0.1);
    }

    public function testIntermediateDividendZeroesNpv(): void
    {
        // -1000, +50 dividend after a year, +1050 terminal after two years. No closed-form
        // here, so assert self-consistency: the returned rate must zero the NPV.
        $flows = [
            $this->_flow('2023-01-01', -1000.0),
            $this->_flow('2024-01-01', 50.0),
            $this->_flow('2025-01-01', 1050.0),
        ];
        $rate = $this->xirr->compute($flows);

        $this->assertNotNull($rate);

        $r   = $rate / 100.0;
        $npv = -1000.0
            + 50.0 / pow(1.0 + $r, 365.0 / 365.0)
            + 1050.0 / pow(1.0 + $r, 730.0 / 365.0);
        $this->assertEqualsWithDelta(0.0, $npv, 0.5);
    }

    public function testMultipleBuysRewardTiming(): void
    {
        // Adding capital before a run-up should still yield a sensible positive rate.
        $rate = $this->xirr->compute([
            $this->_flow('2023-01-01', -1000.0),
            $this->_flow('2023-07-01', -1000.0),
            $this->_flow('2025-01-01', 2600.0),
        ]);

        $this->assertNotNull($rate);
        $this->assertGreaterThan(0.0, $rate);
    }

    public function testNoSignChangeReturnsNull(): void
    {
        // All outflows: there is no rate that makes the money come back.
        $this->assertNull($this->xirr->compute([
            $this->_flow('2023-01-01', -1000.0),
            $this->_flow('2024-01-01', -500.0),
        ]));
    }

    public function testSingleFlowReturnsNull(): void
    {
        $this->assertNull($this->xirr->compute([
            $this->_flow('2023-01-01', -1000.0),
        ]));
    }

    public function testEmptyReturnsNull(): void
    {
        $this->assertNull($this->xirr->compute([]));
    }
}
