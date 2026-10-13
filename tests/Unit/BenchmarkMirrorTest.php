<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\BenchmarkMirror;
use PHPUnit\Framework\TestCase;

/**
 * The same trades made in the benchmark: same amounts in, same share of the position out, same days.
 */
class BenchmarkMirrorTest extends TestCase
{
    private const BENCH = [
        '2025-01-02' => 100.0,
        '2025-03-03' => 80.0,
        '2025-06-02' => 120.0,
        '2025-09-01' => 150.0,
    ];

    private function _trade(string $date, string $action, float $qty, float $price): array
    {
        return ['date' => $date, 'action' => $action, 'quantity' => $qty, 'price' => $price];
    }

    public function testSingleBuyFollowsTheBenchmark(): void
    {
        $mirror = new BenchmarkMirror(self::BENCH);
        $trades = [$this->_trade('2025-01-02', 'BUY', 10, 50.0)];

        // 500 in at 100: worth 600 at 120.
        $this->assertEqualsWithDelta(20.0, $mirror->returnOn($trades, '2025-06-02'), 1e-9);
        // A day without a close uses the last one on or before it.
        $this->assertEqualsWithDelta(20.0, $mirror->returnOn($trades, '2025-06-15'), 1e-9);
    }

    public function testLaterBuysUseTheirOwnDayAndAmount(): void
    {
        $mirror = new BenchmarkMirror(self::BENCH);
        $trades = [
            $this->_trade('2025-01-02', 'BUY', 10, 50.0), // 500 in at 100 -> 5 units
            $this->_trade('2025-03-03', 'BUY', 20, 20.0), // 400 in at 80  -> 5 units
        ];

        // 10 units at 120 = 1200 on 900 invested.
        $this->assertEqualsWithDelta(1200.0 / 900.0 * 100.0 - 100.0, $mirror->returnOn($trades, '2025-06-02'), 1e-9);
    }

    public function testSellsTakeOutTheSameShareOfThePosition(): void
    {
        $mirror = new BenchmarkMirror(self::BENCH);
        $trades = [
            $this->_trade('2025-01-02', 'BUY', 100, 10.0),  // 1000 in at 100 -> 10 units
            $this->_trade('2025-06-02', 'SELL', 25, 99.0),   // a quarter: 2.5 units out at 120 -> 300
        ];

        // 300 taken out + 7.5 units at 150 (1125) on 1000 invested.
        $this->assertEqualsWithDelta(42.5, $mirror->returnOn($trades, '2025-09-01'), 1e-9);
        // Trades after the day do not count yet.
        $this->assertEqualsWithDelta(-20.0, $mirror->returnOn($trades, '2025-03-03'), 1e-9);
    }

    public function testNoBenchmarkPriceGivesNull(): void
    {
        $mirror = new BenchmarkMirror(self::BENCH);

        $this->assertNull($mirror->returnOn([$this->_trade('2024-12-01', 'BUY', 1, 1.0)], '2025-06-02'));
        $empty = new BenchmarkMirror([]);
        $this->assertNull($empty->returnOn([$this->_trade('2025-01-02', 'BUY', 1, 1.0)], '2025-06-02'));
    }
}
