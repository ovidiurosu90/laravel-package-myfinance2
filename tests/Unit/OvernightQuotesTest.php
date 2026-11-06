<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Scheb\YahooFinanceApi\Results\Quote;

use ovidiuro\myfinance2\App\Services\LiveQuote;
use ovidiuro\myfinance2\App\Services\OvernightQuotes;
use ovidiuro\myfinance2\App\Services\OvernightSession;

/**
 * No session's latest price is lost: the unflagged quotes keep their after-hours prices, and during the overnight
 * session the US ones also get the overnight fields of a second, flagged fetch. Symbols that do not trade overnight,
 * or a failed flagged fetch, keep the after-hours price as before.
 */
class OvernightQuotesTest extends TestCase
{
    // Fri 30 Oct 2026, 16:00 / 19:59 New York (EDT), then Mon 2 Nov 2026, 02:04 New York (EST).
    private const REGULAR_CLOSE = 1793390400;
    private const POST_LAST     = 1793404740;
    private const OVERNIGHT     = 1793603040;

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();

        parent::tearDown();
    }

    private static function _inSession(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . self::OVERNIGHT);
    }

    /** An unflagged quote: regular and after-hours prices, no overnight fields. */
    private static function _unflagged(string $symbol, string $timezone = 'America/New_York'): LiveQuote
    {
        return new LiveQuote([
            'symbol'               => $symbol,
            'exchangeTimezoneName' => $timezone,
            'regularMarketPrice'   => 100.0,
            'regularMarketTime'    => new \DateTime('@' . self::REGULAR_CLOSE),
            'postMarketPrice'      => 101.0,
            'postMarketTime'       => new \DateTime('@' . self::POST_LAST),
        ]);
    }

    /** A flagged quote: Yahoo leaves out the after-hours fields and adds the overnight ones when it has them. */
    private static function _flagged(string $symbol, ?float $overnightPrice): LiveQuote
    {
        return new LiveQuote([
            'symbol'               => $symbol,
            'exchangeTimezoneName' => 'America/New_York',
            'regularMarketPrice'   => 100.0,
            'regularMarketTime'    => new \DateTime('@' . self::REGULAR_CLOSE),
        ], $overnightPrice === null ? [] : [
            'overnightMarketPrice' => $overnightPrice,
            'overnightMarketTime'  => new \DateTime('@' . self::OVERNIGHT),
        ]);
    }

    public function test_outside_the_session_nothing_more_is_fetched(): void
    {
        $quotes = [self::_unflagged('TST.A')];
        $fetch  = function (): array
        {
            $this->fail('No flagged fetch outside the overnight session');
        };

        $afterHours = new \DateTimeImmutable('2026-11-06 22:00:00', new \DateTimeZone('UTC'));
        $this->assertSame($quotes, OvernightQuotes::add($quotes, $fetch, $afterHours));
    }

    public function test_in_the_session_us_quotes_get_the_overnight_price_and_keep_the_after_hours_one(): void
    {
        $asked  = null;
        $quotes = OvernightQuotes::add(
            [self::_unflagged('TST.A'), self::_unflagged('TST.B'), self::_unflagged('TST.AS', 'Europe/Amsterdam')],
            function (array $symbols) use (&$asked): array
            {
                $asked = $symbols;
                return [self::_flagged('TST.A', 103.5), self::_flagged('TST.B', null)];
            },
            self::_inSession()
        );

        // Only the US symbols are fetched again.
        $this->assertSame(['TST.A', 'TST.B'], $asked);
        $this->assertCount(3, $quotes);

        // Trades overnight: both prices kept, the overnight one is the latest.
        $this->assertSame(103.5, $quotes[0]->getOvernightMarketPrice());
        $this->assertSame(101.0, $quotes[0]->getPostMarketPrice());
        $this->assertSame(103.5, OvernightSession::latest($quotes[0])[0]);

        // No overnight trading: the after-hours price stays the latest (the flagged response alone would lose it).
        $this->assertNull($quotes[1]->getOvernightMarketPrice());
        $this->assertSame(101.0, $quotes[1]->getPostMarketPrice());
        $this->assertNull(OvernightSession::latest($quotes[1]));

        $this->assertSame(101.0, $quotes[2]->getPostMarketPrice());
    }

    public function test_a_failed_flagged_fetch_keeps_the_quotes(): void
    {
        Log::swap(new NullLogger());
        $quotes = [self::_unflagged('TST.A')];

        $this->assertSame($quotes, OvernightQuotes::add(
            $quotes,
            function (): array
            {
                throw new \RuntimeException('Yahoo is down');
            },
            self::_inSession()
        ));
    }

    public function test_quotes_without_overnight_support_are_left_alone(): void
    {
        // A plain library Quote (e.g. from an older cache entry) and a non-US quote: nothing to fetch.
        $quotes = [new Quote(['symbol' => 'TST.Q']), self::_unflagged('TST.AS', 'Europe/Amsterdam')];
        $fetch  = function (): array
        {
            $this->fail('Nothing to fetch for these quotes');
        };

        $this->assertSame($quotes, OvernightQuotes::add($quotes, $fetch, self::_inSession()));
    }
}
