<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Scheb\YahooFinanceApi\Results\Quote;

use ovidiuro\myfinance2\App\Services\MarketUtils;
use ovidiuro\myfinance2\App\Services\StaleQuoteService;

/**
 * Pure tests for the stale live-quote detector, in particular the split threshold: Yahoo serves the
 * European exchange feeds with a built-in ~15 minute delay, so a healthy European price is always
 * around 15 minutes old and must not raise the banner, while a US price of the same age must.
 *
 * No DB, no HTTP. The fixtures deliberately use exchange names MarketUtils::getMarketName() does not
 * recognise, so getMarketStatus() short-circuits to UNKNOWN and never shells out to the market
 * schedule script; openness then comes from the quote's own REGULAR market state.
 */
class StaleQuoteServiceTest extends TestCase
{
    private const _BASE_THRESHOLD    = 300;  // 5 minutes, as shipped for real-time feeds
    private const _DELAYED_THRESHOLD = 1800; // 30 minutes, as shipped for Yahoo's delayed feeds

    private StaleQuoteService $_service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->_service = new StaleQuoteService();
        $this->_bindContainer();
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function testEuropeanTimezonesAreDelayedFeedsAndOthersAreNot(): void
    {
        $this->assertTrue(StaleQuoteService::isDelayedFeed('Europe/Amsterdam'));
        $this->assertTrue(StaleQuoteService::isDelayedFeed('Europe/London'));
        $this->assertTrue(StaleQuoteService::isDelayedFeed('Europe/Berlin'));
        $this->assertTrue(StaleQuoteService::isDelayedFeed('Europe/Paris'));

        $this->assertFalse(StaleQuoteService::isDelayedFeed('America/New_York'));
        $this->assertFalse(StaleQuoteService::isDelayedFeed('Asia/Tokyo'));
        $this->assertFalse(StaleQuoteService::isDelayedFeed(''));
        $this->assertFalse(StaleQuoteService::isDelayedFeed(null));
    }

    /**
     * The regression this split fixes: 16 minutes old is simply Yahoo's delay on a European feed,
     * not a frozen one, and used to warn on every page load.
     */
    public function testEuropeanMarketWithinItsOwnThresholdIsNotStale(): void
    {
        $quotes = ['TST.AS' => $this->_quote('XETRA', 'Europe/Amsterdam', 16 * 60)];

        $this->assertSame([], $this->_service->detect($quotes));
    }

    public function testEuropeanMarketPastItsOwnThresholdIsStale(): void
    {
        $quotes = ['TST.AS' => $this->_quote('XETRA', 'Europe/Amsterdam', 35 * 60)];

        $alerts = $this->_service->detect($quotes);

        $this->assertCount(1, $alerts);
        $this->assertTrue($alerts[0]['delayed_feed']);
        $this->assertSame(self::_DELAYED_THRESHOLD, $alerts[0]['threshold_seconds']);
        $this->assertSame('30m', $alerts[0]['threshold_human']);
        $this->assertSame(['TST.AS'], $alerts[0]['symbols']);
    }

    /**
     * The real-time side must keep the tight threshold: a US feed that stops advancing for six
     * minutes is a genuine incident.
     */
    public function testUsMarketKeepsTheBaseThreshold(): void
    {
        $quotes = ['TST' => $this->_quote('NYSE American', 'America/New_York', 6 * 60)];

        $alerts = $this->_service->detect($quotes);

        $this->assertCount(1, $alerts);
        $this->assertFalse($alerts[0]['delayed_feed']);
        $this->assertSame(self::_BASE_THRESHOLD, $alerts[0]['threshold_seconds']);
    }

    /**
     * A European and a US market of the same age must be judged differently in the same pass.
     */
    public function testOnlyTheRealTimeMarketIsFlaggedAtTheSameAge(): void
    {
        $quotes = [
            'TST.AS' => $this->_quote('XETRA', 'Europe/Amsterdam', 16 * 60),
            'TST'    => $this->_quote('NYSE American', 'America/New_York', 16 * 60),
        ];

        $alerts = $this->_service->detect($quotes);

        $this->assertCount(1, $alerts);
        $this->assertSame(['TST'], $alerts[0]['symbols']);
    }

    /**
     * An explicit caller override replaces both thresholds, delayed feeds included.
     */
    public function testExplicitThresholdOverridesTheDelayedFeedThreshold(): void
    {
        $quotes = ['TST.AS' => $this->_quote('XETRA', 'Europe/Amsterdam', 16 * 60)];

        $alerts = $this->_service->detect($quotes, 600);

        $this->assertCount(1, $alerts);
        $this->assertSame(600, $alerts[0]['threshold_seconds']);
    }

    public function testClosedMarketIsNeverStale(): void
    {
        $quotes = ['TST.AS' => $this->_quote('XETRA', 'Europe/Amsterdam', 5 * 3600, 'CLOSED')];

        $this->assertSame([], $this->_service->detect($quotes));
    }

    /**
     * A quote array for a market that is open right now, whose freshest regular-session price is
     * $ageSeconds old.
     */
    private function _quote(
        string $exchange,
        string $timezone,
        int $ageSeconds,
        string $marketState = 'REGULAR'
    ): array
    {
        $quote = new Quote([
            'symbol'                   => 'TST',
            'fullExchangeName'         => $exchange,
            'exchangeTimezoneName'     => $timezone,
            'market'                   => 'test_market',
            'marketState'              => $marketState,
        ]);

        return [
            'marketUtils'              => new MarketUtils($quote),
            'regular_market_timestamp' => (new \DateTime())->setTimestamp(time() - $ageSeconds),
        ];
    }

    /**
     * Minimal container exposing config('myfinance2.stale_quote.*') with the shipped values, a
     * translator for the datetime format and a no-op logger, without booting Laravel.
     */
    private function _bindContainer(): void
    {
        $container = new Container();
        $container->instance('config', new ConfigRepository([
            'myfinance2' => [
                'stale_quote' => [
                    'enabled'                        => true,
                    'threshold_seconds'              => self::_BASE_THRESHOLD,
                    'delayed_feed_threshold_seconds' => self::_DELAYED_THRESHOLD,
                ],
            ],
        ]));
        $container->instance('translator', new class {
            public function get(string $key, array $replace = [], ?string $locale = null): string
            {
                return 'Y-m-d H:i:s';
            }
        });
        $container->instance('log', new class {
            public function __call(string $method, array $args): void
            {
            }
        });

        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }
}
