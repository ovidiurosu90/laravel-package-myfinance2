<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;

use ovidiuro\myfinance2\App\Services\SymbolPositionReturns;

/**
 * Pure tests for the position figures the alert emails share (watchlist dashboard, peak-proximity and price alerts):
 * the unrealized gain uses cost2 (fees included) in EUR across accounts, today's move sums the day's P&L, and a
 * symbol not held still reads today's % from its quote. No DB, no HTTP.
 */
class SymbolPositionReturnsTest extends TestCase
{
    private static function _position(string $accountCurrency, array $values): array
    {
        $account = (object) ['currency' => (object) ['iso_code' => $accountCurrency]];

        return array_merge(['accountModel' => $account, 'trades' => []], $values);
    }

    public function test_no_open_positions_has_no_figures(): void
    {
        $this->assertNull(SymbolPositionReturns::fromOpenPositions([], ['EUR' => 1.0]));
    }

    public function test_figures_sum_accounts_in_eur_with_fees_in_the_cost(): void
    {
        $figures = SymbolPositionReturns::fromOpenPositions([
            self::_position('EUR', [
                'market_value_in_account_currency' => 1100.0,
                'cost2_in_account_currency'        => 1010.0, // 1000 + 10 fees
                'cost2_in_trade_currency'          => 1010.0,
                'day_change_in_account_currency'   => -50.0,
                'day_change_percentage'            => -4.35,
                'quantity'                         => 10.0,
            ]),
            self::_position('USD', [
                'market_value_in_account_currency' => 1200.0,
                'cost2_in_account_currency'        => 1000.0,
                'cost2_in_trade_currency'          => 1000.0,
                'day_change_in_account_currency'   => -60.0,
                'day_change_percentage'            => -4.35,
                'quantity'                         => 10.0,
            ]),
        ], ['EUR' => 1.0, 'USD' => 0.5]);

        // EUR: 1100 + 600 market value, 1010 + 500 cost => +190 (12.58%); today -50 + -30.
        $this->assertEqualsWithDelta(190.0, $figures['gain_eur'], 0.001);
        $this->assertEqualsWithDelta(190.0 / 1510.0 * 100.0, $figures['raw_pct'], 0.0001);
        $this->assertEqualsWithDelta(-80.0, $figures['today_gain_eur'], 0.001);
        $this->assertEqualsWithDelta(-4.35, $figures['today_gain_pct'], 0.0001);
        $this->assertEqualsWithDelta(20.0, $figures['quantity'], 0.0001);
        $this->assertEqualsWithDelta(100.5, $figures['avg_unit_cost2'], 0.0001);
        $this->assertSame(0, $figures['days']);
    }

    public function test_live_eur_rates_invert_the_eur_account_rates_and_derive_pence(): void
    {
        $rates = SymbolPositionReturns::liveEurRates([
            ['account_currency' => 'EUR', 'trade_currency' => 'USD', 'exchange_rate' => 1.25],
            ['account_currency' => 'EUR', 'trade_currency' => 'GBP', 'exchange_rate' => 0.8],
            ['account_currency' => 'USD', 'trade_currency' => 'EUR', 'exchange_rate' => 0.8],
            ['account_currency' => 'EUR', 'trade_currency' => 'CHF', 'exchange_rate' => 0],
        ]);

        $this->assertEqualsWithDelta(1.0, $rates['EUR'], 0.0001);
        $this->assertEqualsWithDelta(0.8, $rates['USD'], 0.0001);
        $this->assertEqualsWithDelta(1.25, $rates['GBP'], 0.0001);
        $this->assertEqualsWithDelta(0.0125, $rates['GBp'], 0.000001);
        $this->assertArrayNotHasKey('CHF', $rates);
    }

    public function test_quote_day_change_pct_reads_the_quote_for_a_symbol_not_held(): void
    {
        $this->assertEqualsWithDelta(-6.31, SymbolPositionReturns::quoteDayChangePct(
            ['day_change_percentage' => -6.31]
        ), 0.0001);
        $this->assertNull(SymbolPositionReturns::quoteDayChangePct(['day_change_percentage' => false]));
        $this->assertNull(SymbolPositionReturns::quoteDayChangePct(null));
    }
}
