<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Mockery;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\Results\Quote;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Services\UnlistedSymbol;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * Yahoo answers a symbol it doesn't know with an empty Quote. FinanceAPI drops it (it used to reach
 * get_timezone_offset() with a null timezone and throw), logging an error unless the symbol is a configured
 * unlisted one without its prefix (the trade form's Listed toggle strips it), which is expected to have no quote.
 * The Yahoo client is mocked: no network calls, nothing persisted (the empty quote never gets that far).
 */
class FinanceApiNoMarketDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'myfinance2.trades.unlisted'     => 'UNLISTED',
            'myfinance2.trades.unlisted_fmv' => ['UNLISTED_TSTU' => ['symbol_name' => 'Test', 'quotes' => []]],
        ]);
    }

    public function test_prefixed_for_finds_only_configured_unlisted_symbols(): void
    {
        $this->assertSame('UNLISTED_TSTU', UnlistedSymbol::prefixedFor('TSTU'));
        $this->assertNull(UnlistedSymbol::prefixedFor('TSTX'));
    }

    public function test_empty_quote_for_bare_unlisted_symbol_is_dropped_with_info(): void
    {
        Log::spy();

        $this->assertNull($this->_apiReturningEmptyQuote('TSTU')->getQuote('TSTU', false, false));

        Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains($message, 'UNLISTED_TSTU'))->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_empty_quote_for_listed_symbol_is_dropped_with_error(): void
    {
        Log::spy();

        $this->assertNull($this->_apiReturningEmptyQuote('TSTX')->getQuote('TSTX', false, false));

        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'TSTX'))->once();
    }

    public function test_get_quotes_drops_empty_quotes_and_keeps_the_others(): void
    {
        Log::spy();
        $valid = new Quote([
            'symbol'               => 'TSTA',
            'regularMarketPrice'   => 10.0,
            'regularMarketTime'    => new \DateTime('2026-10-27 16:00:00'),
            'exchangeTimezoneName' => 'America/New_York',
        ]);
        $client = Mockery::mock(ApiClient::class);
        $client->shouldReceive('getQuotes')->andReturn([new Quote(['symbol' => 'TSTU']), $valid]);
        $api = Mockery::mock(FinanceAPI::class)->makePartial();
        $api->shouldReceive('getClient')->andReturn($client);

        $this->assertSame([$valid], $api->getQuotes(['TSTU', 'TSTA'], false, false));
        Log::shouldNotHaveReceived('error');
    }

    private function _apiReturningEmptyQuote(string $symbol): FinanceAPI
    {
        $client = Mockery::mock(ApiClient::class);
        $client->shouldReceive('getQuote')->andReturn(new Quote(['symbol' => $symbol]));
        $api = Mockery::mock(FinanceAPI::class)->makePartial();
        $api->shouldReceive('getClient')->andReturn($client);
        return $api;
    }
}
