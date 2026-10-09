<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\PositionsSnapshot;

/**
 * Unit tests for the pure parts of PositionsSnapshot: building the per-user record from the
 * cron's positions, and repricing live rows at the recorded prices. No storage access.
 */
class PositionsSnapshotTest extends TestCase
{
    private function _account(int $userId, string $currency, ?float $cash): array
    {
        return [
            'accountModel' => (object) [
                'user_id' => $userId,
                'currency' => (object) ['iso_code' => $currency],
            ],
            'cashBalanceUtils' => new class ($cash) {
                public function __construct(private ?float $_cash)
                {
                }

                public function getLastCashBalance(): ?object
                {
                    return $this->_cash === null ? null : (object) ['amount' => $this->_cash];
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

    public function test_build_groups_accounts_per_user_and_records_prices(): void
    {
        $groupedItems = [
            1 => ['AAA' => $this->_row(10, 100.0, 800.0, 1.1), 'UNPRICED' => ['quantity' => 3]],
            2 => [],
            3 => ['BBB' => $this->_row(2, 50.0, 90.0)],
        ];
        $accountData = [
            1 => $this->_account(7, 'EUR', 25.0),
            2 => $this->_account(7, 'USD', null),
            3 => $this->_account(8, 'USD', 5.0),
        ];

        $snapshots = PositionsSnapshot::build($groupedItems, $accountData, 1.17, 1700000000);

        $this->assertSame([7, 8], array_keys($snapshots));
        $this->assertTrue($snapshots[7]['complete']);
        $this->assertSame(1700000000, $snapshots[7]['taken_at']);
        $this->assertSame(1.17, $snapshots[7]['eurusd']);
        $this->assertSame(
            ['AAA' => ['quantity' => 10.0, 'price' => 100.0, 'exchange_rate' => 1.1]],
            $snapshots[7]['accounts'][1]['positions']
        );
        $this->assertSame(25.0, $snapshots[7]['accounts'][1]['cash']);
        $this->assertSame(0.0, $snapshots[7]['accounts'][2]['cash']);
        $this->assertSame([], $snapshots[7]['accounts'][2]['positions']);
        $this->assertSame('USD', $snapshots[8]['accounts'][3]['currency']);
    }

    /**
     * Repricing keeps the row's own change field and only swaps its market value, so a row whose
     * change does not equal market value minus cost still shows up as a gap.
     */
    public function test_reprice_swaps_market_value_and_keeps_cost(): void
    {
        $items = ['AAA' => $this->_row(10, 120.0, 800.0, 1.2)];
        $snapAccount = ['positions' => [
            'AAA' => ['quantity' => 10.0, 'price' => 108.0, 'exchange_rate' => 1.08],
        ]];

        $sums = PositionsSnapshot::repriceAccount($items, $snapAccount);

        $this->assertEqualsWithDelta(1000.0, $sums['mvalue'], 0.0001);
        $this->assertEqualsWithDelta(800.0, $sums['cost'], 0.0001);
        $this->assertEqualsWithDelta(200.0, $sums['change'], 0.0001);
    }

    public function test_reprice_returns_null_when_a_quantity_changed(): void
    {
        $items = ['AAA' => $this->_row(12, 100.0, 960.0)];
        $snapAccount = ['positions' => [
            'AAA' => ['quantity' => 10.0, 'price' => 100.0, 'exchange_rate' => 1.0],
        ]];

        $this->assertNull(PositionsSnapshot::repriceAccount($items, $snapAccount));
    }

    public function test_reprice_returns_null_when_a_position_was_closed_or_opened(): void
    {
        $snapAccount = ['positions' => [
            'AAA' => ['quantity' => 10.0, 'price' => 100.0, 'exchange_rate' => 1.0],
            'BBB' => ['quantity' => 1.0, 'price' => 10.0, 'exchange_rate' => 1.0],
        ]];

        $closed = ['AAA' => $this->_row(10, 100.0, 800.0)];
        $opened = $closed + [
            'BBB' => $this->_row(1, 10.0, 9.0),
            'CCC' => $this->_row(1, 5.0, 5.0),
        ];

        $this->assertNull(PositionsSnapshot::repriceAccount($closed, $snapAccount));
        $this->assertNull(PositionsSnapshot::repriceAccount($opened, $snapAccount));
    }

    public function test_cash_of_defaults_to_zero_without_a_balance(): void
    {
        $this->assertSame(0.0, PositionsSnapshot::cashOf($this->_account(1, 'EUR', null)));
        $this->assertSame(0.0, PositionsSnapshot::cashOf([]));
    }
}
