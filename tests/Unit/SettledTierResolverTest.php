<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierCalculationService as T;
use PHPUnit\Framework\TestCase;

/**
 * Locks down the settled-tier and tier-signal rules (time in tier, consistency percentile, rare high,
 * rare low, deteriorating). Pure: the lookback is a date => basis value map.
 */
class SettledTierResolverTest extends TestCase
{
    // Representative basis values per tier (Rust < 0 <= Bronze <= 5 < Silver <= 10 < Gold <= 15 < Platinum).
    private const VALUE = [
        T::PLATINUM => 20.0,
        T::GOLD     => 12.0,
        T::SILVER   => 7.0,
        T::BRONZE   => 2.0,
        T::RUST     => -5.0,
    ];

    private function _resolver(float $consistency = 50.0): SettledTierResolver
    {
        return new SettledTierResolver($consistency, 60, 20.0, 20, [T::GOLD, T::PLATINUM]);
    }

    /**
     * Weekday-dated lookback from a list of [tier, days] blocks (or [value, days] for an exact reading),
     * oldest first, ending the day before 2026-06-01.
     */
    private function _lookback(array $blocks): array
    {
        $values = [];
        foreach ($blocks as [$tier, $days]) {
            for ($i = 0; $i < $days; $i++) {
                $values[] = is_string($tier) ? self::VALUE[$tier] : (float) $tier;
            }
        }
        $day    = Carbon::parse('2026-06-01');
        $result = [];
        foreach (array_reverse($values) as $value) {
            do {
                $day->subDay();
            } while ($day->isWeekend());
            $result[$day->format('Y-m-d')] = $value;
        }
        ksort($result);

        return $result;
    }

    public function testSharesSumToHundredAndTierOrder(): void
    {
        $r = $this->_resolver()->resolve('TST.A', T::SILVER, $this->_lookback([
            [T::GOLD, 30], [T::SILVER, 60], [T::BRONZE, 10],
        ]), true);

        $this->assertSame(T::TIERS, array_keys($r->shares));
        $this->assertEqualsWithDelta(100.0, array_sum($r->shares), 1e-9);
        $this->assertEqualsWithDelta(30.0, $r->shares[T::GOLD], 1e-9);
        $this->assertEqualsWithDelta(60.0, $r->shares[T::SILVER], 1e-9);
        $this->assertSame(100, $r->days);
    }

    public function testMedianIsTheSettledTier(): void
    {
        // 45 Gold days and 55 Silver days: Silver or better on 100 %, Gold or better on 45 %.
        $r = $this->_resolver()->resolve('TST.A', T::SILVER, $this->_lookback([
            [T::GOLD, 45], [T::SILVER, 55],
        ]), true);

        $this->assertSame(SettledTierResolver::STATUS_OK, $r->status);
        $this->assertSame(T::SILVER, $r->settledTier);
        $this->assertStringContainsString('Silver or better on 100%', $r->explanation);
    }

    public function testHigherConsistencyIsStricter(): void
    {
        // 55 Gold, 45 Silver: median is Gold, but Gold holds on only 55 % < 60 %.
        $lookback = $this->_lookback([[T::SILVER, 45], [T::GOLD, 55]]);

        $this->assertSame(T::GOLD, $this->_resolver(50.0)->resolve('TST.A', T::GOLD, $lookback, true)->settledTier);
        $this->assertSame(T::SILVER, $this->_resolver(60.0)->resolve('TST.A', T::GOLD, $lookback, true)->settledTier);
    }

    public function testInsufficientHistoryHasNoSettledTierAndNoSignal(): void
    {
        $r = $this->_resolver()->resolve('TST.A', T::PLATINUM, $this->_lookback([[T::SILVER, 59]]), true);

        $this->assertSame(SettledTierResolver::STATUS_INSUFFICIENT, $r->status);
        $this->assertNull($r->settledTier);
        $this->assertNull($r->signal);
        $this->assertStringContainsString('59 days', $r->explanation);
    }

    public function testRareHighOnOwnedIsSellBelowGold(): void
    {
        // Settled Silver, Gold or better on 10 % of days: today Gold is a rare high.
        $r = $this->_resolver()->resolve('TST.A', T::GOLD, $this->_lookback([
            [T::SILVER, 90], [T::GOLD, 10],
        ]), true);

        $this->assertSame(SettledTierResolver::SIGNAL_RARE_HIGH, $r->signal);
        $this->assertSame(SettledTierResolver::ACTION_SELL, $r->action);
        $this->assertTrue($r->alertable);
        $this->assertEqualsWithDelta(10.0, $r->atOrAboveTodayPct, 1e-9);
    }

    public function testRareHighOnSettledGoldIsTrim(): void
    {
        $r = $this->_resolver()->resolve('TST.A', T::PLATINUM, $this->_lookback([
            [T::GOLD, 95], [T::PLATINUM, 5],
        ]), true);

        $this->assertSame(SettledTierResolver::SIGNAL_RARE_HIGH, $r->signal);
        $this->assertSame(SettledTierResolver::ACTION_TRIM, $r->action);
    }

    public function testRareHighOnWatchlistIsChipOnly(): void
    {
        $r = $this->_resolver()->resolve('TST.A', T::GOLD, $this->_lookback([[T::SILVER, 100]]), false);

        $this->assertSame(SettledTierResolver::SIGNAL_RARE_HIGH, $r->signal);
        $this->assertNull($r->action);
        $this->assertFalse($r->alertable);
    }

    public function testCommonHighIsNoSignal(): void
    {
        // Gold or better on 40 % of days is not rare.
        $r = $this->_resolver()->resolve('TST.A', T::GOLD, $this->_lookback([
            [T::SILVER, 60], [T::GOLD, 40],
        ]), true);

        $this->assertNull($r->signal);
    }

    public function testRareLowOnStrongNameIsBuyOrAdd(): void
    {
        $lookback = $this->_lookback([[T::GOLD, 95], [T::SILVER, 5]]);

        $owned = $this->_resolver()->resolve('TST.A', T::SILVER, $lookback, true);
        $this->assertSame(SettledTierResolver::SIGNAL_RARE_LOW, $owned->signal);
        $this->assertSame(SettledTierResolver::ACTION_ADD, $owned->action);

        $watch = $this->_resolver()->resolve('TST.A', T::SILVER, $lookback, false);
        $this->assertSame(SettledTierResolver::ACTION_BUY, $watch->action);
        $this->assertTrue($watch->alertable);
    }

    public function testRareLowOnWeakSettledTierIsNotABuy(): void
    {
        // Settled Silver dipping to Bronze: falling-knife risk, no buy.
        $r = $this->_resolver()->resolve('TST.A', T::BRONZE, $this->_lookback([
            [T::SILVER, 95], [T::BRONZE, 5],
        ]), true);

        $this->assertNull($r->signal);
    }

    public function testTwoTierDropIsRareLowBeforeItPersists(): void
    {
        // 18 Bronze days + today = 19 weekdays two tiers below settled Gold: still a rare low (buy).
        $r = $this->_resolver()->resolve('TST.A', T::BRONZE, $this->_lookback([
            [T::GOLD, 182], [T::BRONZE, 18],
        ]), true);

        $this->assertSame(19, $r->deterioratingDays);
        $this->assertSame(SettledTierResolver::SIGNAL_RARE_LOW, $r->signal);
        $this->assertSame(T::GOLD, $r->settledTier);
    }

    public function testDeterioratingAfterPersistDaysAndSettledFollowsToday(): void
    {
        // 19 Bronze days + today = 20 weekdays: deteriorating, settled follows today.
        $r = $this->_resolver()->resolve('TST.A', T::BRONZE, $this->_lookback([
            [T::GOLD, 181], [T::BRONZE, 19],
        ]), true);

        $this->assertSame(20, $r->deterioratingDays);
        $this->assertSame(SettledTierResolver::SIGNAL_DETERIORATING, $r->signal);
        $this->assertSame(SettledTierResolver::STATUS_DETERIORATING, $r->status);
        $this->assertSame(SettledTierResolver::ACTION_REVIEW, $r->action);
        $this->assertSame(T::BRONZE, $r->settledTier);
        $this->assertSame(T::GOLD, $r->dropFrom);
        $this->assertSame(T::GOLD, $r->toArray()['drop_from']);
        $this->assertSame(
            'Two tiers below its settled Gold tier for 20 trading days; the tier now follows today.', $r->explanation
        );
    }

    public function testADeeperDropNamesHowFarTodayIsDown(): void
    {
        // Settled Platinum, today Rust: four tiers below today, the 20 days counted at two or more below.
        $r = $this->_resolver()->resolve('TST.A', T::RUST, $this->_lookback([
            [T::PLATINUM, 181], [T::SILVER, 10], [T::RUST, 9],
        ]), true);

        $this->assertSame(SettledTierResolver::STATUS_DETERIORATING, $r->status);
        $this->assertSame(T::PLATINUM, $r->dropFrom);
        $this->assertSame('Four tiers below its settled Platinum tier today (two or more for 20 trading days); the'
            . ' tier now follows today.', $r->explanation);
    }

    public function testSettledBronzeFollowsADeepDropAfterPersistDays(): void
    {
        // Settled Bronze (+2 %), then 19 days at -40 % and today -52 %: at or under -5 %, so it follows today.
        $r = $this->_resolver()->resolve('TST.A', T::RUST, $this->_lookback([
            [2.0, 181], [-40.0, 19],
        ]), true, null, null, -52.0);

        $this->assertSame(20, $r->deterioratingDays);
        $this->assertSame(SettledTierResolver::STATUS_DETERIORATING, $r->status);
        $this->assertSame(T::RUST, $r->settledTier);
        $this->assertStringStartsWith('At or under -5%', $r->explanation);
    }

    public function testSettledBronzeIgnoresAShallowRust(): void
    {
        // Rust, but only -3 %: not two steps below Bronze, so no drop is counted.
        $r = $this->_resolver()->resolve('TST.A', T::RUST, $this->_lookback([
            [2.0, 170], [-3.0, 30],
        ]), true, null, null, -3.0);

        $this->assertSame(0, $r->deterioratingDays);
        $this->assertSame(T::BRONZE, $r->settledTier);
    }

    public function testSettledBronzeNeedsTodaysValueForADeepDrop(): void
    {
        // Without today's reading the deep step cannot be told apart from a shallow Rust: no drop.
        $r = $this->_resolver()->resolve('TST.A', T::RUST, $this->_lookback([
            [2.0, 181], [-40.0, 19],
        ]), true);

        $this->assertSame(0, $r->deterioratingDays);
        $this->assertSame(T::BRONZE, $r->settledTier);
    }

    public function testDeterioratingOnWatchlistIsChipOnly(): void
    {
        $r = $this->_resolver()->resolve('TST.A', T::BRONZE, $this->_lookback([
            [T::GOLD, 170], [T::BRONZE, 30],
        ]), false);

        $this->assertSame(SettledTierResolver::SIGNAL_DETERIORATING, $r->signal);
        $this->assertNull($r->action);
        $this->assertFalse($r->alertable);
    }

    public function testBenchmarkIsPinnedGoldWithoutSignal(): void
    {
        $r = $this->_resolver()->resolve(T::BENCHMARK_SYMBOL, T::GOLD, $this->_lookback([
            [T::PLATINUM, 100],
        ]), true);

        $this->assertSame(T::GOLD, $r->settledTier);
        $this->assertNull($r->signal);
        $this->assertEqualsWithDelta(100.0, $r->shares[T::GOLD], 1e-9);
        // The tier is pinned, but the median reading is still reported.
        $this->assertSame(self::VALUE[T::PLATINUM], $r->settledValue);
    }

    public function testRowsOnOrAfterTheDayAreIgnored(): void
    {
        $lookback = $this->_lookback([[T::SILVER, 100]]);
        // Rows dated on and after D must not count (no look-ahead).
        $lookback['2026-06-01'] = self::VALUE[T::PLATINUM];
        $lookback['2026-06-02'] = self::VALUE[T::PLATINUM];

        $r = $this->_resolver()->resolve('TST.A', T::SILVER, $lookback, true, '2026-06-01');

        $this->assertSame(100, $r->days);
        $this->assertEqualsWithDelta(0.0, $r->shares[T::PLATINUM], 1e-9);
    }

    public function testRepositoryRowsAndOwnedSince(): void
    {
        $lookback = [];
        foreach ($this->_lookback([[T::SILVER, 100]]) as $date => $value) {
            $lookback[$date] = ['v' => $value, 'b' => 'raw_return', 'o' => $date >= '2026-04-01'];
        }

        $r = $this->_resolver()->resolve('TST.A', T::SILVER, $lookback, true);

        $this->assertSame(T::SILVER, $r->settledTier);
        $this->assertSame('2026-04-01', $r->ownedSince);
    }

    public function testNoTodayTierStillSettles(): void
    {
        $r = $this->_resolver()->resolve('TST.A', null, $this->_lookback([[T::GOLD, 80]]), false);

        $this->assertSame(T::GOLD, $r->settledTier);
        $this->assertNull($r->signal);
        $this->assertNull($r->atOrAboveTodayPct);
    }

    /**
     * Repository-shaped rows: the deciding value v, the market value m and the own value w per day.
     */
    private function _rows(array $blocks): array
    {
        $rows = [];
        $day  = Carbon::parse('2026-06-01');
        $flat = [];
        foreach ($blocks as [$v, $m, $w, $days]) {
            for ($i = 0; $i < $days; $i++) {
                $flat[] = ['v' => $v, 'm' => $m, 'w' => $w, 'b' => 'x', 'o' => $w !== null];
            }
        }
        foreach (array_reverse($flat) as $row) {
            do {
                $day->subDay();
            } while ($day->isWeekend());
            $rows[$day->format('Y-m-d')] = $row;
        }
        ksort($rows);

        return $rows;
    }

    public function testOwnMeasureComparesOwnReturnsOnly(): void
    {
        // 150 days watchlist-only (market Platinum decided), then 100 days held (own Silver decided).
        // Each day also carries the own figure (realized Silver earlier), so the own history is all Silver.
        $rows = $this->_rows([
            [self::VALUE[T::PLATINUM], self::VALUE[T::PLATINUM], self::VALUE[T::SILVER], 150],
            [self::VALUE[T::SILVER], self::VALUE[T::PLATINUM], self::VALUE[T::SILVER], 100],
        ]);

        $mixed = $this->_resolver()->resolve('TST.A', T::SILVER, $rows, true);
        $own   = $this->_resolver()->resolve('TST.A', T::SILVER, $rows, true, null, SettledTierResolver::MEASURE_OWN);

        // Mixing the measures reads the market Platinum days as this holding's norm, so today's Silver
        // looks like a two-tier collapse: a false DETERIORATING alarm.
        $this->assertSame(SettledTierResolver::SIGNAL_DETERIORATING, $mixed->signal);
        $this->assertSame(T::SILVER, $own->settledTier);
        $this->assertNull($own->signal);
        $this->assertSame(SettledTierResolver::MEASURE_OWN, $own->measure);
        $this->assertEqualsWithDelta(100.0, $own->shares[T::SILVER], 1e-9);
        $this->assertStringContainsString('on your own return', $own->explanation);
    }

    public function testMarketMeasureReadsTheMarketValues(): void
    {
        $rows = $this->_rows([[self::VALUE[T::SILVER], self::VALUE[T::GOLD], null, 100]]);

        $r = $this->_resolver()->resolve('TST.A', T::GOLD, $rows, false, null, SettledTierResolver::MEASURE_MARKET);

        $this->assertSame(T::GOLD, $r->settledTier);
        $this->assertNull($r->signal);
    }

    public function testRecentPurchaseFallsBackToMarketWithoutSignals(): void
    {
        // Only 30 days of own return: settle on the market history, and raise no signal (today's own
        // number and the market history are different measures).
        $rows = $this->_rows([
            [self::VALUE[T::SILVER], self::VALUE[T::SILVER], null, 170],
            [self::VALUE[T::SILVER], self::VALUE[T::SILVER], self::VALUE[T::SILVER], 30],
        ]);

        $r = $this->_resolver()->resolve('TST.A', T::PLATINUM, $rows, true, null, SettledTierResolver::MEASURE_OWN);

        $this->assertTrue($r->measureFallback);
        $this->assertSame(SettledTierResolver::MEASURE_MARKET, $r->measure);
        $this->assertSame(T::SILVER, $r->settledTier);
        $this->assertNull($r->signal);
        $this->assertStringContainsString('too few days of your own return', $r->explanation);
    }

    public function testDropIsMeasuredFromTheTierBeforeItNotFromTheSinkingMedian(): void
    {
        // Platinum, then Gold, then a collapse through Silver into deep Rust. The drop's own days pull today's median
        // down to Gold, so against that median only the 12 Rust days count (a "rare low" ADD on a Gold name). Against
        // the tier before the drop (Platinum) the Silver days count too: 27 days plus today, a lasting drop.
        $lookback = $this->_lookback([[T::PLATINUM, 70], [T::GOLD, 60], [T::SILVER, 15], [-25.0, 12]]);

        $r = $this->_resolver()->resolve('TST.A', T::RUST, $lookback, true, null, null, -25.0);

        $this->assertSame(SettledTierResolver::STATUS_DETERIORATING, $r->status);
        $this->assertSame(SettledTierResolver::SIGNAL_DETERIORATING, $r->signal);
        $this->assertSame(SettledTierResolver::ACTION_REVIEW, $r->action);
        $this->assertSame(T::RUST, $r->settledTier, 'the tier follows today');
        $this->assertSame(T::PLATINUM, $r->dropFrom);
        $this->assertSame(28, $r->deterioratingDays);
    }

    public function testALongDropEndsOnlyWhenItHasBecomeTheNorm(): void
    {
        // 70 Platinum days are still enough to settle on before the drop: still deteriorating from Platinum.
        $kept = $this->_resolver()->resolve(
            'TST.A', T::RUST, $this->_lookback([[T::PLATINUM, 70], [-25.0, 200]]), true, null, null, -25.0
        );
        $this->assertSame(SettledTierResolver::STATUS_DETERIORATING, $kept->status);
        $this->assertSame(T::PLATINUM, $kept->dropFrom);
        $this->assertSame(201, $kept->deterioratingDays);

        // Under 60 days before it: the drop is the norm, settled Rust like today, no signal.
        $norm = $this->_resolver()->resolve(
            'TST.A', T::RUST, $this->_lookback([[T::PLATINUM, 50], [-25.0, 200]]), true, null, null, -25.0
        );
        $this->assertSame(SettledTierResolver::STATUS_OK, $norm->status);
        $this->assertSame(T::RUST, $norm->settledTier);
        $this->assertNull($norm->signal);
    }
}
