<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Models\Order;

/**
 * Order::isLimitReachedAt() / limitDistance() on bare (unsaved) orders: a BUY's limit is reached at
 * or below it, a SELL's at or above it, and the distance is measured on the current price, the way
 * PriceAlert::targetDistance() measures an alert's target.
 */
class OrderLimitDistanceTest extends TestCase
{
    private function _order(string $action, ?float $limit): Order
    {
        $order = new Order();
        $order->action      = $action;
        $order->limit_price = $limit;

        return $order;
    }

    public function test_buy_limit_is_reached_at_or_below_it(): void
    {
        $buy = $this->_order('BUY', 125.0);

        $this->assertFalse($buy->isLimitReachedAt(126.28));
        $this->assertTrue($buy->isLimitReachedAt(125.0));
        $this->assertTrue($buy->isLimitReachedAt(120.0));
    }

    public function test_sell_limit_is_reached_at_or_above_it(): void
    {
        $sell = $this->_order('SELL', 200.0);

        $this->assertFalse($sell->isLimitReachedAt(190.0));
        $this->assertTrue($sell->isLimitReachedAt(200.0));
        $this->assertTrue($sell->isLimitReachedAt(210.0));
    }

    public function test_without_a_limit_nothing_is_reached(): void
    {
        $this->assertFalse($this->_order('BUY', null)->isLimitReachedAt(1.0));
    }

    public function test_distance_is_measured_on_the_current_price(): void
    {
        $toGo = $this->_order('BUY', 125.0)->limitDistance(126.28);
        $this->assertFalse($toGo['reached']);
        $this->assertEqualsWithDelta(1.28, $toGo['amount'], 0.0001);
        $this->assertEqualsWithDelta(1.0136, $toGo['pct'], 0.0001);

        $past = $this->_order('SELL', 200.0)->limitDistance(210.0);
        $this->assertTrue($past['reached']);
        $this->assertEqualsWithDelta(10.0, $past['amount'], 0.0001);
        $this->assertEqualsWithDelta(4.7619, $past['pct'], 0.0001);

        $this->assertNull($this->_order('BUY', 10.0)->limitDistance(0.0)['pct']);
    }
}
