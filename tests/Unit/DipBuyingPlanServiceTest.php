<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use ovidiuro\myfinance2\App\Services\DipBuyingPlanService;
use PHPUnit\Framework\TestCase;

/**
 * Locks down the pure scoring core of the Dip Buying Plan engine: band resolution, the gap verdict,
 * the ladder table states and the stall backstop. These methods take everything they need as
 * arguments (no DB, no config when an explicit band table is passed), so the same code that drives
 * the live panel, the email and the backtest is exercised here without booting Laravel.
 */
class DipBuyingPlanServiceTest extends TestCase
{
    private DipBuyingPlanService $service;

    /** The default front-loaded ladder, passed explicitly so the test needs no config. */
    private array $bands;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DipBuyingPlanService();
        $this->bands   = $this->service->resolveBands([
            ['dd' => 10, 'target' => 20],
            ['dd' => 15, 'target' => 40],
            ['dd' => 20, 'target' => 60],
            ['dd' => 28, 'target' => 85],
            ['dd' => 35, 'target' => 100],
        ]);
    }

    public function testResolveBandsSortsAndAddsZeroFloor(): void
    {
        $bands = $this->service->resolveBands([
            ['dd' => 20, 'target' => 60],
            ['dd' => 10, 'target' => 20],
        ]);

        $this->assertSame(0.0, $bands[0]['dd'], 'A band-0 floor is always prepended.');
        $this->assertSame(0.0, $bands[0]['target']);
        $this->assertSame(10.0, $bands[1]['dd'], 'Bands are sorted ascending by drawdown.');
        $this->assertSame(20.0, $bands[2]['dd']);
    }

    public function testResolveBandPicksDeepestReached(): void
    {
        $this->assertSame(0.0,  $this->service->resolveBand(9.0, $this->bands)['dd']);
        $this->assertSame(10.0, $this->service->resolveBand(12.0, $this->bands)['dd']);
        $this->assertSame(15.0, $this->service->resolveBand(16.2, $this->bands)['dd']);
        $this->assertSame(35.0, $this->service->resolveBand(40.0, $this->bands)['dd']);
    }

    public function testVerdictNoDipBelowFirstBand(): void
    {
        $plan = $this->service->computePlan(5.0, 0.0, 10000.0, $this->bands, 5.0);

        $this->assertSame(DipBuyingPlanService::VERDICT_NO_DIP, $plan['verdict']);
        $this->assertSame(0.0, $plan['target_pct']);
        $this->assertSame(0.0, $plan['suggested_tranche_eur']);
    }

    public function testVerdictBehindSuggestsTheGapTranche(): void
    {
        // Band 15%+ => target 40% (EUR 4,000); deployed EUR 1,500 (15%) => gap 25pp.
        $plan = $this->service->computePlan(16.2, 1500.0, 10000.0, $this->bands, 5.0);

        $this->assertSame(DipBuyingPlanService::VERDICT_BEHIND, $plan['verdict']);
        $this->assertSame(40.0, $plan['target_pct']);
        $this->assertSame(4000.0, $plan['target_eur']);
        $this->assertSame(2500.0, $plan['suggested_tranche_eur']);
    }

    public function testVerdictOnPlanWithinTolerance(): void
    {
        // Deployed 38% vs target 40% => gap 2pp, inside the 5pp tolerance.
        $plan = $this->service->computePlan(15.0, 3800.0, 10000.0, $this->bands, 5.0);

        $this->assertSame(DipBuyingPlanService::VERDICT_ON_PLAN, $plan['verdict']);
    }

    public function testVerdictAheadHoldsDryPowder(): void
    {
        // Band 10%+ target 20%, but already 40% deployed => 20pp ahead.
        $plan = $this->service->computePlan(10.0, 4000.0, 10000.0, $this->bands, 5.0);

        $this->assertSame(DipBuyingPlanService::VERDICT_AHEAD, $plan['verdict']);
        $this->assertSame(0.0, $plan['suggested_tranche_eur'], 'No tranche suggested when ahead.');
    }

    public function testStallReleaseRaisesTargetAndFlipsToBehind(): void
    {
        // Band 10%+ target 20%, deployed 20% => on plan. A stall release to 50% makes it behind.
        $released = $this->service->stallReleasedTarget(20.0, 0.375); // 20 + 0.375*80 = 50
        $this->assertSame(50.0, $released);

        $plan = $this->service->computePlan(12.0, 2000.0, 10000.0, $this->bands, 5.0, $released);

        $this->assertTrue($plan['stall_active']);
        $this->assertSame(50.0, $plan['target_pct']);
        $this->assertSame(DipBuyingPlanService::VERDICT_BEHIND, $plan['verdict']);
    }

    public function testBuildLadderStates(): void
    {
        $rows = $this->service->buildLadder($this->bands, 10000.0, 16.2, 0.0);

        // dd => state. 0 none, 10 done, 15 current, 20/28/35 reserved.
        $byDd = [];
        foreach ($rows as $row) {
            $byDd[(int) $row['dd']] = $row['state'];
        }

        $this->assertSame(DipBuyingPlanService::STATE_NONE, $byDd[0]);
        $this->assertSame(DipBuyingPlanService::STATE_DONE, $byDd[10]);
        $this->assertSame(DipBuyingPlanService::STATE_CURRENT, $byDd[15]);
        $this->assertSame(DipBuyingPlanService::STATE_RESERVED, $byDd[20]);
        $this->assertSame(DipBuyingPlanService::STATE_RESERVED, $byDd[35]);
    }

    public function testStallInactiveBeforeTheWindow(): void
    {
        $series = $this->_flatSeries('2025-01-01', 4, 12.0); // band 1, 4 monthly points
        $stall  = $this->service->computeStall($series, $this->bands, 6, 10.0, Carbon::parse('2025-04-01'));

        $this->assertFalse($stall['active'], 'Only ~3 months in, the backstop has not tripped.');
    }

    public function testStallActiveAfterTheWindowRampsRelease(): void
    {
        // 12% drawdown held flat for ~8 months without deepening: stalled.
        $series = $this->_flatSeries('2025-01-01', 9, 12.0);
        $stall  = $this->service->computeStall($series, $this->bands, 6, 10.0, Carbon::parse('2025-09-01'));

        $this->assertTrue($stall['active']);
        $this->assertSame('2025-01-01', $stall['last_deepen_date']);
        $this->assertGreaterThan(0.0, $stall['released_fraction']);
        $this->assertLessThanOrEqual(1.0, $stall['released_fraction']);
    }

    public function testFreshDeeperBandResetsTheStallClock(): void
    {
        // Same long episode, but it deepens to a new band near the end: clock resets, not stalled.
        $series = $this->_flatSeries('2025-01-01', 9, 12.0);
        $series['2025-08-01'] = 22.0; // jumps into the 20%+ band a month before evaluation
        $stall = $this->service->computeStall($series, $this->bands, 6, 10.0, Carbon::parse('2025-09-01'));

        $this->assertFalse($stall['active'], 'A fresh deeper band resets the six-month stall clock.');
        $this->assertSame(0.0, $stall['released_fraction'], 'Nothing is released while the clock is reset.');
    }

    /**
     * Build a flat monthly drawdown series of $count points from $start, all at $dd.
     *
     * @return array<string, float>
     */
    private function _flatSeries(string $start, int $count, float $dd): array
    {
        $series = [];
        $date   = Carbon::parse($start);
        for ($i = 0; $i < $count; $i++) {
            $series[$date->format('Y-m-d')] = $dd;
            $date = $date->addMonth();
        }

        return $series;
    }
}
