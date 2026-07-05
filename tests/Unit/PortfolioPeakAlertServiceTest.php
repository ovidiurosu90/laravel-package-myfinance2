<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use ovidiuro\myfinance2\App\Services\PortfolioPeakAlertService;

/**
 * Pure tests for the Portfolio Peak proximity core: the window slice, the two proximity transforms
 * (change_eur against |peak|, change_pct on the value index), the per-window breakdown (peak, peak
 * date, threshold, 3M context row) and the fire gate derived from it. No DB, no mail, no HTTP.
 *
 * The scanned methods read windows/thresholds from config(), so a minimal container with a config
 * repository is bound in setUp (no full Laravel boot). Each test can override the windows and
 * thresholds to isolate a single (metric, window) pair.
 */
class PortfolioPeakAlertServiceTest extends TestCase
{
    private PortfolioPeakAlertService $_service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->_service = new PortfolioPeakAlertService();
        $this->_bindConfig([
            'windows'           => ['6m', '1y', '2y'],
            'display_windows'   => ['3m', '6m', '1y', '2y'],
            'window_thresholds' => [
                'change_eur' => ['3m' => 5, '6m' => 10, '1y' => 20, '2y' => 30],
                'change_pct' => ['3m' => 0.5, '6m' => 1, '1y' => 3, '2y' => 5],
            ],
            'min_peak_abs_eur'  => 1000,
            'reminder_days'     => 7,
        ]);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    /**
     * Bind a fresh container exposing config('alerts.portfolio_peak.*').
     *
     * @param array $portfolioPeak
     *
     * @return void
     */
    private function _bindConfig(array $portfolioPeak): void
    {
        $container = new Container();
        $container->instance('config', new ConfigRepository([
            'alerts' => ['portfolio_peak' => $portfolioPeak],
        ]));
        Container::setInstance($container);
    }

    /**
     * @param string $name
     * @param mixed  ...$args
     *
     * @return mixed
     */
    private function _call(string $name, ...$args)
    {
        $m = new ReflectionMethod(PortfolioPeakAlertService::class, $name);
        $m->setAccessible(true);
        return $m->invoke($this->_service, ...$args);
    }

    /**
     * The fire gate: build the full breakdown for both metrics, then keep only the triggered rows.
     *
     * @param array<string, float> $eur
     * @param array<string, float> $pct
     *
     * @return array
     */
    private function _triggered(array $eur, array $pct): array
    {
        return $this->_call('_triggeredPairs', $this->_call('_buildBreakdown', $eur, $pct));
    }

    /**
     * A daily date => value series ending today, so every point is inside every window slice and the
     * window peak equals max($values) regardless of the window length.
     *
     * @param array<int, float> $values  oldest first, last entry is "today"
     *
     * @return array<string, float>
     */
    private function _recentSeries(array $values): array
    {
        $series = [];
        $n      = count($values);
        foreach (array_values($values) as $i => $v) {
            $date          = Carbon::today()->subDays($n - 1 - $i)->format('Y-m-d');
            $series[$date] = (float) $v;
        }
        return $series;
    }

    // -------------------------------------------------------------------------
    // _sliceWindow
    // -------------------------------------------------------------------------

    public function testSliceWindowKeepsOnlyPointsInsideCutoff(): void
    {
        $series = [
            Carbon::today()->subDays(800)->format('Y-m-d') => 1.0, // outside 2y
            Carbon::today()->subDays(700)->format('Y-m-d') => 2.0, // inside 2y, outside 1y
            Carbon::today()->subDays(300)->format('Y-m-d') => 3.0, // inside 1y, outside 6m
            Carbon::today()->subDays(100)->format('Y-m-d') => 4.0, // inside 6m
            Carbon::today()->format('Y-m-d')               => 5.0, // today
        ];

        $this->assertCount(2, $this->_call('_sliceWindow', $series, 182), '6m keeps the last 2 points.');
        $this->assertCount(3, $this->_call('_sliceWindow', $series, 365), '1y keeps the last 3 points.');
        $this->assertCount(4, $this->_call('_sliceWindow', $series, 730), '2y keeps 4 points (800d excluded).');
    }

    // -------------------------------------------------------------------------
    // change_eur proximity (relative to |peak|, negative peaks in scope)
    // -------------------------------------------------------------------------

    public function testChangeEurNegativePeakFarThenRecovered(): void
    {
        // 1Y window only, threshold 5%. Peak -30000, current -100000 => proximity -233%, far.
        $this->_bindConfig([
            'windows'           => ['1y'],
            'window_thresholds' => ['change_eur' => ['1y' => 5]],
            'min_peak_abs_eur'  => 1000,
        ]);

        $far = $this->_triggered($this->_recentSeries([-30000, -50000, -100000]), []);
        $this->assertSame([], $far, 'Current far below the negative peak does not trigger.');

        // Recover to -31500: proximity = (-31500 - -30000)/30000*100 = -5.0% => exactly at threshold.
        $near = $this->_triggered($this->_recentSeries([-30000, -60000, -31500]), []);
        $this->assertCount(1, $near);
        $this->assertSame('change_eur', $near[0]['metric']);
        $this->assertSame('1y', $near[0]['window']);
        $this->assertEqualsWithDelta(-5.0, $near[0]['proximity_pct'], 0.001);
    }

    public function testChangeEurPositivePeakBoundary(): void
    {
        // 6m only, threshold 3%. Peak 10000; current 9700 => -3% (inside), 9600 => -4% (outside).
        $this->_bindConfig([
            'windows'           => ['6m'],
            'window_thresholds' => ['change_eur' => ['6m' => 3]],
            'min_peak_abs_eur'  => 1000,
        ]);

        $inside = $this->_triggered($this->_recentSeries([10000, 5000, 9700]), []);
        $this->assertCount(1, $inside, 'Exactly at the threshold triggers.');
        $this->assertEqualsWithDelta(-3.0, $inside[0]['proximity_pct'], 0.001);

        $outside = $this->_triggered($this->_recentSeries([10000, 5000, 9600]), []);
        $this->assertSame([], $outside, 'One tick past the threshold does not trigger.');
    }

    public function testChangeEurMinPeakFloorSkipsNearZeroPeak(): void
    {
        // Peak 500 is under the 1000 floor => window skipped even though current sits on the peak.
        $pairs = $this->_triggered($this->_recentSeries([500, 200, 500]), []);
        $this->assertSame([], $pairs, 'A sub-floor peak magnitude is treated as noise.');
    }

    // -------------------------------------------------------------------------
    // change_pct proximity (value index 1 + cp/100)
    // -------------------------------------------------------------------------

    public function testChangePctPositivePeakValueIndex(): void
    {
        // 6m only, threshold 3%. Peak 40%, current 38.5%: (1.385-1.40)/1.40*100 = -1.071%.
        $this->_bindConfig([
            'windows'           => ['6m'],
            'window_thresholds' => ['change_pct' => ['6m' => 3]],
            'min_peak_abs_eur'  => 1000,
        ]);

        $pairs = $this->_triggered([], $this->_recentSeries([40, 10, 38.5]));
        $this->assertCount(1, $pairs);
        $this->assertSame('change_pct', $pairs[0]['metric']);
        $this->assertEqualsWithDelta(-1.07, $pairs[0]['proximity_pct'], 0.01);
    }

    public function testChangePctNegativePeakValueIndex(): void
    {
        // 2y only, threshold 8%. Peak -3%, current -10%: (0.90-0.97)/0.97*100 = -7.216% => inside.
        $this->_bindConfig([
            'windows'           => ['2y'],
            'window_thresholds' => ['change_pct' => ['2y' => 8]],
            'min_peak_abs_eur'  => 1000,
        ]);

        $pairs = $this->_triggered([], $this->_recentSeries([-3, -25, -10]));
        $this->assertCount(1, $pairs);
        $this->assertEqualsWithDelta(-7.22, $pairs[0]['proximity_pct'], 0.01);
    }

    // -------------------------------------------------------------------------
    // Breakdown structure: every display window, peak date, 3M context row
    // -------------------------------------------------------------------------

    public function testBreakdownCoversEveryDisplayWindowWithPeakDate(): void
    {
        // Peak 20 lands on yesterday; current 15 today. change_pct series (eur empty).
        $series    = $this->_recentSeries([10, 20, 15]);
        $breakdown = $this->_call('_buildBreakdown', [], $series);

        $windows = array_column($breakdown, 'window');
        $this->assertSame(['3m', '6m', '1y', '2y'], $windows, 'All display windows appear, 3M first.');

        $byWindow = [];
        foreach ($breakdown as $row) {
            $byWindow[$row['window']] = $row;
        }

        $this->assertEqualsWithDelta(20.0, $byWindow['6m']['peak'], 0.001);
        $this->assertEqualsWithDelta(15.0, $byWindow['6m']['current'], 0.001);
        $this->assertSame(
            Carbon::today()->subDays(1)->format('Y-m-d'),
            $byWindow['6m']['peak_date'],
            'The peak date is the date of the window max.'
        );

        // 3M is context only: present, carries its threshold, but can never be a trigger row.
        $this->assertFalse($byWindow['3m']['is_trigger'], '3M never gates a send.');
        $this->assertFalse($byWindow['3m']['triggered']);
        $this->assertTrue($byWindow['1y']['is_trigger']);
    }

    public function testThreeMonthWindowNeverFiresEvenAtItsHigh(): void
    {
        // Series sitting exactly on its high => proximity 0 for every window, but 3M is context only.
        $breakdown = $this->_call('_buildBreakdown', [], $this->_recentSeries([5, 8, 10, 10]));
        $triggered = $this->_call('_triggeredPairs', $breakdown);

        $windows = array_unique(array_column($triggered, 'window'));
        sort($windows);
        $this->assertSame(['1y', '2y', '6m'], $windows, '3M is excluded from the fired set.');
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function testEmptySeriesReturnEmpty(): void
    {
        $this->assertSame([], $this->_triggered([], []));
    }

    public function testUnknownWindowKeyIsSkipped(): void
    {
        $this->_bindConfig([
            'windows'           => ['bogus'],
            'display_windows'   => ['bogus'],
            'window_thresholds' => [],
            'min_peak_abs_eur'  => 1000,
        ]);

        $breakdown = $this->_call('_buildBreakdown', $this->_recentSeries([10000, 9000, 10000]), []);
        $this->assertSame([], $breakdown, 'A window with no WINDOW_DAYS entry yields no rows.');
    }

    public function testNullProximityClosureMarksEveryWindowSkipped(): void
    {
        $rows = $this->_call(
            '_metricBreakdown',
            $this->_recentSeries([100, 90, 100]),
            'change_eur',
            fn (float $current, float $peak): ?float => null
        );

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertTrue($row['skipped'], 'A null-proximity window is marked skipped.');
            $this->assertFalse($row['triggered']);
            $this->assertNull($row['proximity_pct']);
        }
    }

    public function testBothMetricsCanTriggerAtOnce(): void
    {
        // Both series sitting on their window highs => one triggered pair per trigger window.
        $eur = $this->_recentSeries([2000, 1500, 2000]);
        $pct = $this->_recentSeries([10, 5, 10]);

        $metrics = array_unique(array_column($this->_triggered($eur, $pct), 'metric'));

        sort($metrics);
        $this->assertSame(['change_eur', 'change_pct'], $metrics);
    }
}
