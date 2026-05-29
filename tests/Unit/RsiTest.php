<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\TechnicalIndicatorsService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Locks down the Wilder RSI (14-period) used for the overbought/oversold signal. The
 * easy-to-break parts are the count(prices) < period + 1 guard, the 0 and 100 extremes,
 * and the divide-by-zero when there are no losses. _computeRsi is pure, so no database
 * is needed.
 */
class RsiTest extends TestCase
{
    private const PERIOD = 14;

    private TechnicalIndicatorsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TechnicalIndicatorsService();
    }

    private function _rsi(array $prices, int $period = self::PERIOD): ?float
    {
        $m = new ReflectionMethod(TechnicalIndicatorsService::class, '_computeRsi');
        $m->setAccessible(true);
        return $m->invoke($this->service, $prices, $period);
    }

    public function testTooFewPricesReturnsNull(): void
    {
        // A 14-period RSI needs at least 15 closes (period + 1 changes).
        $prices = array_fill(0, self::PERIOD, 100.0); // exactly 14
        $this->assertNull($this->_rsi($prices));
    }

    public function testStraightUpIsHundred(): void
    {
        // No losses at all => avgLoss is 0 => RSI saturates at 100.
        $prices = [];
        for ($i = 0; $i < 20; $i++) {
            $prices[] = 100.0 + $i; // strictly increasing
        }
        $this->assertSame(100.0, $this->_rsi($prices));
    }

    public function testStraightDownIsZero(): void
    {
        // No gains at all => RS is 0 => RSI is 0.
        $prices = [];
        for ($i = 0; $i < 20; $i++) {
            $prices[] = 200.0 - $i; // strictly decreasing
        }
        $this->assertSame(0.0, $this->_rsi($prices));
    }

    public function testKnownReferenceValue(): void
    {
        // Exactly period + 1 = 15 closes, so there is no Wilder smoothing tail and the
        // averages are plain means of the 14 changes. Nine +2 gains then five -1 losses:
        //   avgGain = 18/14, avgLoss = 5/14, RS = 3.6, RSI = 100 - 100/4.6 = 78.26 -> 78.3.
        $prices = [100.0];
        for ($i = 0; $i < 9; $i++) {
            $prices[] = end($prices) + 2.0;
        }
        for ($i = 0; $i < 5; $i++) {
            $prices[] = end($prices) - 1.0;
        }

        $this->assertCount(15, $prices);
        $this->assertEqualsWithDelta(78.3, $this->_rsi($prices), 0.05);
    }
}
