<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature\Security;

use ovidiuro\myfinance2\Tests\TestCase;
use ovidiuro\myfinance2\Tests\Support\TestUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * One user can never read, change or delete another's records, nor point their own records at another's accounts or
 * currencies, whatever the gates let them do (here: everything; the app decides who may use MyFinance2).
 *
 * The victim's records are synthetic (symbol TST.VICTIM, marked MARKER); victim and attacker are users with no data,
 * never saved. Rows are inserted with the query builder (foreign keys off on this connection only, as the victim is
 * not a stored user) and rolled back. Every attempt is followed by a check that the victim's rows are byte for byte
 * unchanged.
 */
class FinanceCrossUserIsolationTest extends TestCase
{

    private const SYMBOL = 'TST.VICTIM';
    private const MARKER = 'tst-victim-marker';

    /**
     * Resource routes (route name prefix => table) whose edit / update / destroy take the record id.
     */
    private const RESOURCES = [
        'accounts'            => 'accounts',
        'currencies'          => 'currencies',
        'trades'              => 'trades',
        'dividends'           => 'dividends',
        'cash-balances'       => 'cash_balances',
        'ledger-transactions' => 'ledger_transactions',
        'watchlist-symbols'   => 'watchlist_symbols',
        'orders'              => 'orders',
        'price-alerts'        => 'price_alerts',
    ];

    /**
     * The victim's record ids, by table.
     *
     * @var array<string, int>
     */
    private array $_victim = [];

    protected function setUp(): void
    {
        parent::setUp();

        AssignedToUserScope::enable();

        // Two users with no data, never saved: the victim's records are seeded, the attacker passes every gate
        $this->_victim = $this->_seedRecordsOf($this->userWithoutData(1));
        $this->actingAs($this->userWithoutData());
    }

    protected function tearDown(): void
    {
        AssignedToUserScope::enable();
        Auth::forgetGuards();
        parent::tearDown();
    }

    public function test_someone_elses_records_do_not_open_for_editing(): void
    {
        foreach (self::RESOURCES as $resource => $table) {
            $this->get(route("myfinance2::{$resource}.edit", $this->_victim[$table]))
                ->assertNotFound()
                ->assertDontSee(self::MARKER);
        }
        $this->get(route('myfinance2::stock-splits.revert-preview', $this->_victim['stock_splits']))->assertNotFound();
    }

    public function test_lists_never_show_someone_elses_records(): void
    {
        $pages = array_map(fn (string $resource) => "myfinance2::{$resource}.index", array_keys(self::RESOURCES));
        $pages = array_merge($pages, [
            'myfinance2::stock-splits.index', 'myfinance2::price-alerts.history',
            'myfinance2::peak-proximity-alerts.history', 'myfinance2::peak-proximity-alerts.inbox',
            'myfinance2::dip-buying-alerts.history', 'myfinance2::portfolio-peak-alerts.history',
        ]);

        foreach ($pages as $name) {
            $this->get(route($name))->assertOk()->assertDontSee(self::MARKER)->assertDontSee(self::SYMBOL);
        }
    }

    public function test_someone_elses_records_cannot_be_updated_or_deleted(): void
    {
        $before = $this->_victimRows();

        foreach (self::RESOURCES as $resource => $table) {
            $id   = $this->_victim[$table];
            $data = ['description' => 'changed', 'name' => 'changed', 'notes' => 'changed'] + $before[$table];
            $this->put(route("myfinance2::{$resource}.update", $id), $data);
            $this->delete(route("myfinance2::{$resource}.destroy", $id))->assertNotFound();
        }

        $this->assertSame($before, $this->_victimRows(), 'Someone else changed or deleted the records');
    }

    public function test_someone_elses_records_cannot_be_acted_on(): void
    {
        $before = $this->_victimRows();
        $order  = $this->_victim['orders'];
        $alert  = $this->_victim['price_alerts'];
        $split  = $this->_victim['stock_splits'];

        foreach (['cancel', 'expire', 'expire-and-clone', 'fill', 'place', 'reopen', 'unlink-trade'] as $action) {
            $this->post(route("myfinance2::orders.{$action}", $order))->assertNotFound();
        }
        // Someone else's trade is not a valid trade_id (OwnRecord), whichever order it is linked to.
        $this->post(route('myfinance2::orders.link-trade', $order), ['trade_id' => $this->_victim['trades']])
            ->assertSessionHasErrors(['trade_id']);
        $this->patch(route('myfinance2::trades.close', $this->_victim['trades']))->assertNotFound();
        $this->patch(route('myfinance2::trades.close-symbol'),
            ['account_id' => $this->_victim['accounts'], 'symbol' => self::SYMBOL]);
        $this->post(route('myfinance2::price-alerts.pause', $alert))->assertNotFound();
        $this->post(route('myfinance2::price-alerts.resume', $alert))->assertNotFound();
        $this->post(route('myfinance2::stock-splits.revert', $split))->assertNotFound();
        $this->post(route('myfinance2::stock-splits.reapply', $split))->assertNotFound();
        $this->delete(route('myfinance2::symbol-tier-overrides.destroy', self::SYMBOL));

        $this->assertSame($before, $this->_victimRows(), 'Someone else acted on the records');
    }

    public function test_someone_elses_alerts_and_notifications_cannot_be_deleted_or_dismissed(): void
    {
        $before = $this->_victimRows();

        foreach (['pause', 'resume', 'delete'] as $action) {
            $this->post(route('myfinance2::price-alerts.bulk-action'),
                ['action' => $action, 'ids' => [$this->_victim['price_alerts']]]);
        }
        $histories = [
            'price-alerts'          => 'price_alert_notifications',
            'peak-proximity-alerts' => 'peak_proximity_notifications',
            'dip-buying-alerts'     => 'dip_buying_notifications',
            'portfolio-peak-alerts' => 'portfolio_peak_notifications',
        ];
        foreach ($histories as $prefix => $table) {
            $this->delete(route("myfinance2::{$prefix}.history.destroy", $this->_victim[$table]))->assertNotFound();
            $this->post(route("myfinance2::{$prefix}.history.bulk-action"),
                ['action' => 'delete', 'ids' => [$this->_victim[$table]]]);
        }
        $this->post(route('myfinance2::peak-proximity-alerts.history.clear-today'));
        $this->post(route('myfinance2::peak-proximity-alerts.dismiss'),
            ['ids' => [$this->_victim['peak_proximity_alert_events']]]);
        $this->post(route('myfinance2::peak-proximity-alerts.dismiss-all'));

        $this->assertSame($before, $this->_victimRows(), 'Someone else deleted or dismissed the alerts');
    }

    public function test_forms_cannot_point_at_someone_elses_accounts_or_currencies(): void
    {
        $account  = $this->_victim['accounts'];
        $currency = $this->_victim['currencies'];
        $forms    = [
            'myfinance2::accounts.store'            => ['currency_id' => $currency],
            'myfinance2::trades.store'              => ['account_id' => $account, 'trade_currency_id' => $currency],
            'myfinance2::dividends.store'           => ['account_id' => $account, 'dividend_currency_id' => $currency],
            'myfinance2::cash-balances.store'       => ['account_id' => $account],
            'myfinance2::ledger-transactions.store' => ['debit_account_id' => $account,
                'credit_account_id' => $account, 'parent_id' => $this->_victim['ledger_transactions']],
            'myfinance2::orders.store'              => ['account_id' => $account, 'trade_currency_id' => $currency],
            'myfinance2::price-alerts.store'        => ['trade_currency_id' => $currency],
        ];

        foreach ($forms as $name => $fields) {
            $this->post(route($name), $fields)->assertSessionHasErrors(array_keys($fields));
        }
        $this->patch(route('myfinance2::trades.close-symbol'), ['account_id' => $account, 'symbol' => self::SYMBOL])
            ->assertSessionHasErrors(['account_id']);
    }

    /**
     * One record of every kind for $owner.
     *
     * @return array<string, int> record id by table
     */
    private function _seedRecordsOf(TestUser $owner): array
    {
        $db  = DB::connection(config('myfinance2.db_connection'));
        $now = now();
        $ids = [];

        Schema::connection(config('myfinance2.db_connection'))->withoutForeignKeyConstraints(
            function () use ($db, $owner, $now, &$ids)
            {
                $row = fn (array $fields) => ['user_id' => $owner->id, 'created_at' => $now, 'updated_at' => $now]
                    + $fields;
                $add = function (string $table, array $fields) use ($db, $row, &$ids)
                {
                    $ids[$table] = (int) $db->table($table)->insertGetId($row($fields));
                };

                $add('currencies', ['iso_code' => 'TSV', 'display_code' => 'TSV', 'name' => self::MARKER,
                    'is_ledger_currency' => 1, 'is_trade_currency' => 1, 'is_dividend_currency' => 1]);
                $currency = $ids['currencies'];
                $add('accounts', ['currency_id' => $currency, 'name' => self::MARKER, 'description' => self::MARKER,
                    'is_ledger_account' => 1, 'is_trade_account' => 1, 'is_dividend_account' => 1]);
                $account = $ids['accounts'];
                $add('trades', ['account_id' => $account, 'trade_currency_id' => $currency, 'action' => 'BUY',
                    'exchange_rate' => 1, 'symbol' => self::SYMBOL, 'quantity' => 10, 'unit_price' => 100, 'fee' => 1,
                    'description' => self::MARKER, 'status' => 'OPEN', 'timestamp' => $now]);
                $add('dividends', ['account_id' => $account, 'dividend_currency_id' => $currency,
                    'exchange_rate' => 1, 'symbol' => self::SYMBOL, 'amount' => 5, 'fee' => 0,
                    'description' => self::MARKER, 'timestamp' => $now]);
                $add('cash_balances', ['account_id' => $account, 'amount' => 100, 'description' => self::MARKER,
                    'timestamp' => $now]);
                $add('ledger_transactions', ['debit_account_id' => $account, 'credit_account_id' => $account,
                    'type' => 'DEBIT', 'exchange_rate' => 1, 'amount' => 100, 'fee' => 0,
                    'description' => self::MARKER, 'timestamp' => $now]);
                $add('watchlist_symbols', ['symbol' => self::SYMBOL, 'description' => self::MARKER,
                    'timestamp' => $now]);
                $add('orders', ['account_id' => $account, 'symbol' => self::SYMBOL, 'action' => 'BUY',
                    'status' => 'PLACED', 'quantity' => 10, 'limit_price' => 100, 'trade_currency_id' => $currency,
                    'exchange_rate' => 1, 'placed_at' => $now, 'description' => self::MARKER]);
                $add('price_alerts', ['symbol' => self::SYMBOL, 'alert_type' => 'PRICE_ABOVE', 'target_price' => 200,
                    'status' => 'ACTIVE', 'trade_currency_id' => $currency, 'notes' => self::MARKER]);
                $add('stock_splits', ['symbol' => self::SYMBOL, 'split_date' => $now->toDateString(),
                    'ratio_numerator' => 2, 'ratio_denominator' => 1, 'notes' => self::MARKER]);
                $add('symbol_tier_overrides', ['symbol' => self::SYMBOL, 'tier_override' => 'GOLD',
                    'note' => self::MARKER]);
                $add('price_alert_notifications', ['price_alert_id' => $ids['price_alerts'],
                    'symbol' => self::SYMBOL, 'current_price' => 201, 'target_price' => 200,
                    'alert_type' => 'PRICE_ABOVE', 'sent_at' => $now, 'status' => 'SENT']);
                $add('peak_proximity_notifications', ['symbol' => self::SYMBOL, 'triggered_windows' => '1Y',
                    'sent_at' => $now, 'status' => 'SENT']);
                $add('dip_buying_notifications', ['effective_dd_pct' => 10, 'target_pct' => 10,
                    'deployed_pct' => 0, 'deployed_eur' => 0, 'pool_amount_eur' => 1000,
                    'suggested_tranche_eur' => 100, 'verdict' => 'DEPLOY', 'trigger' => 'NEW', 'sent_at' => $now,
                    'status' => 'SENT']);
                $add('portfolio_peak_notifications', ['triggered_metrics' => 'change_eur',
                    'triggered_windows' => '1Y', 'sent_at' => $now, 'status' => 'SENT']);
                $add('peak_proximity_alert_events', ['symbol' => self::SYMBOL, 'classification' => 'ACTIONABLE',
                    'severity' => 'HIGH', 'status' => 'OPEN', 'triggered_windows' => '1Y', 'opened_at' => $now,
                    'summary' => self::MARKER]);
            });

        return $ids;
    }

    /**
     * The victim's rows as stored, to prove nothing changed.
     *
     * @return array<string, array<string, mixed>>
     */
    private function _victimRows(): array
    {
        $db   = DB::connection(config('myfinance2.db_connection'));
        $rows = [];
        foreach ($this->_victim as $table => $id) {
            $rows[$table] = (array) $db->table($table)->where('id', $id)->first();
        }

        return $rows;
    }
}
