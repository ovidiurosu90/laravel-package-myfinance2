<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use Illuminate\Support\Facades\Cache;

use ovidiuro\myfinance2\Tests\TestCase;

use ovidiuro\myfinance2\App\Console\Commands\FinanceApiCronQuotesTrait;

/**
 * refreshQuotes() warns only about a symbol missing from Yahoo's batch for a while: one dropped for a few minutes
 * (a few US stocks right at the US open) is transient. No DB, no network: the trait on its own, with the test's
 * array cache.
 */
class QuoteMissesFeatureTest extends TestCase
{
    private const KEY = 'finance_api_quotes_last_fetched';

    private object $_cron;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(self::KEY);
        $this->_cron = new class
        {
            use FinanceApiCronQuotesTrait;
        };
    }

    /**
     * @return array{0: string[], 1: string[]}
     */
    private function _split(array $symbols, array $fetched, array $unableToFetch): array
    {
        $method = new \ReflectionMethod($this->_cron, '_splitQuoteMisses');
        $method->setAccessible(true);

        return $method->invoke($this->_cron, $symbols, $fetched, $unableToFetch);
    }

    public function test_a_symbol_fetched_a_minute_ago_is_a_transient_miss(): void
    {
        $this->_split(['TST.A', 'TST.B'], ['TST.A', 'TST.B'], []);

        [$missing, $transient] = $this->_split(['TST.A', 'TST.B'], ['TST.A'], ['TST.B']);

        $this->assertSame([], $missing);
        $this->assertSame(['TST.B'], $transient);
    }

    public function test_a_symbol_missing_for_over_fifteen_minutes_is_a_warning(): void
    {
        Cache::put(self::KEY, ['TST.A' => time(), 'TST.B' => time() - 16 * 60], 3600);

        [$missing, $transient] = $this->_split(['TST.A', 'TST.B'], ['TST.A'], ['TST.B']);

        $this->assertSame(['TST.B'], $missing);
        $this->assertSame([], $transient);
    }

    public function test_a_symbol_never_fetched_is_a_warning(): void
    {
        [$missing, $transient] = $this->_split(['TST.A', 'TST.NEW'], ['TST.A'], ['TST.NEW']);

        $this->assertSame(['TST.NEW'], $missing);
        $this->assertSame([], $transient);
    }

    public function test_symbols_no_longer_used_are_dropped_from_the_cache(): void
    {
        Cache::put(self::KEY, ['TST.OLD' => time()], 3600);

        $this->_split(['TST.A'], ['TST.A'], []);

        $this->assertSame(['TST.A'], array_keys(Cache::get(self::KEY)));
    }
}
