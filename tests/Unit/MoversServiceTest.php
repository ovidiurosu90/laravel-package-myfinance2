<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ovidiuro\myfinance2\App\Services\MoversService;

/**
 * Unit tests for MoversService — pure logic without DB or FinanceAPI calls.
 */
class MoversServiceTest extends TestCase
{
    private MoversService $_service;
    private ReflectionClass $_reflection;

    protected function setUp(): void
    {
        $this->_service = new MoversService();
        $this->_reflection = new ReflectionClass(MoversService::class);

        // Provide a no-op logger so Log::warning() calls don't throw in unit tests.
        $app = new Container();
        $app->instance('log', new class {
            public function warning(string $message, array $context = []): void
            {
            }
        });
        Facade::setFacadeApplication($app);
    }

    private function _invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->_reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->_service, $args);
    }

    private function _gain(string $symbol, float $eur): array
    {
        return [
            'symbol' => $symbol, 'gain_eur' => $eur, 'gain_percentage' => $eur / 10,
            'inception_label' => null,
        ];
    }

    /**
     * Core ranking: worst 5 losers and best 5 gainers are selected correctly.
     */
    public function test_rank_gains_selects_top_5_in_correct_order(): void
    {
        $gains = [
            'A' => $this->_gain('A', -500), 'B' => $this->_gain('B', -200),
            'C' => $this->_gain('C', -50),  'D' => $this->_gain('D', -10),
            'E' => $this->_gain('E', 100),  'F' => $this->_gain('F', 800),
            'G' => $this->_gain('G', 1200), 'H' => $this->_gain('H', 50),
            'I' => $this->_gain('I', -300), 'J' => $this->_gain('J', 400),
            'K' => $this->_gain('K', 600),  'L' => $this->_gain('L', -5),
        ];

        $result = $this->_invokePrivate('_rankGains', [$gains]);

        $this->assertCount(5, $result['losers']);
        $this->assertCount(5, $result['gainers']);
        $this->assertSame(['A', 'I', 'B', 'C', 'D'], array_column($result['losers'], 'symbol'));
        $this->assertSame(['G', 'F', 'K', 'J', 'E'], array_column($result['gainers'], 'symbol'));
    }

    /**
     * Fewer than TOP_N movers must not pad or crash — show as many as available.
     */
    public function test_rank_gains_fewer_than_top_n_handled_gracefully(): void
    {
        $gains = [
            'A' => $this->_gain('A', -300),
            'B' => $this->_gain('B', 150),
        ];

        $result = $this->_invokePrivate('_rankGains', [$gains]);

        $this->assertCount(1, $result['losers']);
        $this->assertCount(1, $result['gainers']);
    }

    /**
     * Zero or near-zero day_change positions must be excluded from today's movers.
     */
    public function test_compute_today_movers_filters_zero_day_change(): void
    {
        $positions = [
            'AMD'   => ['quantity' => 10, 'trade_currency' => 'EUR'],
            'ADYEN' => ['quantity' => 5,  'trade_currency' => 'EUR'],
        ];
        $quotes = [
            'AMD'   => ['day_change' => 2.50, 'day_change_percentage' => 3.2, 'price' => 80.0],
            'ADYEN' => ['day_change' => 0.0,  'day_change_percentage' => 0.0, 'price' => 700.0],
        ];

        // total: 10*80 + 5*700 = 4300
        $result = $this->_invokePrivate('_computeTodayMovers', [$positions, $quotes, new \DateTime(), 4300.0]);

        $allSymbols = array_merge(
            array_column($result['losers'], 'symbol'),
            array_column($result['gainers'], 'symbol')
        );
        $this->assertNotContains('ADYEN', $allSymbols);
        $this->assertContains('AMD', $allSymbols);
    }

    /**
     * EUR positions: gain_eur = day_change * quantity (no FX conversion).
     * Verifies the core gain formula and that gain_percentage comes from the quote.
     * Also verifies portfolio_total_eur and portfolio_total_pct are set correctly.
     */
    public function test_compute_today_movers_gain_formula_eur_position(): void
    {
        $positions = ['ASML' => ['quantity' => 4, 'trade_currency' => 'EUR']];
        $quotes    = ['ASML' => ['day_change' => 5.0, 'day_change_percentage' => 2.5, 'price' => 200.0]];

        // total portfolio = 4 shares × €200 = €800; gain = 5.0 × 4 = €20 = 2.5% of portfolio
        $result = $this->_invokePrivate('_computeTodayMovers', [$positions, $quotes, new \DateTime(), 800.0]);

        $this->assertCount(1, $result['gainers']);
        $this->assertEqualsWithDelta(20.0, $result['gainers'][0]['gain_eur'], 0.001);
        $this->assertEqualsWithDelta(2.5, $result['gainers'][0]['gain_percentage'], 0.001);
        $this->assertArrayHasKey('portfolio_total_eur', $result);
        $this->assertEqualsWithDelta(20.0, $result['portfolio_total_eur'], 0.001);
        $this->assertEqualsWithDelta(2.5, $result['portfolio_total_pct'], 0.001);
    }

    /**
     * A position that has no matching entry in $quotes must be silently skipped.
     */
    public function test_compute_today_movers_skips_position_without_quote(): void
    {
        $positions = [
            'KNOWN'   => ['quantity' => 10, 'trade_currency' => 'EUR'],
            'MISSING' => ['quantity' => 10, 'trade_currency' => 'EUR'],
        ];
        $quotes = ['KNOWN' => ['day_change' => 3.0, 'day_change_percentage' => 1.5, 'price' => 50.0]];

        $result = $this->_invokePrivate('_computeTodayMovers', [$positions, $quotes, new \DateTime(), 500.0]);

        $allSymbols = array_merge(
            array_column($result['losers'], 'symbol'),
            array_column($result['gainers'], 'symbol')
        );
        $this->assertNotContains('MISSING', $allSymbols);
    }

    /**
     * Build a quote carrying a regular-market timestamp (and optional extra fields) for the
     * staleness tests. $regularDate is a 'Y-m-d' string.
     */
    private function _timedQuote(float $dayChange, float $price, string $regularDate, array $extra = []): array
    {
        return array_merge([
            'day_change'               => $dayChange,
            'day_change_percentage'    => $dayChange,
            'price'                    => $price,
            'regular_market_timestamp' => new \DateTime($regularDate . ' 17:30:00'),
        ], $extra);
    }

    /**
     * _effectiveQuoteDate dates pre-market by its own timestamp, post-market by the regular
     * session it extends, falls back to the regular-market timestamp, and returns null when no
     * timestamp is present.
     */
    public function test_effective_quote_date_precedence(): void
    {
        // Pre-market change wins over the regular timestamp.
        $pre = $this->_invokePrivate('_effectiveQuoteDate', [[
            'pre_market_day_change'    => true,
            'pre_market_timestamp'     => new \DateTime('2026-06-16 08:00:00'),
            'regular_market_timestamp' => new \DateTime('2026-06-15 17:30:00'),
        ]]);
        $this->assertSame('2026-06-16', $pre);

        // Post-market belongs to the regular session it extends, even when its own timestamp
        // rolls into the next calendar day (US after-hours ends ~02:00 Europe/Amsterdam). The
        // session date is the regular-market date, so yesterday's after-hours is not "today".
        $post = $this->_invokePrivate('_effectiveQuoteDate', [[
            'post_market_day_change'   => true,
            'post_market_timestamp'    => new \DateTime('2026-06-17 01:59:59'),
            'regular_market_timestamp' => new \DateTime('2026-06-16 22:00:00'),
        ]]);
        $this->assertSame('2026-06-16', $post);

        // Post-market with no regular timestamp falls back to the post-market timestamp.
        $postNoRegular = $this->_invokePrivate('_effectiveQuoteDate', [[
            'post_market_day_change' => true,
            'post_market_timestamp'  => new \DateTime('2026-06-17 01:59:59'),
        ]]);
        $this->assertSame('2026-06-17', $postNoRegular);

        // Falls back to the regular-market timestamp.
        $regular = $this->_invokePrivate('_effectiveQuoteDate', [[
            'regular_market_timestamp' => new \DateTime('2026-06-15 17:30:00'),
        ]]);
        $this->assertSame('2026-06-15', $regular);

        // No timestamp at all -> null (cannot prove staleness).
        $this->assertNull($this->_invokePrivate('_effectiveQuoteDate', [[]]));
    }

    /**
     * _resolveSessionDate returns today when any non-crypto symbol already trades today,
     * otherwise the most recent completed session; crypto is ignored in the decision.
     */
    public function test_resolve_session_date(): void
    {
        $today = '2026-06-16';

        // A non-crypto symbol with a today session -> card is today.
        $this->assertSame($today, $this->_invokePrivate('_resolveSessionDate', [[
            'AMD'   => ['date' => '2026-06-16', 'is_crypto' => false],
            'ADYEN' => ['date' => '2026-06-15', 'is_crypto' => false],
        ], $today]));

        // No non-crypto symbol open yet -> fall back to the latest completed session.
        $this->assertSame('2026-06-15', $this->_invokePrivate('_resolveSessionDate', [[
            'AMD'   => ['date' => '2026-06-15', 'is_crypto' => false],
            'ADYEN' => ['date' => '2026-06-15', 'is_crypto' => false],
        ], $today]));

        // Crypto trading today must not force the card to today while equities are still closed.
        $this->assertSame('2026-06-15', $this->_invokePrivate('_resolveSessionDate', [[
            'BTC-EUR' => ['date' => '2026-06-16', 'is_crypto' => true],
            'ADYEN'   => ['date' => '2026-06-15', 'is_crypto' => false],
        ], $today]));
    }

    /**
     * Mixed markets: when one market already trades today, a position still on yesterday's close
     * is excluded and the card is labelled today.
     */
    public function test_compute_today_movers_excludes_stale_when_another_market_is_today(): void
    {
        $currentDate = new \DateTime('2026-06-16 10:30:00');
        $positions = [
            'AMD'   => ['quantity' => 10, 'trade_currency' => 'EUR'],
            'ADYEN' => ['quantity' => 5,  'trade_currency' => 'EUR'],
        ];
        $quotes = [
            'AMD'   => $this->_timedQuote(2.50, 80.0, '2026-06-16'),  // open today
            'ADYEN' => $this->_timedQuote(9.00, 700.0, '2026-06-15'), // still yesterday's close
        ];

        $result = $this->_invokePrivate('_computeTodayMovers', [$positions, $quotes, $currentDate, 4300.0]);

        $allSymbols = array_merge(
            array_column($result['losers'], 'symbol'),
            array_column($result['gainers'], 'symbol')
        );
        $this->assertContains('AMD', $allSymbols);
        $this->assertNotContains('ADYEN', $allSymbols);
        $this->assertSame('Jun 16', $result['date_label']);
    }

    /**
     * Before any market opens, every position carries yesterday's change: nothing is dropped and
     * the card is relabelled to the last completed session so yesterday is not shown as "today".
     */
    public function test_compute_today_movers_falls_back_to_last_session_before_open(): void
    {
        $currentDate = new \DateTime('2026-06-16 08:00:00');
        $positions = [
            'AMD'     => ['quantity' => 10, 'trade_currency' => 'EUR'],
            'ADYEN'   => ['quantity' => 5,  'trade_currency' => 'EUR'],
            'BTC-EUR' => ['quantity' => 1,  'trade_currency' => 'EUR'],
        ];
        $quotes = [
            'AMD'     => $this->_timedQuote(2.50, 80.0, '2026-06-15'),
            'ADYEN'   => $this->_timedQuote(9.00, 700.0, '2026-06-15'),
            // Crypto keeps trading; it is included regardless of the equity session date.
            'BTC-EUR' => $this->_timedQuote(120.0, 60000.0, '2026-06-16'),
        ];

        $result = $this->_invokePrivate('_computeTodayMovers', [$positions, $quotes, $currentDate, 70000.0]);

        $allSymbols = array_merge(
            array_column($result['losers'], 'symbol'),
            array_column($result['gainers'], 'symbol')
        );
        $this->assertContains('AMD', $allSymbols);
        $this->assertContains('ADYEN', $allSymbols);
        $this->assertContains('BTC-EUR', $allSymbols);
        $this->assertSame('Jun 15', $result['date_label']);
    }

    /**
     * EUR currency must return a rate of exactly 1.0 — no DB lookup, no conversion.
     */
    public function test_get_eur_rate_returns_1_for_eur(): void
    {
        $rate = $this->_invokePrivate('_getEurRate', ['EUR', new \DateTime()]);
        $this->assertSame(1.0, $rate);
    }


    /**
     * Cache keys must encode user ID to ensure per-user isolation.
     */
    public function test_cache_key_is_scoped_per_user(): void
    {
        $key1 = $this->_invokePrivate('_getCacheKey', [1, 'today']);
        $key2 = $this->_invokePrivate('_getCacheKey', [2, 'today']);

        $this->assertSame('movers:1:today', $key1);
        $this->assertNotSame($key1, $key2);
    }

}
