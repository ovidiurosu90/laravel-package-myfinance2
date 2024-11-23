<?php

namespace ovidiuro\myfinance2\Test;

use ovidiuro\myfinance2\MyFinance2Facade;
use ovidiuro\myfinance2\MyFinance2ServiceProvider;

use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /**
     * Load package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return ovidiuro\myfinance2\MyFinance2ServiceProvider
     */
    protected function getPackageProviders($app): void
    {
        return [MyFinance2ServiceProvider::class];
    }

    /**
     * Load package alias.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageAliases($app): void
    {
        return [
            'myfinance2' => MyFinance2Facade::class,
        ];
    }
}

