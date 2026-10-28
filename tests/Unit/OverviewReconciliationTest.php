<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\OverviewReconciliation;

/**
 * Unit tests for OverviewReconciliation::build: pure logic, no DB or Laravel boot.
 *
 * growth = Investment total + Funding total; explained = realized (stock gains + dividends at each year's rate)
 * + the open positions' Gain (today's rate) - money still in intermediary accounts; gap = growth - explained.
 */
class OverviewReconciliationTest extends TestCase
{
    private function _account(string $name, string $displayCode): object
    {
        return (object) ['name' => $name, 'currency' => (object) ['display_code' => $displayCode]];
    }

    public function test_growth_explained_and_gap(): void
    {
        $usd = $this->_account('TST USD', '&#36;');
        $eur = $this->_account('TST EUR', '&euro;');

        $rec = OverviewReconciliation::build(
            [1 => ['balance_in_eur' => -10000.0]],
            [],
            [
                2 => ['accountModel' => $usd, 'balance_in_eur' => 9000.0, 'total_change' => 400.0,
                    'exchange_rate' => 0.5],
                3 => ['accountModel' => $eur, 'balance_in_eur' => 3500.0, 'total_change' => 400.0,
                    'exchange_rate' => null],
            ],
            // 2024 sale in USD at 0.8, 2025 sale in EUR
            [
                2024 => [2 => ['TST.A' => ['total_gain_in_account_currency' => 1000.0]]],
                2025 => [3 => ['TST.B' => ['total_gain_in_account_currency' => 700.0]]],
            ],
            [2025 => [3 => ['TST.B' => ['total_dividend_in_account_currency' => 300.0]]]],
            [2024 => [2 => ['exchange_rate' => 0.8]], 2025 => [3 => ['exchange_rate' => null]]]
        );

        $this->assertEqualsWithDelta(-10000.0, $rec['funding'], 0.001);
        $this->assertEqualsWithDelta(12500.0, $rec['investment'], 0.001);
        $this->assertEqualsWithDelta(2500.0, $rec['growth'], 0.001);
        $this->assertEqualsWithDelta(1500.0, $rec['realized_stock'], 0.001); // 1000 * 0.8 + 700
        $this->assertEqualsWithDelta(300.0, $rec['realized_dividends'], 0.001);
        $this->assertEqualsWithDelta(600.0, $rec['unrealized'], 0.001); // 400 * 0.5 + 400
        $this->assertEqualsWithDelta(2400.0, $rec['explained'], 0.001);
        $this->assertEqualsWithDelta(100.0, $rec['gap'], 0.001);
        $this->assertEqualsWithDelta(96.0, $rec['explained_pct'], 0.001);
        $this->assertSame(['TST USD', 'TST EUR'], array_column($rec['unrealized_accounts'], 'name'));
    }

    /**
     * Money that left a source but sits in an intermediary account isn't in Investment yet: it is subtracted from
     * the explained side so it doesn't show up as a gap.
     */
    public function test_intermediary_money_is_not_a_gap(): void
    {
        $rec = OverviewReconciliation::build(
            [1 => ['balance_in_eur' => -1000.0]],
            [4 => ['balance_in_eur' => 200.0]],
            [2 => ['accountModel' => $this->_account('TST', '&euro;'), 'balance_in_eur' => 800.0,
                'total_change' => 0.0, 'exchange_rate' => null]],
            [],
            [],
            []
        );

        $this->assertEqualsWithDelta(-200.0, $rec['growth'], 0.001);
        $this->assertEqualsWithDelta(-200.0, $rec['explained'], 0.001);
        $this->assertEqualsWithDelta(0.0, $rec['gap'], 0.001);
        $this->assertSame([], $rec['unrealized_accounts']);
    }

    public function test_no_growth_has_no_explained_share(): void
    {
        $rec = OverviewReconciliation::build([], [], [], [], [], []);

        $this->assertNull($rec['explained_pct']);
    }
}
