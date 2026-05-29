<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\SymbolPerformanceService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Locks down the CAGR (geometric, time-weighted) annualization used for position returns,
 * and in particular guards against a regression back to simple annualization (return / years),
 * which over-states big or long winners. The two private formulas are exercised directly via
 * reflection so no database is needed.
 */
class SymbolPerformanceCagrTest extends TestCase
{
    private SymbolPerformanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SymbolPerformanceService();
    }

    private function _annualize(?float $pct, int $days, float $gainEur, float $investedEur): array
    {
        $method = new ReflectionMethod(SymbolPerformanceService::class, '_annualizeReturn');
        $method->setAccessible(true);
        return $method->invoke($this->service, $pct, $days, $gainEur, $investedEur);
    }

    public function testOneYearDoubleIsAboutOneHundredPercent(): void
    {
        // +100% over ~1 year compounds to ~100%/yr.
        $result = $this->_annualize(100.0, 365, 1000.0, 1000.0);
        $this->assertEqualsWithDelta(100.0, $result['pct'], 0.5);
        // EUR/y is the first-year euro equivalent: invested * CAGR.
        $this->assertEqualsWithDelta(1000.0, $result['eur'], 10.0);
    }

    public function testCagrNotSimpleAnnualizationOnLongWinner(): void
    {
        // +200% over two years: simple annualization would read 100%/yr; CAGR is ~73%/yr.
        // This is the regression guard: the figure must be the geometric ~73, never ~100.
        $result = $this->_annualize(200.0, 730, 2000.0, 1000.0);
        $this->assertEqualsWithDelta(73.2, $result['pct'], 1.0);
        $this->assertLessThan(80.0, $result['pct']);
    }

    public function testShortWindowsAreNotAnnualized(): void
    {
        $this->assertNull($this->_annualize(50.0, 10, 500.0, 1000.0)['pct']);   // < 30 days
        $this->assertNull($this->_annualize(50.0, 200, 500.0, 1000.0)['pct']);  // 30-364 days
    }

    public function testOverallMultiWindowIsBlendedNotChained(): void
    {
        // The overall multi-window figure reuses _annualizeReturn on the BLENDED total return,
        // never a geometric chain of the windows. Two +50% buy/sell episodes (equal capital) ->
        // total profit 1000 on 2000 deployed = +50% blended, annualized over the 730 days held
        // = ~22.5%/yr. The chained value would be ~50%/yr; this guards against that inflation
        // (the bug that made a multi-window position read ~19% instead of ~6%).
        $blendedPct   = 50.0;   // (500 + 500) / 2000 * 100
        $totalDays    = 730;
        $totalGainEur = 1000.0;
        $totalInvest  = 2000.0;

        $result = $this->_annualize($blendedPct, $totalDays, $totalGainEur, $totalInvest);
        $this->assertEqualsWithDelta(22.5, $result['pct'], 0.5);
        $this->assertLessThan(30.0, $result['pct']); // nowhere near the chained 50%
    }

    public function testOverallUnderOneYearIsNull(): void
    {
        // Total time held under a year is not annualized.
        $this->assertNull($this->_annualize(10.0, 200, 100.0, 1000.0)['pct']);
    }
}
