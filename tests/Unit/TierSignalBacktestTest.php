<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierSignalBacktest;
use PHPUnit\Framework\TestCase;

/**
 * Signal backtest core: daily signals without look-ahead, episodes, forward returns against the benchmark, hit rates
 * and the baseline.
 */
class TierSignalBacktestTest extends TestCase
{
    /**
     * $days weekdays from 2025-01-06 of one symbol: a 1Y market reading of 2 % (Bronze) every day, then $spike
     * (Gold) from day $spikeFrom on. Market basis, held.
     */
    private function history(int $days, int $spikeFrom, float $spike = 12.0): array
    {
        $rows = [];
        $day  = strtotime('2025-01-06');
        for ($i = 0; $i < $days; ) {
            if ((int) date('N', $day) <= 5) {
                $v = $i >= $spikeFrom ? $spike : 2.0;
                $rows[date('Y-m-d', $day)] = ['v' => $v, 'm' => $v, 'w' => null, 'b' => 'market_momentum', 'o' => true];
                $i++;
            }
            $day += 86400;
        }

        return ['TST.AAA' => $rows];
    }

    /**
     * Daily closes on the history's dates: the symbol from 100 by $step a day, the benchmark flat at 50.
     */
    private function prices(array $history, float $step): array
    {
        $prices = [];
        $bench  = [];
        $p      = 100.0;
        foreach (array_keys($history['TST.AAA']) as $date) {
            $prices['TST.AAA'][$date] = $p;
            $bench[$date] = 50.0;
            $p += $step;
        }

        return [$prices, $bench];
    }

    public function testARareHighIsOneEpisodeJudgedFromItsFirstDay(): void
    {
        $history = $this->history(100, 80);
        [$prices, $bench] = $this->prices($history, -0.5);
        $run = (new TierSignalBacktest(new SettledTierResolver(50.0, 60, 20.0, 20), [5, 50]))
            ->run($history, $prices, $bench);

        $high = array_values(array_filter($run['episodes'], fn ($e) => $e['signal'] === 'RARE_HIGH'));
        $this->assertCount(1, $high, 'one rare-high run');
        $this->assertSame('SELL', $high[0]['action']);
        $first = array_keys($history['TST.AAA'])[80];
        $this->assertSame($first, $high[0]['start']);
        $this->assertGreaterThan(1, $high[0]['days']);

        // From its first day (close 60) the symbol falls 0.5 a day against a flat benchmark: -2.5 over 5 days =
        // -4.17 points, a hit for a sell; 50 days later is past the data, so no figure.
        $this->assertEqualsWithDelta(-2.5 / 60.0 * 100.0, $high[0]['forward'][5], 0.001);
        $this->assertNull($high[0]['forward'][50]);
        $summary = $run['summary']['RARE_HIGH|all']['horizons'][5];
        $this->assertSame(1, $summary['n']);
        $this->assertSame(100.0, $summary['hit_rate']);
    }

    public function testNoDayIsResolvedBeforeItsHistoryIsLongEnough(): void
    {
        $history = $this->history(70, 200);
        [$prices, $bench] = $this->prices($history, 0.0);
        $run = (new TierSignalBacktest(new SettledTierResolver(50.0, 60, 20.0, 20), [5]))
            ->run($history, $prices, $bench);

        $this->assertSame(10, $run['days'], 'days 60 to 69 only');
        $this->assertSame([], $run['episodes']);
        $this->assertEqualsWithDelta(0.0, $run['baseline'][5]['avg_rel'], 0.0001);
    }

    public function testHitDirectionAndMedian(): void
    {
        $this->assertTrue(TierSignalBacktest::isHit('RARE_HIGH', -1.0));
        $this->assertTrue(TierSignalBacktest::isHit('DETERIORATING', -0.1));
        $this->assertFalse(TierSignalBacktest::isHit('RARE_LOW', -1.0));
        $this->assertTrue(TierSignalBacktest::isHit('RARE_LOW', 2.0));
        $this->assertSame(2.5, TierSignalBacktest::median([4, 1, 3, 2]));
        $this->assertNull(TierSignalBacktest::median([]));
    }
}
