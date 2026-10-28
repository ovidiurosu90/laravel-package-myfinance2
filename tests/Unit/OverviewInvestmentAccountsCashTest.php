<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\CashBalancesUtils;
use ovidiuro\myfinance2\App\Services\OverviewDashboard;

/**
 * Unit tests for OverviewDashboard::_buildInvestmentAccounts' Cash column: pure logic, no DB or Laravel boot.
 *
 * A trade account whose positions are all sold drops out of the positions list, so it is added from the funding
 * bucket. Its Cash used to be the ledger balance (net funding, which ignores the trades: money funded, spent on
 * shares, then sold and transferred out showed the spent amount as cash) instead of its cash balance.
 */
class OverviewInvestmentAccountsCashTest extends TestCase
{
    private function _call(array $positionAccountData, array $investmentBucket): array
    {
        $service = (new \ReflectionClass(OverviewDashboard::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(OverviewDashboard::class, '_buildInvestmentAccounts');
        $method->setAccessible(true);

        return $method->invoke($service, $positionAccountData, $investmentBucket);
    }

    private function _account(string $name): object
    {
        return (object) ['name' => $name, 'currency' => (object) ['display_code' => '&euro;', 'iso_code' => 'EUR']];
    }

    private function _cashBalance(?string $amount): CashBalancesUtils
    {
        $utils = $this->createMock(CashBalancesUtils::class);
        $utils->method('getAmount')->willReturn($amount);
        return $utils;
    }

    /**
     * Funded 100, bought shares for 100, sold them for 60 and transferred the 60 out: the ledger nets 40,
     * the cash balance is 0.
     */
    public function test_trade_account_without_open_positions_shows_its_cash_balance(): void
    {
        $account = $this->_account('Sold out');
        $accounts = $this->_call(
            [7 => [
                'accountModel'       => $account,
                'total_cost'         => 0,
                'total_market_value' => 0,
                'cashBalanceUtils'   => $this->_cashBalance('0.0000'),
            ]],
            [7 => ['account' => $account, 'balance' => 40.0]]
        );

        $this->assertSame(0.0, $accounts[7]['cash']);
        $this->assertSame(0.0, $accounts[7]['balance_in_eur']);
    }

    public function test_account_without_cash_balance_falls_back_to_the_ledger_balance(): void
    {
        $savings = $this->_account('Savings');
        $soldOut = $this->_account('No cash rows');
        $accounts = $this->_call(
            [8 => [
                'accountModel'       => $soldOut,
                'total_cost'         => 0,
                'total_market_value' => 0,
                'cashBalanceUtils'   => $this->_cashBalance(null),
            ]],
            [
                7 => ['account' => $savings, 'balance' => 1500.0],
                8 => ['account' => $soldOut, 'balance' => 25.0],
            ]
        );

        $this->assertSame(1500.0, $accounts[7]['cash']);
        $this->assertSame(25.0, $accounts[8]['cash']);
    }
}
