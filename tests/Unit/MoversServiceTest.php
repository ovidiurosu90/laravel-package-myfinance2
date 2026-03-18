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
