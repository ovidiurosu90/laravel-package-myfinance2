<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use ovidiuro\myfinance2\MyFinance2ServiceProvider;
use ovidiuro\myfinance2\Tests\Support\HostEnvironment;
use ovidiuro\myfinance2\Tests\Support\TestUser;

/**
 * Base of the package's feature tests: a Laravel app (orchestra/testbench) with only this package, on the host app's
 * databases, the local copy of production (HostEnvironment). So every test rolls back both connections, writes only
 * synthetic rows (symbols TST.*) and acts as an existing user, never saved (TestUser). The leftover-rows check
 * (phpunit.xml) fails a run that leaves anything behind.
 *
 * The gates are open (the host decides who gets in; the package's own default is tested with $opensTheGates off).
 */
abstract class TestCase extends OrchestraTestCase
{
    use DatabaseTransactions;

    /**
     * Defines myfinance2.access and myfinance2.manage as allowed for everyone, as a host app would for its users.
     */
    protected bool $opensTheGates = true;

    public static function setUpBeforeClass(): void
    {
        // Before the app's config is loaded, so its database settings read the host's
        HostEnvironment::load();
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->opensTheGates) {
            Gate::define('myfinance2.access', fn () => true);
            Gate::define('myfinance2.manage', fn () => true);
        }
    }

    protected function tearDown(): void
    {
        Auth::forgetGuards();
        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MyFinance2ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        foreach (HostEnvironment::connections() as $name => $settings) {
            $app['config']->set("database.connections.{$name}", $settings);
        }
        $app['config']->set('database.default', array_key_first(HostEnvironment::connections()));
        // The requests' cookies are encrypted; a throwaway key per run, never the host's
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('myfinance2.user_model', TestUser::class);
        $app['config']->set('auth.providers.users.model', TestUser::class);
    }

    /**
     * @return list<?string>
     */
    public function connectionsToTransact(): array
    {
        return [null, config('myfinance2.db_connection')];
    }

    /**
     * The host's first user, to act as (read-only); skips the test when there is none.
     */
    protected function existingUser(): TestUser
    {
        $user = TestUser::query()->orderBy('id')->first();
        if ($user === null) {
            $this->markTestSkipped("Needs at least one user in the host app's database");
        }

        return $user;
    }

    /**
     * A user with no MyFinance2 data (an id above every user's), never saved: nothing of anyone's shows on its pages.
     * $which gives another one (e.g. a victim next to an attacker).
     */
    protected function userWithoutData(int $which = 0): TestUser
    {
        $user         = new TestUser();
        $user->id     = (int) TestUser::query()->max('id') + 1000 + $which;
        $user->exists = true;

        return $user;
    }
}
