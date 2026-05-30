<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\PeakExitPnlBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

/**
 * Locks down the pure "P&L if sold at this window's peak" math that the quadrant per-period
 * table shows next to "From peak". Valuing the held shares at the window peak (recovered from
 * the proximity_pct that "From peak" reports) and comparing against their true cost is all pure
 * arithmetic, so no database is needed.
 */
class PeakExitPnlBuilderTest extends TestCase
{
    private PeakExitPnlBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PeakExitPnlBuilder();
    }

    private function _invoke(string $method, array $args): mixed
    {
        $m = new ReflectionMethod(PeakExitPnlBuilder::class, $method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->builder, $args);
    }

    private function _position(string $currency, float $mvalue, float $cost2): array
    {
        $account           = new stdClass();
        $account->currency = new stdClass();
        $account->currency->iso_code = $currency;

        return [
            'accountModel'                     => $account,
            'market_value_in_account_currency' => $mvalue,
            'cost2_in_account_currency'        => $cost2,
        ];
    }

    // ---- _pnlAtPeak ---------------------------------------------------------

    public function testPnlAtPeakNullProximityIsNull(): void
    {
        $this->assertNull($this->_invoke('_pnlAtPeak', [1000.0, 1300.0, null]));
    }

    public function testPnlAtPeakBelowMinusHundredIsNull(): void
    {
        $this->assertNull($this->_invoke('_pnlAtPeak', [1000.0, 1300.0, -100.0]));
    }

    public function testPnlAtPeakRecoversHigherPeakAsSmallerLoss(): void
    {
        // Down 23% now (mvalue 1000 vs cost 1300); price is 10% below the window peak, so at the
        // peak the held shares are worth 1000 / 0.9 = 1111.11, a -188.89 EUR / -14.53% loss.
        $pnl = $this->_invoke('_pnlAtPeak', [1000.0, 1300.0, -10.0]);

        $this->assertSame(-188.89, $pnl['eur']);
        $this->assertSame(-14.53, $pnl['pct']);
    }

    public function testPnlAtPeakAtPeakEqualsCurrentUnrealized(): void
    {
        // proximity 0 => price is at the peak, so P&L is just the current unrealized gain.
        $pnl = $this->_invoke('_pnlAtPeak', [1000.0, 1300.0, 0.0]);

        $this->assertSame(-300.0, $pnl['eur']);
        $this->assertSame(-23.08, $pnl['pct']);
    }

    public function testPnlAtPeakDeepDrawdownCanBeProfit(): void
    {
        // 40% below peak: at the peak the shares are worth 1000 / 0.6 = 1666.67, a profit vs 1300.
        $pnl = $this->_invoke('_pnlAtPeak', [1000.0, 1300.0, -40.0]);

        $this->assertSame(366.67, $pnl['eur']);
        $this->assertSame(28.21, $pnl['pct']);
    }

    public function testPnlAtPeakNonPositiveCostSuppressesPercentage(): void
    {
        // Effective cost <= 0 (sell proceeds already exceeded buy cost): amount only, no percentage.
        $pnl = $this->_invoke('_pnlAtPeak', [1000.0, 0.0, -10.0]);

        $this->assertSame(1111.11, $pnl['eur']);
        $this->assertNull($pnl['pct']);
    }

    // ---- _forSymbol ---------------------------------------------------------

    public function testForSymbolNoPositionsIsAllNull(): void
    {
        $result = $this->_invoke('_forSymbol', [['open_positions' => []], ['EUR' => 1.0]]);

        $this->assertSame(['3m' => null, '6m' => null, '1y' => null, '2y' => null], $result);
    }

    public function testForSymbolComputesPerPeriodAndNullsMissingWindow(): void
    {
        $quoteData = [
            'open_positions' => [$this->_position('EUR', 1000.0, 1300.0)],
            'categorization' => [
                'periods' => [
                    '3m' => ['exit_zone' => ['proximity_pct' => -10.0]],
                    '6m' => ['exit_zone' => null],
                    '1y' => ['exit_zone' => ['proximity_pct' => -40.0]],
                    // 2y missing entirely
                ],
            ],
        ];

        $result = $this->_invoke('_forSymbol', [$quoteData, ['EUR' => 1.0]]);

        $this->assertSame(-188.89, $result['3m']['eur']);
        $this->assertNull($result['6m']);
        $this->assertSame(366.67, $result['1y']['eur']);
        $this->assertNull($result['2y']);
    }

    public function testForSymbolAggregatesAcrossAccountsWithFxRates(): void
    {
        // One EUR account plus one USD account (rate 0.5 USD->EUR). At the peak (proximity -10)
        // both legs scale by 1/0.9. EUR leg: 1000; USD leg: 1000 * 0.5 = 500 EUR mvalue, 600 cost.
        $quoteData = [
            'open_positions' => [
                $this->_position('EUR', 1000.0, 1300.0),
                $this->_position('USD', 1000.0, 1200.0),
            ],
            'categorization' => [
                'periods' => ['3m' => ['exit_zone' => ['proximity_pct' => -10.0]]],
            ],
        ];

        $result = $this->_invoke('_forSymbol', [$quoteData, ['EUR' => 1.0, 'USD' => 0.5]]);

        // mvalueEur = 1000 + 500 = 1500; costEur = 1300 + 600 = 1900.
        // proceeds = 1500 / 0.9 = 1666.67; pnl = -233.33; pct = -233.33 / 1900 = -12.28%.
        $this->assertSame(-233.33, $result['3m']['eur']);
        $this->assertSame(-12.28, $result['3m']['pct']);
    }
}
