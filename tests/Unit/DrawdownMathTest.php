<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use ovidiuro\myfinance2\App\Services\DrawdownService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Locks down the pure drawdown / raw-benchmark math that the quadrant chart and the
 * short-period (sub-1Y) alpha depend on. The CAGR side is covered by BenchmarkCagrTest;
 * this guards the pieces that feed it: peak-to-trough drawdown, the non-annualized raw
 * return, the span derived from a position's windows, and the raw span benchmark that
 * composes the two. All pure, so no database is needed.
 */
class DrawdownMathTest extends TestCase
{
    private DrawdownService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DrawdownService();
    }

    private function _invoke(string $method, array $args): mixed
    {
        $m = new ReflectionMethod(DrawdownService::class, $method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->service, $args);
    }

    private function _win(string $start, ?string $end, bool $open): array
    {
        return [
            'start_date' => Carbon::parse($start),
            'end_date'   => $end !== null ? Carbon::parse($end) : null,
            'is_open'    => $open,
        ];
    }

    // ---- _maxDrawdown -------------------------------------------------------

    public function testMaxDrawdownEmptyIsZero(): void
    {
        $this->assertSame(0.0, $this->_invoke('_maxDrawdown', [[]]));
    }

    public function testMaxDrawdownMonotonicRiseIsZero(): void
    {
        $this->assertSame(0.0, $this->_invoke('_maxDrawdown', [[100.0, 110.0, 120.0]]));
    }

    public function testMaxDrawdownHalvingIsFiftyPercent(): void
    {
        // Peak 100, trough 50 => (100 - 50) / 100 = 0.5.
        $this->assertEqualsWithDelta(0.5, $this->_invoke('_maxDrawdown', [[100.0, 50.0]]), 1e-9);
    }

    public function testMaxDrawdownTracksThePeakNotTheStart(): void
    {
        // Rises to 200 then falls to 100: the drawdown is measured from the 200 peak (0.5),
        // not the 100 start. A later, smaller dip must not lower the recorded maximum.
        $this->assertEqualsWithDelta(0.5, $this->_invoke('_maxDrawdown', [[100.0, 200.0, 100.0, 180.0]]), 1e-9);
    }

    public function testMaxDrawdownReturnsTheDeepestOfSeveralDips(): void
    {
        // Dip 1: peak 100 -> 80 (0.20). Dip 2: peak 120 -> 60 (0.50). Deepest wins.
        $this->assertEqualsWithDelta(0.5, $this->_invoke('_maxDrawdown', [[100.0, 80.0, 120.0, 60.0]]), 1e-9);
    }

    // ---- benchmarkRawBetween (public) ---------------------------------------

    public function testRawReturnIsSimplePercentChange(): void
    {
        $prices = ['2023-01-02' => 100.0, '2024-01-02' => 121.0];
        $this->assertEqualsWithDelta(21.0, $this->service->benchmarkRawBetween($prices, '2023-01-02'), 1e-6);
    }

    public function testRawReturnIsNotAnnualizedForShortWindows(): void
    {
        // +10% over ~6 months stays +10% raw (annualizing would roughly double it). This is
        // exactly why the raw benchmark exists for positions held under a year.
        $prices = ['2024-01-02' => 100.0, '2024-07-02' => 110.0];
        $this->assertEqualsWithDelta(10.0, $this->service->benchmarkRawBetween($prices, '2024-01-02'), 1e-6);
    }

    public function testRawReturnUsesNearestTradingDaysInsideTheWindow(): void
    {
        // First price on/after start and last price on/before end define the endpoints.
        $prices = [
            '2023-01-03' => 100.0,
            '2023-06-01' => 130.0,
            '2024-01-03' => 150.0,
            '2024-06-01' => 999.0, // after the end, must be ignored
        ];
        $raw = $this->service->benchmarkRawBetween($prices, '2023-01-01', '2024-01-03');
        $this->assertEqualsWithDelta(50.0, $raw, 1e-6);
    }

    public function testRawReturnEmptyPricesIsNull(): void
    {
        $this->assertNull($this->service->benchmarkRawBetween([], '2023-01-01'));
    }

    public function testRawReturnSinglePriceIsNull(): void
    {
        // Start and end resolve to the same (only) day, so there is no span to measure.
        $this->assertNull($this->service->benchmarkRawBetween(['2023-01-02' => 100.0], '2023-01-02'));
    }

    // ---- _windowSpan --------------------------------------------------------

    public function testWindowSpanEmptyIsNull(): void
    {
        $this->assertNull($this->_invoke('_windowSpan', [[]]));
    }

    public function testWindowSpanOpenWindowHasNullEnd(): void
    {
        // Still holding => end is null so the benchmark runs through the latest price.
        $span = $this->_invoke('_windowSpan', [[$this->_win('2023-01-02', null, true)]]);
        $this->assertSame('2023-01-02', $span['start']);
        $this->assertNull($span['end']);
    }

    public function testWindowSpanClosedWindowsRunEarliestStartToLatestEnd(): void
    {
        $windows = [
            $this->_win('2023-01-02', '2023-07-02', false),
            $this->_win('2024-01-02', '2025-01-02', false),
        ];
        $span = $this->_invoke('_windowSpan', [$windows]);
        $this->assertSame('2023-01-02', $span['start']);
        $this->assertSame('2025-01-02', $span['end']);
    }

    public function testWindowSpanWithAnyOpenWindowHasNullEnd(): void
    {
        // A closed window plus a still-open re-entry => measure through today (null end).
        $windows = [
            $this->_win('2023-01-02', '2023-07-02', false),
            $this->_win('2024-01-02', null, true),
        ];
        $span = $this->_invoke('_windowSpan', [$windows]);
        $this->assertSame('2023-01-02', $span['start']);
        $this->assertNull($span['end']);
    }

    // ---- _benchmarkSpanRaw (composition of the two above) -------------------

    public function testBenchmarkSpanRawMeasuresOverTheClosedSpan(): void
    {
        $prices = [
            '2023-01-02' => 100.0,
            '2024-01-02' => 150.0,
            '2025-01-02' => 200.0,
        ];
        // Closed window 2023-01-02 -> 2025-01-02: raw 100 -> 200 = +100%.
        $raw = $this->_invoke('_benchmarkSpanRaw', [$prices, [$this->_win('2023-01-02', '2025-01-02', false)]]);
        $this->assertEqualsWithDelta(100.0, $raw, 1e-6);
    }

    public function testBenchmarkSpanRawEmptyWindowsIsNull(): void
    {
        $this->assertNull($this->_invoke('_benchmarkSpanRaw', [['2023-01-02' => 100.0], []]));
    }
}
