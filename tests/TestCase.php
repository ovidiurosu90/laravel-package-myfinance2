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
     * @return array
     */
    protected function getPackageProviders($app)
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
    protected function getPackageAliases($app)
    {
        return [
            'myfinance2' => MyFinance2Facade::class,
        ];
    }
}

