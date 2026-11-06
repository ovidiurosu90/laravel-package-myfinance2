<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Scheb\YahooFinanceApi\Results\Quote;
use Scheb\YahooFinanceApi\ValueMapper;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Services\LiveQuote;
use ovidiuro\myfinance2\App\Services\LiveQuoteDecoder;
use ovidiuro\myfinance2\App\Services\MarketUtils;
use ovidiuro\myfinance2\App\Services\OvernightSession;

/**
 * The overnight session (20:00 to 04:00 New York time): Yahoo's overnight fields survive decoding,
 * the overnight price wins only when it is the newest, and a row in that session reads like a
 * pre-market one (day change vs the last regular close, no after-hours figures).
 */
class OvernightSessionTest extends TestCase
{
    // Fri 30 Oct 2026, 16:00 / 19:59 New York (EDT), then Mon 2 Nov 2026, 02:04 New York (EST).
    private const REGULAR_CLOSE = 1793390400;
    private const POST_LAST     = 1793404740;
    private const OVERNIGHT     = 1793603040;

    private function _quote(array $extra = [], array $values = []): LiveQuote
    {
        return new LiveQuote(array_merge([
            'symbol'                     => 'TST.ON',
            'regularMarketPrice'         => 424.30,
            'regularMarketChange'        => -51.21,
            'regularMarketChangePercent' => -10.77,
            'regularMarketTime'          => new \DateTime('@' . self::REGULAR_CLOSE),
            'postMarketPrice'            => 424.18,
            'postMarketChange'           => -0.12,
            'postMarketTime'             => new \DateTime('@' . self::POST_LAST),
        ], $values), array_merge([
            'overnightMarketPrice' => 431.84,
            'overnightMarketTime'  => new \DateTime('@' . self::OVERNIGHT),
        ], $extra));
    }

    public function test_decoder_keeps_the_overnight_fields(): void
    {
        $body = json_encode(['quoteResponse' => ['result' => [[
            'symbol'                       => 'TST.ON',
            'regularMarketPrice'           => 424.30,
            'regularMarketTime'            => self::REGULAR_CLOSE,
            'overnightMarketPrice'         => 431.84,
            'overnightMarketTime'          => self::OVERNIGHT,
            'overnightMarketChange'        => 7.54,
            'overnightMarketChangePercent' => 1.78,
        ]]]]);

        $quotes = (new LiveQuoteDecoder(new ValueMapper()))->transformQuotes($body);

        $this->assertCount(1, $quotes);
        $this->assertInstanceOf(LiveQuote::class, $quotes[0]);
        $this->assertSame('TST.ON', $quotes[0]->getSymbol());
        $this->assertSame(424.30, $quotes[0]->getRegularMarketPrice());
        $this->assertSame(431.84, $quotes[0]->getOvernightMarketPrice());
        $this->assertSame(self::OVERNIGHT, $quotes[0]->getOvernightMarketTime()->getTimestamp());
        $this->assertSame(7.54, $quotes[0]->getOvernightMarketChange());
        $this->assertSame(1.78, $quotes[0]->getOvernightMarketChangePercent());
    }

    public function test_a_live_quote_survives_the_quote_cache(): void
    {
        $quote = unserialize(serialize($this->_quote()));

        $this->assertSame(431.84, $quote->getOvernightMarketPrice());
        $this->assertSame(424.30, $quote->getRegularMarketPrice());
    }

    public function test_overnight_price_wins_when_newest(): void
    {
        [$price, $time] = OvernightSession::latest($this->_quote());

        $this->assertSame(431.84, $price);
        $this->assertSame(self::OVERNIGHT, $time->getTimestamp());
    }

    public function test_a_newer_session_wins_over_overnight(): void
    {
        // Pre-market opened after the last overnight tick.
        $pre = $this->_quote([], [
            'preMarketPrice' => 433.00,
            'preMarketTime'  => new \DateTime('@' . (self::OVERNIGHT + 7200)),
        ]);
        $this->assertNull(OvernightSession::latest($pre));

        // The regular session is trading: Yahoo may still carry the night's last tick.
        $regular = $this->_quote([], ['regularMarketTime' => new \DateTime('@' . (self::OVERNIGHT + 30000))]);
        $this->assertNull(OvernightSession::latest($regular));
    }

    public function test_no_overnight_data_leaves_the_row_alone(): void
    {
        $plain = new Quote(['regularMarketPrice' => 424.30]);
        $this->assertNull(OvernightSession::latest($plain));
        $this->assertNull(OvernightSession::latest(
            $this->_quote(['overnightMarketPrice' => null, 'overnightMarketTime' => null])
        ));

        $row = ['price' => 424.18, 'post_market_price' => 424.18];
        $this->assertSame($row, OvernightSession::apply($row, $plain));
    }

    public function test_overnight_row_reads_like_pre_market(): void
    {
        $row = OvernightSession::apply([
            'price'                     => 424.18,
            'day_change'                => -51.33,
            'day_change_percentage'     => -10.80,
            'post_market_price'         => 424.18,
            'post_market_timestamp'     => new \DateTime('@' . self::POST_LAST),
            'post_market_change'        => -0.12,
            'post_market_day_change'    => true,
            'regular_market_price'      => 424.30,
        ], $this->_quote());

        $this->assertSame(431.84, $row['price']);
        $this->assertSame(431.84, $row['pre_market_price']);
        $this->assertSame(self::OVERNIGHT, $row['quote_timestamp']->getTimestamp());
        $this->assertSame('Europe/Amsterdam', $row['quote_timestamp']->getTimezone()->getName());
        $this->assertTrue($row['overnight_market']);

        // Measured against the last regular close, as Yahoo shows it: +7.54 (+1.78%).
        $this->assertEqualsWithDelta(7.54, $row['day_change'], 0.0001);
        $this->assertEqualsWithDelta(1.777, $row['day_change_percentage'], 0.001);
        $this->assertTrue($row['pre_market_day_change']);
        $this->assertTrue($row['pre_market_day_change_percentage']);

        // The after-hours figures belong to the previous session.
        $this->assertSame([], array_filter(
            array_keys($row),
            fn ($key) => str_starts_with($key, 'post_market_')
        ));
        $this->assertSame(424.30, $row['regular_market_price']);
    }

    /**
     * @return array<string, array{0: string, 1: bool}> New York time => whether the session runs
     */
    public static function newYorkTimes(): array
    {
        return [
            'Sunday 19:59'    => ['2026-11-08 19:59', false],
            'Sunday 20:00'    => ['2026-11-08 20:00', true],
            'Monday 03:59'    => ['2026-11-09 03:59', true],
            'Monday 04:00'    => ['2026-11-09 04:00', false],
            'Monday 12:00'    => ['2026-11-09 12:00', false],
            'Monday 17:00'    => ['2026-11-09 17:00', false], // after hours
            'Thursday 23:00'  => ['2026-11-12 23:00', true],
            'Friday 02:00'    => ['2026-11-06 02:00', true],
            'Friday 20:00'    => ['2026-11-06 20:00', false],
            'Saturday 02:00'  => ['2026-11-07 02:00', false],
            'Saturday 21:00'  => ['2026-11-07 21:00', false],
        ];
    }

    #[DataProvider('newYorkTimes')]
    public function test_overnight_session_hours(string $newYork, bool $open): void
    {
        $this->assertSame($open, OvernightSession::isOpen(
            new \DateTimeImmutable($newYork, new \DateTimeZone('America/New_York'))
        ));
    }

    public function test_only_quote_requests_ask_for_the_overnight_price(): void
    {
        $url = 'https://query1.finance.yahoo.com/v7/finance/quote?crumb=abc&symbols=TST.A%2CTST.B';

        $flagged = FinanceAPI::withOvernightPrice(new Request('GET', $url));
        parse_str($flagged->getUri()->getQuery(), $query);
        $this->assertSame(['crumb' => 'abc', 'symbols' => 'TST.A,TST.B', 'overnightPrice' => 'true'], $query);

        // Other Yahoo calls (history, crumb) are never flagged.
        $chart = 'https://query1.finance.yahoo.com/v8/finance/chart/TST.A?interval=1d';
        $history = FinanceAPI::withOvernightPrice(new Request('GET', $chart));
        $this->assertSame($chart, (string) $history->getUri());
    }

    public function test_overnight_market_state_counts_as_closed(): void
    {
        // The schedule says CLOSED overnight; Yahoo's OVERNIGHT must agree with it, not raise
        // "Inconsistent market status!".
        $this->assertSame('CLOSED', MarketUtils::regularSessionState('OVERNIGHT'));
        foreach (['PREPRE', 'PRE', 'POST', 'POSTPOST', 'CLOSED'] as $state) {
            $this->assertSame('CLOSED', MarketUtils::regularSessionState($state), $state);
        }
        $this->assertSame('OPEN', MarketUtils::regularSessionState('REGULAR'));
    }

    public function test_overnight_session_badge(): void
    {
        $this->assertSame('overnight', MarketUtils::getQuoteSession([
            'overnight_market' => true,
            'pre_market_price' => 431.84,
        ]));
        $this->assertSame('pre', MarketUtils::getQuoteSession(['pre_market_price' => 431.84]));

        $badge = MarketUtils::getSessionBadgeFormatted('overnight');
        $this->assertStringContainsString('>ON</span>', $badge);
        $this->assertStringContainsString('>PRE</span>', MarketUtils::getSessionBadgeFormatted('pre'));
        $this->assertStringContainsString('>AH</span>', MarketUtils::getSessionBadgeFormatted('post'));
        $this->assertSame('', MarketUtils::getSessionBadgeFormatted(null));
    }
}
