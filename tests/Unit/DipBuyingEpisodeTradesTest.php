<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

use ovidiuro\myfinance2\App\Services\DipBuyingBacktestService;

/**
 * The current dip's trades list (actual.flows.trades) the Dip Buying email shows: the episode's buys and sells merged
 * newest first, same-day trades latest first, each tagged with its action; a buy keeps its entry drawdown, a sell has
 * none. Pure: the private static builder through reflection, no DB.
 */
class DipBuyingEpisodeTradesTest extends TestCase
{
    private static function _episodeTrades(array $buys, array $sells): array
    {
        $method = new ReflectionMethod(DipBuyingBacktestService::class, '_episodeTrades');

        return $method->invoke(null, $buys, $sells);
    }

    private static function _row(string $date, string $symbol, float $dd = 0.0): array
    {
        return [
            'date' => $date, 'eur' => 1000.456, 'dd' => $dd, 'symbol' => $symbol,
            'quantity' => 5.0, 'unit_price' => 200.0, 'currency' => '&euro;',
        ];
    }

    public function test_merges_buys_and_sells_newest_first(): void
    {
        $trades = self::_episodeTrades(
            [self::_row('2026-09-01', 'AAA', 4.26), self::_row('2026-10-02', 'BBB', 9.0),
             self::_row('2026-10-02', 'CCC', 9.0)],
            [self::_row('2026-09-15', 'DDD')]
        );

        $this->assertSame(['CCC', 'BBB', 'DDD', 'AAA'], array_column($trades, 'symbol'));
        $this->assertSame(['BUY', 'BUY', 'SELL', 'BUY'], array_column($trades, 'action'));
        $this->assertSame([9.0, 9.0, null, 4.3], array_column($trades, 'dd'));
        $this->assertSame(1000.46, $trades[0]['eur']);
        $this->assertSame(5.0, $trades[0]['quantity']);
        $this->assertSame('&euro;', $trades[0]['currency']);
    }

    /**
     * Same-day trades by their time, whichever side: a morning sell lists below the afternoon buy.
     */
    public function test_same_day_trades_are_ordered_by_time(): void
    {
        $at = fn (array $row, string $time): array => ['time' => $row['date'] . ' ' . $time] + $row;

        $trades = self::_episodeTrades(
            [$at(self::_row('2026-10-02', 'BUY_PM', 9.0), '16:00:00'),
             $at(self::_row('2026-10-02', 'BUY_AM', 9.0), '08:30:00')],
            [$at(self::_row('2026-10-02', 'SELL_MID'), '10:15:00')]
        );

        $this->assertSame(['BUY_PM', 'SELL_MID', 'BUY_AM'], array_column($trades, 'symbol'));
        $this->assertSame('2026-10-02 16:00:00', $trades[0]['time']);
    }

    public function test_no_trades_is_an_empty_list(): void
    {
        $this->assertSame([], self::_episodeTrades([], []));
    }
}
