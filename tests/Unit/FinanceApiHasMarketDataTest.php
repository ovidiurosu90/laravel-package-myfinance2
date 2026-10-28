<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Scheb\YahooFinanceApi\Results\Quote;
use ovidiuro\myfinance2\App\Services\FinanceAPI;

/**
 * Unit tests for FinanceAPI::hasMarketData: pure logic, no DB or Laravel boot.
 *
 * Yahoo answers a symbol it doesn't know with an empty Quote. Passing it on made
 * get_timezone_offset() throw a TypeError on its null exchange timezone (trade form lookup,
 * tier reading), so FinanceAPI drops such quotes before caching or returning them
 * (FinanceApiNoMarketDataTest covers how the drop is logged).
 */
class FinanceApiHasMarketDataTest extends TestCase
{
    public function test_quote_with_time_and_timezone_has_market_data(): void
    {
        $quote = new Quote([
            'symbol'               => 'TST.A',
            'regularMarketPrice'   => 10.5,
            'regularMarketTime'    => new \DateTime('2026-10-27 16:00:00'),
            'exchangeTimezoneName' => 'America/New_York',
        ]);

        $this->assertTrue(FinanceAPI::hasMarketData($quote));
    }

    /**
     * The empty shell Yahoo returns for an unknown symbol: only the symbol is set.
     */
    public function test_empty_quote_has_no_market_data(): void
    {
        $this->assertFalse(FinanceAPI::hasMarketData(new Quote(['symbol' => 'TST.A'])));
    }

    public function test_quote_without_exchange_timezone_has_no_market_data(): void
    {
        $quote = new Quote([
            'symbol'            => 'TST.A',
            'regularMarketTime' => new \DateTime('2026-10-27 16:00:00'),
        ]);

        $this->assertFalse(FinanceAPI::hasMarketData($quote));
    }
}
