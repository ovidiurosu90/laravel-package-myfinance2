<?php

namespace ovidiuro\myfinance2;

use Illuminate\Support\ServiceProvider;
use Config;

class MyFinance2ServiceProvider extends ServiceProvider
{
    private $_packageTag = 'myfinance2';

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
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang/', $this->_packageTag);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/' . $this->_packageTag . '.php', $this->_packageTag);
        $this->mergeConfigFrom(__DIR__ . '/config/cashbalances.php', 'cashbalances');
        $this->mergeConfigFrom(__DIR__ . '/config/dividends.php', 'dividends');
        $this->mergeConfigFrom(__DIR__ . '/config/general.php', 'general');
        $this->mergeConfigFrom(__DIR__ . '/config/ledger.php', 'ledger');
        $this->mergeConfigFrom(__DIR__ . '/config/trades.php', 'trades');
        $this->mergeConfigFrom(__DIR__ . '/config/watchlistsymbols.php', 'watchlistsymbols');
        $this->mergeConfigFrom(__DIR__ . '/config/currencies.php', 'currencies');
        $this->loadMigrations();

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views/', $this->_packageTag);
        $this->publishFiles();

        $this->app->make('ovidiuro\myfinance2\App\Http\Controllers\MyFinance2Controller');
        $this->app->singleton(ovidiuro\myfinance2\App\Http\Controllers\MyFinance2Controller\MyFinance2Controller::class, function ()
        {
            return new App\Http\Controllers\MyService2Controller();
        });
        $this->app->alias(MyService2Controller::class, $this->_packageTag);
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
            __DIR__ . '/config/' . $this->_packageTag . '.php' => base_path('config/' . $this->_packageTag . '.php'),
        ], $publishTag);

        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/' . $this->_packageTag),
        ], $publishTag);

        $this->publishes([
            __DIR__ . '/resources/lang' => resource_path('lang/vendor/' . $this->_packageTag),
        ], $publishTag);
    }
}

