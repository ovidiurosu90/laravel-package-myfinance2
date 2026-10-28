<?php

namespace ovidiuro\myfinance2;

use Config;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use ovidiuro\myfinance2\App\Listeners\SeedStarterCurrencies;

class MyFinance2ServiceProvider extends ServiceProvider
{
    private $_packageTag = 'myfinance2';

    /**
     * The gates MyFinance2 checks; the host app defines them (README, "Authorization"):
     * - myfinance2.access: every MyFinance2 page and AJAX endpoint, and the navbar menu
     * - myfinance2.manage: the create / edit forms of one area, passed as the argument (MANAGED_AREAS)
     */
    public const GATES = ['myfinance2.access', 'myfinance2.manage'];

    /**
     * The areas myfinance2.manage is asked about.
     */
    public const MANAGED_AREAS = [
        'accounts', 'alerts', 'cashbalances', 'currencies', 'dividends', 'ledger', 'orders', 'splits', 'trades',
        'watchlistsymbols',
    ];

    /**
     * The config files besides myfinance2.php (src/config/<area>.php), each read as config('myfinance2.<area>.…').
     */
    public const CONFIG_AREAS = ['general', 'ledger', 'trades', 'watchlistsymbols', 'alerts'];

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Config::set('database.connections.myfinance2_mysql',
            Config::get($this->_packageTag . '.connections.myfinance2_mysql'));
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang/',
            $this->_packageTag);
        $this->defineDefaultGates();
        Event::listen(Verified::class, SeedStarterCurrencies::class);
        $this->_registerNavbarMenu();
    }

    /**
     * Adds the navbar menu partial to the host's list of menu partials, when the host names that list in
     * 'myfinance2.navbar_registry' (README, "Navbar"). Skipped when already listed (a cached config keeps it).
     *
     * @return void
     */
    private function _registerNavbarMenu()
    {
        $registry = config($this->_packageTag . '.navbar_registry');
        $partial  = $this->_packageTag . '::partials.nav';
        if (empty($registry) || in_array($partial, (array) config($registry, []), true)) {
            return;
        }
        config()->push($registry, $partial);
    }

    /**
     * Deny by default, to keep things safe: until the host app defines the gates, nobody can open MyFinance2, so
     * installing the package never exposes anyone's finance data. The host's own Gate::define() replaces these (its
     * AuthServiceProvider boots after the package providers). The denial says what is missing, so a new install
     * shows why it is blocked instead of a bare 403.
     *
     * @return void
     */
    public function defineDefaultGates()
    {
        foreach (self::GATES as $ability) {
            if (Gate::has($ability)) {
                continue;
            }
            Gate::define($ability, fn ($user, ...$arguments) => Response::deny(
                "MyFinance2 access is not configured: the app has to define the '{$ability}' gate"
                . ' (see the ovidiuro/myfinance2 README, "Authorization").'));
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/' . $this->_packageTag . '.php',
            $this->_packageTag);
        // After the main file: each area's file under its own key (myfinance2.trades, ...), so the package claims
        // no generic top-level config names. The app overrides a key of an area in its config/myfinance2.php
        // (e.g. 'trades' => ['price_overrides' => [...]]).
        foreach (self::CONFIG_AREAS as $area) {
            $this->mergeConfigFrom(__DIR__ . '/config/' . $area . '.php', $this->_packageTag . '.' . $area);
        }
        $this->loadMigrations();

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views/', $this->_packageTag);
        $this->publishFiles();

        $this->commands([
            \ovidiuro\myfinance2\App\Console\Commands\FinanceApiCron::class,
            \ovidiuro\myfinance2\App\Console\Commands\StatsCron::class,
            \ovidiuro\myfinance2\App\Console\Commands\SuggestAlerts::class,
            \ovidiuro\myfinance2\App\Console\Commands\PeakProximityAlerts::class,
            \ovidiuro\myfinance2\App\Console\Commands\DipBuyingAlerts::class,
            \ovidiuro\myfinance2\App\Console\Commands\DipBuyingBacktest::class,
            \ovidiuro\myfinance2\App\Console\Commands\PortfolioPeakAlerts::class,
            \ovidiuro\myfinance2\App\Console\Commands\TierHistoryBackfill::class,
            \ovidiuro\myfinance2\App\Console\Commands\TierSignalBacktest::class,
            \ovidiuro\myfinance2\App\Console\Commands\OrderWarningReview::class,
            \ovidiuro\myfinance2\App\Console\Commands\TierSnapshotBackfill::class,
        ]);
    }

    private function loadMigrations()
    {
        if (config($this->_packageTag . '.defaultMigrations.enabled')) {
            $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        }
    }

    /**
     * @return void
     */
    private function publishFiles()
    {
        $publishTag = $this->_packageTag;

        $this->publishes([
            __DIR__ . '/config/' . $this->_packageTag . '.php' =>
                base_path('config/' . $this->_packageTag . '.php'),
        ], $publishTag);

        $this->publishes([
            __DIR__ . '/resources/views' =>
                resource_path('views/vendor/' . $this->_packageTag),
        ], $publishTag);

        $this->publishes([
            __DIR__ . '/resources/lang' =>
                resource_path('lang/vendor/' . $this->_packageTag),
        ], $publishTag);

        // The prebuilt JS / CSS (dist/, README "Assets"); 'laravel-assets' is the tag Laravel apps republish on every
        // composer update (post-update-cmd), so they stay current without a step of their own
        $this->publishes([
            __DIR__ . '/../dist' => public_path('vendor/' . $this->_packageTag),
        ], [$publishTag . '-assets', 'laravel-assets']);
    }
}
