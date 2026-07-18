<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ovidiuro\myfinance2\App\Services\PositionsReconciliationService;

/**
 * Unit tests for PositionsReconciliationService, covering the pure comparison logic without
 * DB, config, or ChartsBuilder (storage) access. The private methods are exercised via
 * reflection, same pattern as AlertServiceTest.
 */
class PositionsReconciliationServiceTest extends TestCase
{
    private PositionsReconciliationService $_service;
    private ReflectionClass $_reflection;

    protected function setUp(): void
    {
        $this->_service = new PositionsReconciliationService();
        $this->_reflection = new ReflectionClass(PositionsReconciliationService::class);
    }

    private function _invoke(string $method, array $args): mixed
    {
        $m = $this->_reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->_service, $args);
    }

    private function _compare(float $computed, float $shown, float $tolerancePct,
        float $floor = 1.0): ?array
    {
        return $this->_invoke('_compare',
            ['account', 'Acc', '$', 'cost', $computed, $shown, $tolerancePct, $floor]);
    }

    public function test_matching_values_produce_no_issue(): void
    {
        $this->assertNull($this->_compare(1000.0, 1000.0, 0.5));
    }

    public function test_difference_within_tolerance_is_ignored(): void
    {
        // 0.2% apart, tolerance 0.5% -> no issue.
        $this->assertNull($this->_compare(100000.0, 100200.0, 0.5));
    }

    public function test_difference_beyond_tolerance_is_flagged(): void
    {
        // 1% apart, tolerance 0.5% -> issue, with signed diff and percentage.
        $issue = $this->_compare(100000.0, 101000.0, 0.5);

        $this->assertIsArray($issue);
        $this->assertSame('cost', $issue['metric']);
        $this->assertEqualsWithDelta(1000.0, $issue['diff'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $issue['diff_pct'], 0.01);
    }

    public function test_absolute_floor_suppresses_near_zero_noise(): void
    {
        // Values differ by 100% but only by 0.5 in absolute terms; floor of 1.0 suppresses it.
        $this->assertNull($this->_compare(0.5, 1.0, 0.5, 1.0));
    }

    public function test_sum_position_rows_totals_the_account_currency_fields(): void
    {
        $items = [
            'AAA' => [
                'market_value_in_account_currency' => 1000.0,
                'cost2_in_account_currency' => 800.0,
                'overall_change2_in_account_currency' => 200.0,
            ],
            'BBB' => [
                'market_value_in_account_currency' => 500.0,
                'cost2_in_account_currency' => 600.0,
                'overall_change2_in_account_currency' => -100.0,
            ],
        ];

        $sums = $this->_invoke('_sumPositionRows', [$items]);

        $this->assertEqualsWithDelta(1500.0, $sums['mvalue'], 0.0001);
        $this->assertEqualsWithDelta(1400.0, $sums['cost'], 0.0001);
        $this->assertEqualsWithDelta(100.0, $sums['change'], 0.0001);
    }

    public function test_eur_factor_maps_currencies(): void
    {
        $this->assertSame(1.0, $this->_invoke('_eurFactor', ['EUR', 1.1446]));
        $this->assertEqualsWithDelta(1.0 / 1.1446, $this->_invoke('_eurFactor', ['USD', 1.1446]),
            0.000001);
        $this->assertNull($this->_invoke('_eurFactor', ['GBP', 1.1446]));
    }

    private function _account(string $currency, float $cash): array
    {
        return [
            'accountModel' => (object) ['currency' => (object) ['iso_code' => $currency]],
            'cashBalanceUtils' => new class ($cash) {
                public function __construct(private float $_cash)
                {
                }

                public function getAmount(): float
                {
                    return $this->_cash;
                }
            },
        ];
    }

    private function _rows(float $mvalue, float $cost, float $change): array
    {
        return [
            'X' => [
                'market_value_in_account_currency' => $mvalue,
                'cost2_in_account_currency' => $cost,
                'overall_change2_in_account_currency' => $change,
            ],
        ];
    }

    /**
     * Live portfolio sum converts each account to EUR at the current rate (USD divided by the
     * EURUSD rate), sums positions plus cash, and is independent of the stored User Overview.
     */
    public function test_sum_portfolio_live_in_eur_converts_and_aggregates(): void
    {
        $groupedItems = [
            1 => $this->_rows(1000.0, 800.0, 200.0),
            2 => $this->_rows(500.0, 400.0, 100.0),
        ];
        $accountData = [
            1 => $this->_account('EUR', 100.0),
            2 => $this->_account('USD', 1000.0),
        ];

        // EURUSD 1.25 -> USD factor 0.8.
        $sums = $this->_invoke('_sumPortfolioLiveInEur', [$groupedItems, $accountData, 1.25]);

        $this->assertEqualsWithDelta(1400.0, $sums['mvalue'], 0.0001);
        $this->assertEqualsWithDelta(1120.0, $sums['cost'], 0.0001);
        $this->assertEqualsWithDelta(280.0, $sums['change'], 0.0001);
        $this->assertEqualsWithDelta(900.0, $sums['cash'], 0.0001);
    }
}
