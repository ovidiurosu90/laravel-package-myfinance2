<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use ovidiuro\myfinance2\App\Services\DrawdownService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Locks down the VUSA.AS same-window benchmark CAGR used for the "better or worse than the
 * S&P 500" alpha. Both the single-span method (watchlist-only) and the span-over-windows method
 * (held positions: earliest start to today or last sell) are pure, so no database is needed.
 */
class BenchmarkCagrTest extends TestCase
{
    private DrawdownService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DrawdownService();
    }

    private function _win(string $start, ?string $end, bool $open): array
    {
        return [
            'start_date' => Carbon::parse($start),
            'end_date'   => $end !== null ? Carbon::parse($end) : null,
            'is_open'    => $open,
        ];
    }

    private function _span(array $vusaPrices, array $windows): ?float
    {
        $method = new ReflectionMethod(DrawdownService::class, '_benchmarkSpanCagr');
        $method->setAccessible(true);
        return $method->invoke($this->service, $vusaPrices, $windows);
    }

    public function testDoubleOverTwoYears(): void
    {
        // 100 -> 200 over ~2 years: 2^(1/2) - 1 = 41.42%/yr.
        $prices = [
            '2023-01-02' => 100.0,
            '2024-01-02' => 150.0,
            '2025-01-02' => 200.0,
        ];
        $cagr = $this->service->benchmarkCagrBetween($prices, '2023-01-02', '2025-01-02');

        $this->assertNotNull($cagr);
        $this->assertEqualsWithDelta(41.4, $cagr, 0.5);
    }

    public function testUsesNearestTradingDayOnOrAfterStart(): void
    {
        // Start date falls on a non-trading day; the first available price is used.
        $prices = [
            '2023-01-03' => 100.0,
            '2025-01-03' => 200.0,
        ];
        $cagr = $this->service->benchmarkCagrBetween($prices, '2023-01-01', '2025-01-03');

        $this->assertNotNull($cagr);
        $this->assertEqualsWithDelta(41.4, $cagr, 0.6);
    }

    public function testDefaultsEndToLatestPrice(): void
    {
        $prices = [
            '2023-01-02' => 100.0,
            '2025-01-02' => 121.0,
        ];
        $cagr = $this->service->benchmarkCagrBetween($prices, '2023-01-02');

        $this->assertNotNull($cagr);
        $this->assertEqualsWithDelta(10.0, $cagr, 0.2);
    }

    public function testWindowUnderOneYearIsNull(): void
    {
        $prices = [
            '2024-06-01' => 100.0,
            '2024-12-01' => 130.0,
        ];
        $this->assertNull($this->service->benchmarkCagrBetween($prices, '2024-06-01', '2024-12-01'));
    }

    public function testEmptyPricesIsNull(): void
    {
        $this->assertNull($this->service->benchmarkCagrBetween([], '2023-01-01', '2025-01-01'));
    }

    public function testSpanOpenWindowRunsThroughLatestPrice(): void
    {
        // Open window from 2023-01-02; benchmark doubles to the latest price (~2 years) => ~41.4%.
        $prices = [
            '2023-01-02' => 100.0,
            '2024-01-02' => 150.0,
            '2025-01-02' => 200.0,
        ];
        $cagr = $this->_span($prices, [$this->_win('2023-01-02', null, true)]);

        $this->assertNotNull($cagr);
        $this->assertEqualsWithDelta(41.4, $cagr, 0.6);
    }

    public function testSpanRunsFromEarliestStartToLastSell(): void
    {
        // Two closed windows; the span is the earliest start (2023-01-02) to the last sell
        // (2025-01-02): 100 -> 200 over ~2 years => ~41.4%/yr.
        $prices = [
            '2023-01-02' => 100.0,
            '2024-01-02' => 150.0,
            '2025-01-02' => 200.0,
        ];
        $windows = [
            $this->_win('2023-01-02', '2023-07-02', false),
            $this->_win('2024-01-02', '2025-01-02', false),
        ];
        $cagr = $this->_span($prices, $windows);

        $this->assertNotNull($cagr);
        $this->assertEqualsWithDelta(41.4, $cagr, 0.6);
    }

    public function testSpanUnderOneYearIsNull(): void
    {
        $prices = [
            '2024-06-01' => 100.0,
            '2024-12-01' => 130.0,
        ];
        $this->assertNull($this->_span($prices, [$this->_win('2024-06-01', '2024-12-01', false)]));
    }

    public function testSpanEmptyWindowsIsNull(): void
    {
        $this->assertNull($this->_span(['2023-01-02' => 100.0], []));
    }
}
