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
        $this->_service = new PositionsReconciliationService(0.05);
        $this->_reflection = new ReflectionClass(PositionsReconciliationService::class);
    }

    private function _invoke(string $method, array $args): mixed
    {
        $m = $this->_reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->_service, $args);
    }

    private function _compare(float $computed, ?float $shown): ?array
    {
        return $this->_invoke('_compare',
            ['stored', 'account', 'Acc', '$', 'cost', $computed, $shown]);
    }

    private function _account(string $currency, float $cash, string $name = 'Acc'): array
    {
        return [
            'accountModel' => (object) [
                'name' => $name,
                'currency' => (object) ['iso_code' => $currency, 'display_code' => $currency],
            ],
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

    private function _row(float $quantity, float $price, float $cost, float $fx = 1.0): array
    {
        $mvalue = $quantity * $price / $fx;

        return [
            'quantity' => $quantity,
            'price' => $price,
            'exchange_rate' => $fx,
            'market_value_in_account_currency' => $mvalue,
            'cost2_in_account_currency' => $cost,
            'overall_change2_in_account_currency' => $mvalue - $cost,
        ];
    }

    private function _series(float $value): array
    {
        return [['time' => '2026-10-08', 'value' => 1.0], ['time' => '2026-10-09', 'value' => $value]];
    }

    private function _snapshotOneAccount(float $cash): array
    {
        return ['accounts' => [1 => [
            'cash' => $cash,
            'positions' => ['AAA' => ['quantity' => 10.0, 'price' => 100.0, 'exchange_rate' => 1.0]],
        ]]];
    }

    public function test_matching_values_produce_no_issue(): void
    {
        $this->assertNull($this->_compare(1000.0, 1000.0));
    }

    public function test_rounding_within_the_tolerance_is_ignored(): void
    {
        $this->assertNull($this->_compare(100000.0, 100000.04));
    }

    /**
     * Both sides use the same prices, so even a small gap on a large base is a real error.
     */
    public function test_gap_beyond_the_tolerance_is_flagged_on_any_base(): void
    {
        $issue = $this->_compare(100000.0, 100001.0);

        $this->assertIsArray($issue);
        $this->assertSame('stored', $issue['check']);
        $this->assertSame('cost', $issue['metric']);
        $this->assertEqualsWithDelta(1.0, $issue['diff'], 0.0001);
        $this->assertEqualsWithDelta(0.001, $issue['diff_pct'], 0.0001);
    }

    public function test_missing_shown_value_is_skipped(): void
    {
        $this->assertNull($this->_compare(1000.0, null));
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

    /**
     * EURUSD 1.25: a USD account is divided by the rate, a EUR account kept as is.
     */
    public function test_sum_in_eur_converts_and_aggregates(): void
    {
        $sums = [
            1 => ['mvalue' => 1000.0, 'cost' => 800.0, 'change' => 200.0, 'cash' => 100.0],
            2 => ['mvalue' => 500.0, 'cost' => 400.0, 'change' => 100.0, 'cash' => 1000.0],
        ];
        $accountData = [1 => $this->_account('EUR', 0.0), 2 => $this->_account('USD', 0.0)];

        $total = $this->_invoke('_sumInEur', [$sums, $accountData, 1.25]);

        $this->assertEqualsWithDelta(1400.0, $total['mvalue'], 0.0001);
        $this->assertEqualsWithDelta(1120.0, $total['cost'], 0.0001);
        $this->assertEqualsWithDelta(280.0, $total['change'], 0.0001);
        $this->assertEqualsWithDelta(900.0, $total['cash'], 0.0001);
    }

    /**
     * Check A1: headers pinned to the live totals agree with the rows, so nothing is flagged.
     */
    public function test_account_totals_check_passes_when_headers_match_rows(): void
    {
        $groupedItems = [1 => ['AAA' => $this->_row(10, 100.0, 800.0)]];
        $accountData = [1 => $this->_account('USD', 50.0)];
        $liveSeries = ['accounts' => [1 => [
            'mvalue' => $this->_series(1000.0),
            'cost' => $this->_series(800.0),
            'change' => $this->_series(200.0),
        ]], 'user' => [], 'eurusd' => null];

        $this->assertSame([], $this->_invoke('_checkAccountTotals',
            [$groupedItems, $accountData, $liveSeries]));
    }

    /**
     * Check A1: a header that drifted from the rows (e.g. a totals bug) is flagged to the cent.
     */
    public function test_account_totals_check_flags_a_header_off_from_the_rows(): void
    {
        $groupedItems = [1 => ['AAA' => $this->_row(10, 100.0, 800.0)]];
        $accountData = [1 => $this->_account('USD', 50.0)];
        $liveSeries = ['accounts' => [1 => [
            'mvalue' => $this->_series(1000.0),
            'cost' => $this->_series(800.0),
            'change' => $this->_series(189.14),
        ]], 'user' => [], 'eurusd' => null];

        $issues = $this->_invoke('_checkAccountTotals', [$groupedItems, $accountData, $liveSeries]);

        $this->assertCount(1, $issues);
        $this->assertSame('rows', $issues[0]['check']);
        $this->assertSame('change', $issues[0]['metric']);
        $this->assertEqualsWithDelta(-10.86, $issues[0]['diff'], 0.0001);
    }

    /**
     * Check B: the rows are repriced at the snapshot's prices, so a price that moved since the
     * snapshot does not change the repriced figures.
     */
    public function test_reprice_at_snapshot_uses_the_snapshot_prices(): void
    {
        // Live price 110, snapshot price 100: the repriced market value is 1000, not 1100.
        $groupedItems = [1 => ['AAA' => $this->_row(10, 110.0, 800.0)]];
        $accountData = [1 => $this->_account('USD', 50.0)];

        $result = $this->_invoke('_repriceAtSnapshot',
            [$groupedItems, $accountData, $this->_snapshotOneAccount(50.0)]);

        $this->assertTrue($result['complete']);
        $this->assertEqualsWithDelta(1000.0, $result['accounts'][1]['mvalue'], 0.0001);
        $this->assertEqualsWithDelta(800.0, $result['accounts'][1]['cost'], 0.0001);
        $this->assertEqualsWithDelta(200.0, $result['accounts'][1]['change'], 0.0001);
        $this->assertEqualsWithDelta(50.0, $result['accounts'][1]['cash'], 0.0001);
    }

    /**
     * Check B: a deposit since the snapshot makes the account incomparable, and with it the
     * User Overview total, instead of raising a false alert.
     */
    public function test_reprice_at_snapshot_skips_an_account_whose_cash_changed(): void
    {
        $groupedItems = [1 => ['AAA' => $this->_row(10, 100.0, 800.0)]];
        $accountData = [1 => $this->_account('USD', 550.0)];

        $result = $this->_invoke('_repriceAtSnapshot',
            [$groupedItems, $accountData, $this->_snapshotOneAccount(50.0)]);

        $this->assertFalse($result['complete']);
        $this->assertSame([], $result['accounts']);
    }

    /**
     * Check B: an account opened since the snapshot is not in the stored User Overview yet.
     */
    public function test_reprice_at_snapshot_is_incomplete_when_an_account_was_added(): void
    {
        $groupedItems = [
            1 => ['AAA' => $this->_row(10, 100.0, 800.0)],
            2 => [],
        ];
        $accountData = [1 => $this->_account('USD', 50.0), 2 => $this->_account('EUR', 10.0)];

        $result = $this->_invoke('_repriceAtSnapshot',
            [$groupedItems, $accountData, $this->_snapshotOneAccount(50.0)]);

        $this->assertFalse($result['complete']);
        $this->assertArrayHasKey(1, $result['accounts']);
    }

    /**
     * Displayed series for check A2: a EUR and a USD account, and a User Overview whose figures
     * are their sums at EURUSD 1.25, optionally with one figure overridden.
     */
    private function _userTotalsFixture(array $userOverrides = []): array
    {
        $accountData = [1 => $this->_account('EUR', 0.0), 2 => $this->_account('USD', 0.0)];
        $accounts = [
            1 => ['cash' => 100.0, 'mvalue' => 1000.0, 'cost' => 800.0, 'change' => 200.0],
            2 => ['cash' => 1000.0, 'mvalue' => 500.0, 'cost' => 400.0, 'change' => 100.0],
        ];
        $user = [
            'cash_EUR' => 900.0, 'mvalue_EUR' => 1400.0, 'cost_EUR' => 1120.0, 'change_EUR' => 280.0,
            'cash_USD' => 1125.0, 'mvalue_USD' => 1750.0, 'cost_USD' => 1400.0, 'change_USD' => 350.0,
        ];

        $liveSeries = ['accounts' => [], 'user' => [], 'eurusd' => 1.25];
        foreach ($accounts as $accountId => $figures) {
            foreach ($figures as $metric => $value) {
                $liveSeries['accounts'][$accountId][$metric] = $this->_series($value);
            }
        }
        foreach (array_merge($user, $userOverrides) as $metric => $value) {
            $liveSeries['user'][$metric] = $this->_series($value);
        }

        return [$accountData, $liveSeries];
    }

    /**
     * Check A2: the User Overview equals the sum of the accounts in both currencies.
     */
    public function test_user_totals_check_passes_when_overview_is_the_sum_of_accounts(): void
    {
        [$accountData, $liveSeries] = $this->_userTotalsFixture();

        $this->assertSame([], $this->_invoke('_checkUserTotals', [$accountData, $liveSeries]));
    }

    /**
     * Check A2: overview cash that is not the sum of the accounts' cash is flagged.
     */
    public function test_user_totals_check_flags_cash_off_from_the_accounts(): void
    {
        [$accountData, $liveSeries] = $this->_userTotalsFixture(['cash_EUR' => 910.0]);

        $issues = $this->_invoke('_checkUserTotals', [$accountData, $liveSeries]);

        $this->assertCount(1, $issues);
        $this->assertSame('accounts', $issues[0]['check']);
        $this->assertSame('cash', $issues[0]['metric']);
        $this->assertSame('&euro;', $issues[0]['currency']);
        $this->assertEqualsWithDelta(10.0, $issues[0]['diff'], 0.0001);
    }

    /**
     * Check A2: the USD view is checked too, so a wrong conversion in it surfaces.
     */
    public function test_user_totals_check_covers_the_usd_view(): void
    {
        [$accountData, $liveSeries] = $this->_userTotalsFixture(['mvalue_USD' => 1700.0]);

        $issues = $this->_invoke('_checkUserTotals', [$accountData, $liveSeries]);

        $this->assertCount(1, $issues);
        $this->assertSame('mvalue', $issues[0]['metric']);
        $this->assertSame('$', $issues[0]['currency']);
    }

    public function test_fx_factor_maps_currencies(): void
    {
        $this->assertSame(1.0, $this->_invoke('_fxFactor', ['USD', 'USD', 1.25]));
        $this->assertSame(0.8, $this->_invoke('_fxFactor', ['USD', 'EUR', 1.25]));
        $this->assertSame(1.25, $this->_invoke('_fxFactor', ['EUR', 'USD', 1.25]));
        $this->assertNull($this->_invoke('_fxFactor', ['GBP', 'EUR', 1.25]));
    }
}
