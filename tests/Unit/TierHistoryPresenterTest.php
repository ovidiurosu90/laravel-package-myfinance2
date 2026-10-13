<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierCalculationService as T;
use ovidiuro\myfinance2\App\Services\TierHistoryPresenter;
use PHPUnit\Framework\TestCase;

/**
 * The view data behind the "Time in tier (12M)" line and the tier history chart: runs of consecutive
 * days in one tier, share segments and their text, and the chart totals.
 */
class TierHistoryPresenterTest extends TestCase
{
    private function _rows(array $valuesByDate): array
    {
        return array_map(fn ($v) => ['v' => $v, 'b' => 'raw_return', 'o' => true], $valuesByDate);
    }

    public function testRunsGroupConsecutiveDaysInTheSameTier(): void
    {
        $runs = (new TierHistoryPresenter())->runs([
            '2026-01-05' => T::SILVER,
            '2026-01-06' => T::SILVER,
            '2026-01-07' => T::GOLD,
            '2026-01-08' => T::SILVER,
        ]);

        $this->assertSame([
            ['tier' => T::SILVER, 'from' => '2026-01-05', 'to' => '2026-01-06', 'days' => 2],
            ['tier' => T::GOLD, 'from' => '2026-01-07', 'to' => '2026-01-07', 'days' => 1],
            ['tier' => T::SILVER, 'from' => '2026-01-08', 'to' => '2026-01-08', 'days' => 1],
        ], $runs);
    }

    public function testForRowBuildsSegmentsTextAndRibbon(): void
    {
        $rows = [];
        $day  = Carbon::parse('2026-01-05');
        for ($i = 0; $i < 100; $i++, $day->addWeekday()) {
            $rows[$day->format('Y-m-d')] = $i < 80 ? 7.0 : 12.0; // 80 Silver days, then 20 Gold days
        }
        $rows    = $this->_rows($rows);
        $settled = (new SettledTierResolver())->resolve('TST.A', T::SILVER, $rows, true);
        $view    = (new TierHistoryPresenter())->forRow('TST.A', $rows, $settled, Carbon::parse('2026-06-01'));

        $this->assertSame('G 20% · S 80%', $view['shares_text']);
        $this->assertSame([T::GOLD, T::SILVER], array_column($view['segments'], 'tier'));
        $this->assertCount(2, $view['ribbon']);
        $this->assertSame(T::SILVER, $view['ribbon'][0]['tier']);
        $this->assertEqualsWithDelta(80.0, $view['ribbon'][0]['width_pct'], 1e-9);
        $this->assertStringContainsString('80 trading days', $view['ribbon'][0]['title']);
        // History shorter than 12 months is labelled with its start.
        $this->assertStringStartsWith('(since 2026-01-05', $view['period']);
        $this->assertSame(7.0, $view['settled_value']);
        $this->assertNull($view['split'], 'Silver held on 80% of the days.');
        $this->assertStringContainsString('G 20% · S 80%. Click for the chart.', $view['ribbon_note']);
    }

    public function testSplitYearWhenTheSettledTierWasRarelyHeld(): void
    {
        $rows = [];
        $day  = Carbon::parse('2025-06-02');
        for ($i = 0; $i < 100; $i++, $day->addWeekday()) {
            // 45 Platinum days, 6 Bronze days, then 49 Rust days: the median lands in Bronze.
            $rows[$day->format('Y-m-d')] = $i < 45 ? 20.0 : ($i < 51 ? 2.0 : -5.0);
        }
        $rows    = $this->_rows($rows);
        $settled = (new SettledTierResolver())->resolve('TST.A', T::RUST, $rows, true);
        $view    = (new TierHistoryPresenter(15.0))->forRow('TST.A', $rows, $settled, Carbon::parse('2026-06-01'));

        $this->assertSame(T::BRONZE, $settled->settledTier);
        $this->assertSame('split year', $view['split']['label']);
        $this->assertSame('Bronze is the middle of a split year: Platinum on 45% of the days, Rust on 49%; Bronze'
            . ' itself on only 6%.', $view['split']['title']);
        $this->assertNull((new TierHistoryPresenter(5.0))->forRow('TST.A', $rows, $settled,
            Carbon::parse('2026-06-01'))['split'], 'Under a 5% threshold, 6% is enough.');
    }

    public function testForRowWithoutHistoryIsNull(): void
    {
        $settled = (new SettledTierResolver())->resolve('TST.A', T::SILVER, [], true);

        $this->assertNull((new TierHistoryPresenter())->forRow('TST.A', [], $settled, Carbon::parse('2026-06-01')));
    }

    public function testForChartTotalsRunsAndWindowLabels(): void
    {
        // A close every calendar day of 2024 at 100 and of 2025 at 120: each 2025 day's 1Y return is
        // measured between a 2024 close (100) and that day's close (120).
        $prices = [];
        for ($d = Carbon::parse('2024-01-01'); $d->lte(Carbon::parse('2026-05-04')); $d->addDay()) {
            $prices[$d->format('Y-m-d')] = $d->year === 2024 ? 100.0 : 120.0;
        }
        $rows  = $this->_rows(['2025-01-02' => 12.0, '2026-05-01' => 7.0, '2026-05-04' => 7.0]);
        $chart = (new TierHistoryPresenter())->forChart(
            'TST.A', $rows, ['prices' => $prices, 'currency' => '$'], Carbon::parse('2026-06-01'), 365
        );

        $this->assertCount(3, $chart['points']);
        $this->assertSame(T::GOLD, $chart['points'][0]['tier']);
        // Totals over everything, and over the last 12 months only (the Gold day is older).
        $this->assertSame([T::GOLD, T::SILVER], array_column($chart['totals_all'], 'tier'));
        $this->assertSame([T::SILVER], array_column($chart['totals_recent'], 'tier'));
        // No trades passed: every day has an empty trade list.
        $this->assertSame([], $chart['points'][0]['trades']);
        // The two closes behind the first day's 1Y return, with dates and currency.
        $this->assertSame('03 Jan 2024: 100.00$', $chart['points'][0]['window']['ago']);
        $this->assertSame('02 Jan 2025: 120.00$', $chart['points'][0]['window']['now']);
    }

    public function testTradesHoverLinesAndHoldingPeriods(): void
    {
        // Not held on 30 Apr; a holding opened on 1 May (+5 %, then +9 %).
        $rows = [
            '2026-04-30' => ['v' => 7.0, 'b' => 'x', 'o' => false, 'h' => null, 's' => null],
            '2026-05-01' => ['v' => 7.0, 'b' => 'x', 'o' => true, 'h' => 5.0, 's' => '2026-05-01'],
            '2026-05-04' => ['v' => 7.0, 'b' => 'x', 'o' => true, 'h' => 9.0, 's' => '2026-05-01'],
        ];
        $buy2019 = ['date' => '2019-03-04', 'action' => 'BUY', 'quantity' => 10.0, 'price' => 50.0];
        $sell2019 = ['date' => '2019-06-03', 'action' => 'SELL', 'quantity' => 10.0, 'price' => 60.0];
        $buy2026 = ['date' => '2026-05-01', 'action' => 'BUY', 'quantity' => 25.0, 'price' => 160.0];
        $trades  = array_map(fn ($t) => $t + ['iso' => 'USD', 'code' => '$'], [
            $buy2019, $sell2019, $buy2026,
            // Saturday: shown on the Friday point, where the S marker snaps too.
            ['date' => '2026-05-02', 'action' => 'SELL', 'quantity' => 5.0, 'price' => 175.0],
        ]);
        $windows = [
            ['from' => '2019-03-04', 'to' => '2019-06-03', 'open' => false, 'days' => 91, 'invested' => 500.0,
                'return_pct' => 20.0, 'trades' => [$buy2019, $sell2019]],
            ['from' => '2026-05-01', 'to' => null, 'open' => true, 'days' => 3, 'invested' => 4000.0,
                'return_pct' => 9.0, 'trades' => [$buy2026]],
        ];
        // VUSA in the stock's currency: +10 % over the 2019 period, +2 % then +4 % in 2026.
        $benchmark = ['2019-03-04' => 100.0, '2019-06-03' => 110.0, '2026-05-01' => 200.0, '2026-05-04' => 208.0];
        $quotes    = ['prices' => ['2026-04-30' => 158.0, '2026-05-01' => 170.0, '2026-05-04' => 180.0],
            'currency' => '$', 'iso' => 'USD'];
        $chart     = (new TierHistoryPresenter())->forChart('TST.A', $rows, $quotes, Carbon::parse('2026-06-01'), 365, [
            'trades' => $trades, 'windows' => $windows, 'lifetime_pct' => 12.0, 'benchmark' => $benchmark,
        ]);

        // 1 May: its own buy, plus the Saturday sell moved onto it.
        $this->assertSame([
            ['buy' => true, 'text' => 'Buy 25 @ 160.00$'],
            ['buy' => false, 'text' => 'Sell 5 @ 175.00$'],
        ], $chart['points'][1]['trades']);

        $points = $chart['position']['points'];
        // A gap where no shares were held, then your return against the same trades in VUSA, both tiered.
        $this->assertNull($points[0]['value']);
        $this->assertSame(9.0, $points[2]['value']);
        $this->assertSame('Silver', $points[2]['label']);
        $this->assertSame(4.0, $points[2]['bench_value']);
        $this->assertSame('Bronze', $points[2]['bench_label']);
        $this->assertSame('01 May 2026', $points[2]['since_label']);

        $periods = $chart['position']['periods'];
        $this->assertSame('+20.00%', $periods[0]['yours']);
        $this->assertSame('+10.00%', $periods[0]['bench']);
        $this->assertSame('+10.00 pts', $periods[0]['diff']);
        $this->assertTrue($periods[0]['diff_positive']);
        $this->assertSame('still held', $periods[1]['to']);
        $this->assertSame('04 Mar 2019', $chart['position']['total']['from']);
    }
}
