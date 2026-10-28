<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use ovidiuro\myfinance2\MyFinance2ServiceProvider;
use ovidiuro\myfinance2\Tests\Support\TestUser;
use ovidiuro\myfinance2\Tests\TestCase;
use ReflectionMethod;

/**
 * What the package does for a new account without the app naming it: the Verified event (the app fires it when an
 * account is activated) seeds the starter currencies for users that pass myfinance2.access, once; the finance menu
 * joins the app's navbar registry (myfinance2.navbar_registry), once. Acts as a user with no data, never saved; the
 * currencies are rolled back.
 */
class OnboardingTest extends TestCase
{
    public function test_verifying_a_finance_user_seeds_the_starter_currencies_once(): void
    {
        $user = $this->userWithoutData();

        $this->_verify($user);
        $this->_verify($user);

        $this->assertSame(['EUR', 'UNK', 'USD'], $this->_currencies($user->id));
    }

    public function test_verifying_a_user_without_finance_access_seeds_nothing(): void
    {
        Gate::define('myfinance2.access', fn () => false);
        $user = $this->userWithoutData();

        $this->_verify($user);

        $this->assertSame([], $this->_currencies($user->id));
    }

    public function test_the_finance_menu_joins_the_navbar_registry_once(): void
    {
        config(['myfinance2.navbar_registry' => 'tst.menus', 'tst.menus' => ['tst::partials.menu']]);
        $register = new ReflectionMethod(MyFinance2ServiceProvider::class, '_registerNavbarMenu');
        $provider = new MyFinance2ServiceProvider($this->app);

        // Twice, as with a cached config that already lists it
        $register->invoke($provider);
        $register->invoke($provider);

        $this->assertSame(['tst::partials.menu', 'myfinance2::partials.nav'], config('tst.menus'));
    }

    private function _verify(TestUser $user): void
    {
        // The user is never saved, so the users foreign key (when on) would refuse its currencies
        Schema::connection(config('myfinance2.db_connection'))->withoutForeignKeyConstraints(
            fn () => event(new Verified($user)));
    }

    /**
     * @return list<string>
     */
    private function _currencies(int $userId): array
    {
        return DB::connection(config('myfinance2.db_connection'))->table('currencies')
            ->where('user_id', $userId)->orderBy('iso_code')->pluck('iso_code')->all();
    }
}
