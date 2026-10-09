<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\LiveOverviewSeries;

/**
 * Unit tests for the pure parts of LiveOverviewSeries: pinning today's point, the live account
 * and User Overview figures, and the EUR/USD conversion factor. No storage access.
 */
class LiveOverviewSeriesTest extends TestCase
{
    private function _account(string $currency, float $cash, float $cost, float $mvalue): array
    {
        return [
            'accountModel' => (object) ['currency' => (object) ['iso_code' => $currency]],
            'total_cost' => $cost,
            'total_market_value' => $mvalue,
            'total_change' => $mvalue - $cost,
            'cashBalanceUtils' => new class ($cash) {
                public function __construct(private float $_cash)
                {
                }

                public function getLastCashBalance(): object
                {
                    return (object) ['amount' => $this->_cash];
                }
            },
        ];
    }

    public function test_pin_replaces_todays_point(): void
    {
        $series = [['time' => '2026-10-08', 'value' => 1.0], ['time' => '2026-10-09', 'value' => 2.0]];

        $pinned = LiveOverviewSeries::pin($series, 3.5, '2026-10-09');

        $this->assertCount(2, $pinned);
        $this->assertSame(1.0, $pinned[0]['value']);
        $this->assertSame(['time' => '2026-10-09', 'value' => 3.5], $pinned[1]);
    }

    public function test_pin_appends_today_when_the_series_ends_earlier(): void
    {
        $series = [['time' => '2026-10-08', 'value' => 1.0]];

        $pinned = LiveOverviewSeries::pin($series, 3.5, '2026-10-09');

        $this->assertCount(2, $pinned);
        $this->assertSame(['time' => '2026-10-09', 'value' => 3.5], $pinned[1]);
        $this->assertSame([['time' => '2026-10-09', 'value' => 3.5]],
            LiveOverviewSeries::pin([], 3.5, '2026-10-09'));
    }

    public function test_account_live_values_come_from_the_totals(): void
    {
        $live = LiveOverviewSeries::accountLiveValues($this->_account('USD', 50.0, 800.0, 1000.0));

        $this->assertSame(50.0, $live['cash']);
        $this->assertSame(800.0, $live['cost']);
        $this->assertSame(1000.0, $live['mvalue']);
        $this->assertSame(200.0, $live['change']);
        $this->assertEqualsWithDelta(25.0, $live['changePercentage'], 0.0001);
    }

    /**
     * EURUSD 1.25: the EUR view divides the USD account by the rate, the USD view multiplies
     * the EUR account by it; change % follows the converted totals.
     */
    public function test_user_live_values_convert_and_sum_both_currencies(): void
    {
        $accountData = [
            1 => $this->_account('EUR', 100.0, 800.0, 1000.0),
            2 => $this->_account('USD', 1000.0, 400.0, 500.0),
        ];
        $liveByAccount = [
            1 => LiveOverviewSeries::accountLiveValues($accountData[1]),
            2 => LiveOverviewSeries::accountLiveValues($accountData[2]),
        ];

        $user = LiveOverviewSeries::userLiveValues($liveByAccount, $accountData, 1.25);

        $this->assertEqualsWithDelta(1400.0, $user['mvalue_EUR'], 0.0001);
        $this->assertEqualsWithDelta(1120.0, $user['cost_EUR'], 0.0001);
        $this->assertEqualsWithDelta(280.0, $user['change_EUR'], 0.0001);
        $this->assertEqualsWithDelta(900.0, $user['cash_EUR'], 0.0001);
        $this->assertEqualsWithDelta(25.0, $user['changePercentage_EUR'], 0.0001);
        $this->assertEqualsWithDelta(1750.0, $user['mvalue_USD'], 0.0001);
        $this->assertEqualsWithDelta(1125.0, $user['cash_USD'], 0.0001);
    }

    public function test_fx_factor_maps_currencies(): void
    {
        $this->assertSame(1.0, LiveOverviewSeries::fxFactor('EUR', 'EUR', 1.25));
        $this->assertSame(0.8, LiveOverviewSeries::fxFactor('USD', 'EUR', 1.25));
        $this->assertSame(1.25, LiveOverviewSeries::fxFactor('EUR', 'USD', 1.25));
        $this->assertNull(LiveOverviewSeries::fxFactor('GBP', 'EUR', 1.25));
    }
}
