<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Services\AlertService;

/**
 * Unit tests for AlertService — pure logic without DB or FinanceAPI calls.
 *
 * Tests the private _isPotentialSplitAnomaly() method via reflection,
 * same pattern as MoversServiceTest::_rankGains.
 */
class AlertServiceTest extends TestCase
{
    private AlertService $_service;
    private ReflectionClass $_reflection;

    protected function setUp(): void
    {
        $this->_service = new AlertService();
        $this->_reflection = new ReflectionClass(AlertService::class);

        // config() helper uses Container::getInstance() (not the Facade application),
        // so we must set the global instance — same approach as Log in MoversServiceTest
        // but extended to cover config().
        $app = new Container();
        $app->instance('config', new class {
            public function get(string $key, mixed $default = null): mixed
            {
                return $default;
            }
        });
        $app->instance('log', new class {
            public function warning(string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        });
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    private function _invokeIsPotentialSplitAnomaly(PriceAlert $alert, float $currentPrice): bool
    {
        $m = $this->_reflection->getMethod('_isPotentialSplitAnomaly');
        $m->setAccessible(true);
        return (bool) $m->invokeArgs($this->_service, [$alert, $currentPrice]);
    }

    private function _makeAlert(string $alertType, float $targetPrice): PriceAlert
    {
        $alert = new PriceAlert();
        $alert->alert_type = $alertType;
        // Bypass the decimal:6 cast setter to store the raw float directly.
        $alert->setRawAttributes(
            array_merge($alert->getAttributes(), ['target_price' => $targetPrice]),
            true
        );
        return $alert;
    }

    /**
     * Core guard: target far above current price (post-split scenario) must be flagged.
     * 300 > 99 × 3 (= 297) → true
     */
    public function test_split_anomaly_price_above_above_ratio_returns_true(): void
    {
        $alert = $this->_makeAlert('PRICE_ABOVE', 300.0);
        $this->assertTrue($this->_invokeIsPotentialSplitAnomaly($alert, 99.0));
    }

    /**
     * Legitimate high-target alert must NOT be blocked.
     * 290 ≤ 100 × 3 (= 300) → false
     */
    public function test_split_anomaly_price_above_within_ratio_returns_false(): void
    {
        $alert = $this->_makeAlert('PRICE_ABOVE', 290.0);
        $this->assertFalse($this->_invokeIsPotentialSplitAnomaly($alert, 100.0));
    }

    /**
     * Inverse formula for PRICE_BELOW: target far below current price must be flagged.
     * 10 < 33 / 3 (= 11) → true
     */
    public function test_split_anomaly_price_below_below_ratio_returns_true(): void
    {
        $alert = $this->_makeAlert('PRICE_BELOW', 10.0);
        $this->assertTrue($this->_invokeIsPotentialSplitAnomaly($alert, 33.0));
    }

    /**
     * Legitimate deep-discount buy alert must not be flagged.
     * 11 ≥ 33 / 3 (= 11) → false (boundary: equal is not an anomaly)
     */
    public function test_split_anomaly_price_below_within_ratio_returns_false(): void
    {
        $alert = $this->_makeAlert('PRICE_BELOW', 11.0);
        $this->assertFalse($this->_invokeIsPotentialSplitAnomaly($alert, 33.0));
    }

    /**
     * Guard clause: currentPrice ≤ 0 must always return false (prevents division-by-zero).
     */
    public function test_split_anomaly_zero_current_price_returns_false(): void
    {
        $alert = $this->_makeAlert('PRICE_ABOVE', 999.0);
        $this->assertFalse($this->_invokeIsPotentialSplitAnomaly($alert, 0.0));
    }
}
