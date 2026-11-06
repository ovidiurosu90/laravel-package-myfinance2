<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

use ovidiuro\myfinance2\Tests\Support\TestUser;
use ovidiuro\myfinance2\Tests\TestCase;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\LiveTierReadings;
use ovidiuro\myfinance2\App\Services\OrderSidePrefill;
use ovidiuro\myfinance2\App\Services\TierReadingBuilder;

/**
 * The order form's side-dependent prefill: the account each side suggests (SELL: the largest holding; BUY: the
 * account last bought in), the figures for the selected account, and the side hint the tier reading gives before a
 * side is picked. Writes only trades of the synthetic TST.SIDE symbol in the first user's own trade accounts (rolled
 * back); the tier pipeline is a fake.
 */
class OrderSidePrefillFeatureTest extends TestCase
{
    private const SYMBOL = 'TST.SIDE';

    /** @var Collection<int, Account> */
    private Collection $_accounts;

    protected function setUp(): void
    {
        parent::setUp();
        AssignedToUserScope::enable();
        $user = TestUser::query()->orderBy('id')->first();
        if (!$user) {
            $this->markTestSkipped('Requires at least 1 user in database');
        }
        $this->actingAs($user);
        config(['myfinance2.watchlistsymbols.order_warnings.live_cache_seconds' => 0]);

        $this->_accounts = Account::where('is_trade_account', 1)->orderBy('id')->take(2)->get();
    }

    protected function tearDown(): void
    {
        Auth::forgetGuards();
        parent::tearDown();
    }

    private function _needTwoAccounts(): void
    {
        if ($this->_accounts->count() < 2) {
            $this->markTestSkipped('Requires 2 trade accounts for the first user');
        }
    }

    private function _trade(Account $account, string $action, float $quantity, string $timestamp): void
    {
        Trade::create([
            'symbol'            => self::SYMBOL,
            'action'            => $action,
            'status'            => 'OPEN',
            'quantity'          => $quantity,
            'unit_price'        => 100.0,
            'fee'               => 0.0,
            'exchange_rate'     => 1.0,
            'account_id'        => $account->id,
            'trade_currency_id' => $account->currency_id,
            'timestamp'         => $timestamp,
        ]);
    }

    /**
     * Account A holds 10 (bought last), account B holds 25 (30 bought, 5 sold).
     */
    private function _holdInTwoAccounts(): array
    {
        $this->_needTwoAccounts();
        [$a, $b] = [$this->_accounts[0], $this->_accounts[1]];
        $this->_trade($b, 'BUY', 30, '2025-01-02 10:00:00');
        $this->_trade($b, 'SELL', 5, '2025-01-03 10:00:00');
        $this->_trade($a, 'BUY', 10, '2025-02-01 10:00:00');

        return [$a, $b];
    }

    private function _financeData(): array
    {
        return ['price' => 100.0, 'currency' => 'EUR', 'closingHigh' => 105.0, 'closingLow' => 50.0];
    }

    public function test_sell_suggests_the_largest_holding_and_buy_the_account_last_bought_in(): void
    {
        [$a, $b] = $this->_holdInTwoAccounts();

        $sides = (new OrderSidePrefill())->build(self::SYMBOL, $this->_financeData(), 1.0, null);

        $this->assertTrue($sides['held']);
        $this->assertSame($b->id, $sides['SELL']['account_id']);
        $this->assertSame($a->id, $sides['BUY']['account_id']);
        $this->assertSame([$b->id, $a->id], array_column($sides['holdings'], 'account_id'));
        $this->assertSame([25.0, 10.0], array_column($sides['holdings'], 'quantity'));
        $this->assertSame(25.0, $sides['SELL']['suggested_qty']); // capped at B's holding
        $this->assertSame(101.0, $sides['SELL']['limit_price']);
        $this->assertSame(99.0, $sides['BUY']['limit_price']);
    }

    public function test_the_figures_follow_the_selected_account(): void
    {
        [$a, $b] = $this->_holdInTwoAccounts();

        $sides = (new OrderSidePrefill())->build(self::SYMBOL, $this->_financeData(), 1.0, $a->id);

        // The suggestion stays the largest holding; the quantity is capped at what the selected account holds.
        $this->assertSame($b->id, $sides['SELL']['account_id']);
        $this->assertSame(10.0, $sides['SELL']['suggested_qty']);
    }

    public function test_nothing_held_suggests_no_sell_account(): void
    {
        $sides = (new OrderSidePrefill())->build(self::SYMBOL, $this->_financeData(), 1.0, null);

        $this->assertFalse($sides['held']);
        $this->assertSame([], $sides['holdings']);
        $this->assertNull($sides['SELL']['account_id']);
        $this->assertNull($sides['SELL']['suggested_qty']);
    }

    public function test_without_a_side_the_tier_reading_returns_the_side_it_points_to(): void
    {
        $this->_fakeReading(['take_profit' => [
            'label' => 'Weak tier in profit', 'action' => 'TAKE PROFIT', 'title' => 'Fake.',
        ]]);

        $response = $this->withoutMiddleware()->getJson(route('myfinance2::orders.tier-reading', [
            'symbol' => self::SYMBOL,
        ]));

        $response->assertOk();
        $this->assertSame('', $response->json('html'));
        $this->assertSame(
            ['action' => 'TAKE PROFIT', 'side' => 'SELL', 'label' => 'Weak tier in profit'],
            $response->json('hint')
        );
    }

    public function test_a_reading_without_a_chip_gives_no_hint(): void
    {
        $this->_fakeReading([]);

        $response = $this->withoutMiddleware()->getJson(route('myfinance2::orders.tier-reading', [
            'symbol' => self::SYMBOL,
        ]));

        $response->assertOk();
        $this->assertNull($response->json('hint'));
    }

    /**
     * Today's (fake) reading of TST.SIDE, with the given chips.
     */
    private function _fakeReading(array $chips): void
    {
        $reading = TierReadingBuilder::fromCategorization(self::SYMBOL, [
            'effective_tier' => 'Rust',
            'tier_tooltip'   => 'Fake.',
            'explanation'    => 'Fake.',
        ] + $chips);
        $live = new class extends LiveTierReadings
        {
            public array $data = [];

            protected function build(): array
            {
                return $this->data;
            }
        };
        $live->data = [
            'taken_at' => now()->format('Y-m-d H:i:s'),
            'as_of'    => now()->format('Y-m-d'),
            'stale'    => [],
            'symbols'  => [self::SYMBOL => ['reading' => $reading, 'facts' => [], 'position' => []]],
        ];
        app()->instance(LiveTierReadings::class, $live);
    }
}
