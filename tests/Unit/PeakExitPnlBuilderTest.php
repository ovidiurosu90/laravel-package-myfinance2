<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\PeakExitPnlBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

/**
 * Locks down the pure "P&L if sold at this window's peak" math that the quadrant per-period
 * table shows next to "From peak". Valuing the held shares at the window peak (the exit zone's
 * EUR peak price times the held quantity) and comparing against their cost is all pure
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

    private function _position(string $currency, float $quantity, float $cost2): array
    {
        $account           = new stdClass();
        $account->currency = new stdClass();
        $account->currency->iso_code = $currency;

        return [
            'accountModel'              => $account,
            'quantity'                  => $quantity,
            'cost2_in_account_currency' => $cost2,
        ];
    }

    // ---- _pnlAtPeak ---------------------------------------------------------

    public function testPnlAtPeakNullPeakIsNull(): void
    {
        $this->assertNull($this->_invoke('_pnlAtPeak', [null, 10.0, 1300.0]));
    }

    public function testPnlAtPeakNonPositivePeakIsNull(): void
    {
        $this->assertNull($this->_invoke('_pnlAtPeak', [0.0, 10.0, 1300.0]));
    }

    public function testPnlAtPeakComputesGain(): void
    {
        // 10 shares valued at the 150 EUR peak = 1500 proceeds, a +200 EUR / +15.38% gain vs 1300 cost.
        $pnl = $this->_invoke('_pnlAtPeak', [150.0, 10.0, 1300.0]);

        $this->assertSame(200.0, $pnl['eur']);
        $this->assertSame(15.38, $pnl['pct']);
    }

    public function testPnlAtPeakComputesLoss(): void
    {
        // 10 shares at the 100 EUR peak = 1000 proceeds, a -300 EUR / -23.08% loss vs 1300 cost.
        $pnl = $this->_invoke('_pnlAtPeak', [100.0, 10.0, 1300.0]);

        $this->assertSame(-300.0, $pnl['eur']);
        $this->assertSame(-23.08, $pnl['pct']);
    }

    public function testPnlAtPeakNonPositiveCostSuppressesPercentage(): void
    {
        // Cost <= 0 (sell proceeds already exceeded buy cost): amount only, no percentage.
        $pnl = $this->_invoke('_pnlAtPeak', [100.0, 10.0, 0.0]);

        $this->assertSame(1000.0, $pnl['eur']);
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
        // 10 shares, cost2 1300 (no performance windows, so the cost2 fallback is used).
        $quoteData = [
            'open_positions' => [$this->_position('EUR', 10.0, 1300.0)],
            'categorization' => [
                'periods' => [
                    '3m' => ['exit_zone' => ['peak_price_eur' => 150.0]],
                    '6m' => ['exit_zone' => null],
                    '1y' => ['exit_zone' => ['peak_price_eur' => 80.0]],
                    // 2y missing entirely
                ],
            ],
        ];

        $result = $this->_invoke('_forSymbol', [$quoteData, ['EUR' => 1.0]]);

        // 3m: 150 * 10 - 1300 = 200; 1y: 80 * 10 - 1300 = -500.
        $this->assertSame(200.0, $result['3m']['eur']);
        $this->assertNull($result['6m']);
        $this->assertSame(-500.0, $result['1y']['eur']);
        $this->assertNull($result['2y']);
    }

    public function testForSymbolUsesWindowCostBasisWhenAvailable(): void
    {
        // The open window's remaining_cost_eur (1000) overrides the cost2 fallback (1300).
        $quoteData = [
            'open_positions' => [$this->_position('EUR', 10.0, 1300.0)],
            'performance'    => [
                'windows' => [
                    ['is_open' => false, 'remaining_cost_eur' => 999.0],
                    ['is_open' => true,  'remaining_cost_eur' => 1000.0],
                ],
            ],
            'categorization' => [
                'periods' => ['3m' => ['exit_zone' => ['peak_price_eur' => 150.0]]],
            ],
        ];

        $result = $this->_invoke('_forSymbol', [$quoteData, ['EUR' => 1.0]]);

        // 150 * 10 - 1000 = 500; pct = 500 / 1000 = 50%.
        $this->assertSame(500.0, $result['3m']['eur']);
        $this->assertSame(50.0, $result['3m']['pct']);
    }

    public function testForSymbolPartialPeriodValuesAtHeldPeakWithWarning(): void
    {
        // Held only 10 days: well inside the 3M window, so it is a partial hold. The 3M peak (150) is
        // higher than the held peak (145), so the figure uses the held peak and flags the shortfall.
        $start = (new \DateTimeImmutable())->modify('-10 days');
        $quoteData = [
            'open_positions' => [$this->_position('EUR', 10.0, 1300.0)],
            'performance'    => [
                'windows' => [
                    ['is_open' => true, 'remaining_cost_eur' => 1300.0,
                     'start_date' => $start, 'peak_price_eur' => 145.0,
                     'peak_gain_date' => $start->modify('+5 days')],
                ],
            ],
            'categorization' => [
                'periods' => ['3m' => ['exit_zone' => ['peak_price_eur' => 150.0]]],
            ],
        ];

        $result = $this->_invoke('_forSymbol', [$quoteData, ['EUR' => 1.0]]);

        // Valued at the held peak 145, not the 3m peak 150: 145 * 10 - 1300 = 150.
        $this->assertSame(150.0, $result['3m']['eur']);
        $this->assertTrue($result['3m']['incomplete']);
        $this->assertSame(150.0, $result['3m']['period_peak_eur']);
        $this->assertSame(145.0, $result['3m']['held_peak_eur']);
        $this->assertSame(3.3, $result['3m']['shortfall_pct']); // (150 - 145) / 150 = 3.33%
    }

    public function testForSymbolFullPeriodValuesAtPeriodPeakNoWarning(): void
    {
        // Held 200 days: the whole 3M window, so the period's own peak (150) is used, no warning.
        $start = (new \DateTimeImmutable())->modify('-200 days');
        $quoteData = [
            'open_positions' => [$this->_position('EUR', 10.0, 1300.0)],
            'performance'    => [
                'windows' => [
                    ['is_open' => true, 'remaining_cost_eur' => 1300.0,
                     'start_date' => $start, 'peak_price_eur' => 200.0],
                ],
            ],
            'categorization' => [
                'periods' => ['3m' => ['exit_zone' => ['peak_price_eur' => 150.0]]],
            ],
        ];

        $result = $this->_invoke('_forSymbol', [$quoteData, ['EUR' => 1.0]]);

        // 150 * 10 - 1300 = 200, valued at the 3m peak, no incomplete flag.
        $this->assertSame(200.0, $result['3m']['eur']);
        $this->assertArrayNotHasKey('incomplete', $result['3m']);
    }

    public function testForSymbolAggregatesQuantityAcrossAccountsWithFxRates(): void
    {
        // EUR account (10 shares, cost2 1300) plus USD account (5 shares, cost2 1200, rate 0.5).
        // The peak price is in EUR, so it applies to all 15 shares; only the cost is FX-converted.
        $quoteData = [
            'open_positions' => [
                $this->_position('EUR', 10.0, 1300.0),
                $this->_position('USD', 5.0, 1200.0),
            ],
            'categorization' => [
                'periods' => ['3m' => ['exit_zone' => ['peak_price_eur' => 150.0]]],
            ],
        ];

        $result = $this->_invoke('_forSymbol', [$quoteData, ['EUR' => 1.0, 'USD' => 0.5]]);

        // heldQty = 15; costEur = 1300 + 1200 * 0.5 = 1900.
        // proceeds = 150 * 15 = 2250; pnl = 350; pct = 350 / 1900 = 18.42%.
        $this->assertSame(350.0, $result['3m']['eur']);
        $this->assertSame(18.42, $result['3m']['pct']);
    }
}
