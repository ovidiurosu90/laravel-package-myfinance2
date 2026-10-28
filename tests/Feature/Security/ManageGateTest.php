<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use ovidiuro\myfinance2\MyFinance2ServiceProvider;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * Each area's create / edit forms ask myfinance2.manage for that area (the app decides who may, e.g. by permission):
 * with the gate open to one area only, that area's forms get past authorization (the empty form then fails
 * validation) and every other area's are refused, saving nothing. Acts as a user with no data, never saved.
 */
class ManageGateTest extends TestCase
{
    /**
     * Each area's form submissions: [method, route name, table its rows land in].
     */
    private const FORMS = [
        'accounts'         => [['POST', 'myfinance2::accounts.store', 'accounts'],
                               ['PUT', 'myfinance2::accounts.update', 'accounts']],
        'alerts'           => [['POST', 'myfinance2::price-alerts.store', 'price_alerts'],
                               ['PUT', 'myfinance2::price-alerts.update', 'price_alerts']],
        'cashbalances'     => [['POST', 'myfinance2::cash-balances.store', 'cash_balances'],
                               ['PUT', 'myfinance2::cash-balances.update', 'cash_balances']],
        'currencies'       => [['POST', 'myfinance2::currencies.store', 'currencies'],
                               ['PUT', 'myfinance2::currencies.update', 'currencies']],
        'dividends'        => [['POST', 'myfinance2::dividends.store', 'dividends'],
                               ['PUT', 'myfinance2::dividends.update', 'dividends']],
        'ledger'           => [['POST', 'myfinance2::ledger-transactions.store', 'ledger_transactions'],
                               ['PUT', 'myfinance2::ledger-transactions.update', 'ledger_transactions'],
                               ['GET', 'myfinance2::get-currency-exchange-gain-estimate', 'ledger_transactions']],
        'orders'           => [['POST', 'myfinance2::orders.store', 'orders'],
                               ['PUT', 'myfinance2::orders.update', 'orders']],
        'splits'           => [['POST', 'myfinance2::stock-splits.store', 'stock_splits']],
        'trades'           => [['POST', 'myfinance2::trades.store', 'trades'],
                               ['PUT', 'myfinance2::trades.update', 'trades'],
                               ['PATCH', 'myfinance2::trades.close-symbol', 'trades']],
        'watchlistsymbols' => [['POST', 'myfinance2::watchlist-symbols.store', 'watchlist_symbols'],
                               ['PUT', 'myfinance2::watchlist-symbols.update', 'watchlist_symbols']],
    ];

    public function test_every_managed_area_has_its_forms_listed(): void
    {
        $this->assertEqualsCanonicalizing(MyFinance2ServiceProvider::MANAGED_AREAS, array_keys(self::FORMS));
    }

    public function test_each_form_asks_the_manage_gate_for_its_own_area(): void
    {
        $user = $this->userWithoutData();
        $this->actingAs($user);

        foreach (MyFinance2ServiceProvider::MANAGED_AREAS as $allowed) {
            Gate::define('myfinance2.manage', fn ($user, string $area) => $area === $allowed);

            foreach (self::FORMS as $area => $forms) {
                foreach ($forms as [$method, $name]) {
                    $status = $this->_submit($method, $name)->getStatusCode();
                    if ($area === $allowed) {
                        $this->assertNotSame(403, $status, "{$method} {$name} refused with '{$area}' allowed");
                    } else {
                        $this->assertSame(403, $status, "{$method} {$name} ({$area}) opened with only '{$allowed}'");
                    }
                }
            }
        }
        $this->_assertNothingSavedFor($user->id);
    }

    private function _submit(string $method, string $name): TestResponse
    {
        return $this->call($method, str_ends_with($name, '.update') ? route($name, 0) : route($name));
    }

    private function _assertNothingSavedFor(int $userId): void
    {
        $connection = config('myfinance2.db_connection');
        foreach (array_unique(array_merge(...array_values(array_map(fn ($forms) => array_column($forms, 2),
            self::FORMS)))) as $table) {
            $this->assertSame(0, DB::connection($connection)->table($table)->where('user_id', $userId)->count(),
                "A refused or invalid form saved a row in {$table}");
        }
    }
}
