<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use ovidiuro\myfinance2\MyFinance2ServiceProvider;
use ovidiuro\myfinance2\Tests\TestCase;
use ReflectionMethod;

/**
 * MyFinance2 keeps all of its config under myfinance2.* (one file per area, merged as myfinance2.<area>), so it claims
 * no generic top-level names such as 'trades' or 'alerts', and an app overrides one key of an area without losing the
 * others. Only this test's config changes, reset after each test.
 */
class ConfigNamespaceTest extends TestCase
{
    public function test_every_area_is_under_myfinance2_and_none_at_the_top_level(): void
    {
        foreach (MyFinance2ServiceProvider::CONFIG_AREAS as $area) {
            $this->assertNull(config($area), "config('{$area}') is set; it belongs under myfinance2.{$area}");
            $this->assertNotEmpty(config("myfinance2.{$area}"), "config('myfinance2.{$area}') is empty");
        }
    }

    public function test_an_app_overrides_one_key_of_an_area_and_keeps_the_others(): void
    {
        config(['myfinance2.trades' => ['delisted_symbols' => ['TST.APP']]]);

        (new ReflectionMethod(MyFinance2ServiceProvider::class, 'mergeConfigFrom'))
            ->invoke(new MyFinance2ServiceProvider($this->app), __DIR__ . '/../../src/config/trades.php',
                'myfinance2.trades');

        $this->assertSame(['TST.APP'], config('myfinance2.trades.delisted_symbols'));
        $this->assertNotEmpty(config('myfinance2.trades.actions'));
    }
}
