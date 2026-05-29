<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use ovidiuro\myfinance2\App\Services\DrawdownService;
use ovidiuro\myfinance2\App\Services\SymbolPerformanceWindowBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Contract test for the seam between SymbolPerformanceWindowBuilder (which turns a
 * trade history into position windows) and DrawdownService (which measures the
 * same-window benchmark CAGR over those windows). The two units are tested in
 * isolation elsewhere; this guards the shape they pass across the boundary, the
 * start_date / end_date / is_open keys and Carbon types, so a change to the window
 * format cannot silently break the benchmark span. No database: real builder output
 * is fed into the real span logic with a synthetic VUSA.AS price series.
 */
class WindowSpanBenchmarkTest extends TestCase
{
    /** Synthetic VUSA.AS: doubles 100 -> 200 over two years. */
    private const VUSA = [
        '2023-01-02' => 100.0,
        '2024-01-02' => 150.0,
        '2025-01-02' => 200.0,
    ];

    private DrawdownService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DrawdownService();
    }

    private function _trade(string $action, float $qty, string $date): object
    {
        return (object) [
            'symbol'    => 'TEST',
            'action'    => $action,
            'quantity'  => $qty,
            'timestamp' => Carbon::parse($date),
        ];
    }

    /** @param object[] $trades */
    private function _windowsFor(array $trades): array
    {
        $collection = new Collection($trades);
        $built      = (new SymbolPerformanceWindowBuilder())->build($collection);
        return $built['TEST'] ?? [];
    }

    private function _spanCagr(array $windows): ?float
    {
        $m = new ReflectionMethod(DrawdownService::class, '_benchmarkSpanCagr');
        $m->setAccessible(true);
        return $m->invoke($this->service, self::VUSA, $windows);
    }

    public function testOpenPositionSpansFromBuyThroughLatestPrice(): void
    {
        $windows = $this->_windowsFor([
            $this->_trade('BUY', 10.0, '2023-01-02'),
        ]);

        // Builder contract: one still-open window starting at the buy.
        $this->assertCount(1, $windows);
        $this->assertTrue($windows[0]['is_open']);

        // Span 2023-01-02 -> latest (200): 100 -> 200 over ~2y = ~41.4%/yr, and it must agree
        // with the public single-span helper over the same dates.
        $cagr = $this->_spanCagr($windows);
        $this->assertNotNull($cagr);
        $this->assertEqualsWithDelta(41.4, $cagr, 0.6);
        $this->assertEqualsWithDelta(
            $this->service->benchmarkCagrBetween(self::VUSA, '2023-01-02'),
            $cagr,
            1e-9
        );
    }

    public function testFullyExitedPositionSpansBuyToSell(): void
    {
        $windows = $this->_windowsFor([
            $this->_trade('BUY', 10.0, '2023-01-02'),
            $this->_trade('SELL', 10.0, '2025-01-02'),
        ]);

        $this->assertCount(1, $windows);
        $this->assertFalse($windows[0]['is_open']);

        // Closed span 2023-01-02 -> 2025-01-02: ~41.4%/yr.
        $cagr = $this->_spanCagr($windows);
        $this->assertNotNull($cagr);
        $this->assertEqualsWithDelta(41.4, $cagr, 0.6);
    }

    public function testReEntryUsesEarliestStartAndRunsThroughLatestPrice(): void
    {
        // Buy, fully exit, then re-enter and still hold: builder emits a closed window plus an
        // open one. The span must take the earliest start (first buy) and, because a window is
        // still open, run through the latest price, not the interim sell.
        $windows = $this->_windowsFor([
            $this->_trade('BUY', 10.0, '2023-01-02'),
            $this->_trade('SELL', 10.0, '2024-01-02'),
            $this->_trade('BUY', 5.0, '2024-07-02'),
        ]);

        $this->assertCount(2, $windows);
        $this->assertFalse($windows[0]['is_open']);
        $this->assertTrue($windows[1]['is_open']);

        $cagr = $this->_spanCagr($windows);
        $this->assertNotNull($cagr);
        $this->assertEqualsWithDelta(
            $this->service->benchmarkCagrBetween(self::VUSA, '2023-01-02'),
            $cagr,
            1e-9
        );
    }

    public function testNoBuysProducesNoWindowsAndNullSpan(): void
    {
        $windows = $this->_windowsFor([]);
        $this->assertSame([], $windows);
        $this->assertNull($this->_spanCagr($windows));
    }
}
