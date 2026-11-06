<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Models\PriceAlert;

/**
 * Unit tests for PriceAlert model methods.
 *
 * Tests canFire() and isActive() on bare (unsaved) PriceAlert instances.
 * Pure attribute checks — no DB, no Facade, no container needed.
 */
class PriceAlertModelTest extends TestCase
{
    private function _makeAlert(string $status, ?Carbon $expiresAt = null): PriceAlert
    {
        $alert = new PriceAlert();
        $alert->status = $status;

        if ($expiresAt !== null) {
            // Store the Carbon instance directly in raw attributes to bypass
            // fromDateTime(), which calls getConnection() → getConnectionName()
            // → config() and would need a container. The getter's asDateTime()
            // handles CarbonInterface immediately without needing getDateFormat().
            $alert->setRawAttributes(
                array_merge($alert->getAttributes(), ['expires_at' => $expiresAt]),
                true
            );
        }

        return $alert;
    }

    /**
     * PAUSED alert must never fire, regardless of expiry.
     */
    public function test_can_fire_returns_false_when_paused(): void
    {
        $alert = $this->_makeAlert('PAUSED');
        $this->assertFalse($alert->canFire());
    }

    /**
     * ACTIVE alert with a past expires_at must not fire (already expired).
     */
    public function test_can_fire_returns_false_when_expired(): void
    {
        $alert = $this->_makeAlert('ACTIVE', Carbon::yesterday());
        $this->assertFalse($alert->canFire());
    }

    /**
     * ACTIVE alert with no expiry must fire.
     */
    public function test_can_fire_returns_true_when_active_and_no_expiry(): void
    {
        $alert = $this->_makeAlert('ACTIVE');
        $this->assertTrue($alert->canFire());
    }

    /**
     * ACTIVE alert with a future expiry must fire.
     */
    public function test_can_fire_returns_true_when_active_with_future_expiry(): void
    {
        $alert = $this->_makeAlert('ACTIVE', Carbon::tomorrow());
        $this->assertTrue($alert->canFire());
    }

    /**
     * isActive() must return true for ACTIVE status.
     */
    public function test_is_active_returns_true_for_active_status(): void
    {
        $alert = $this->_makeAlert('ACTIVE');
        $this->assertTrue($alert->isActive());
    }

    /**
     * isActive() must return false for PAUSED status.
     */
    public function test_is_active_returns_false_for_paused_status(): void
    {
        $alert = $this->_makeAlert('PAUSED');
        $this->assertFalse($alert->isActive());
    }

    private function _targetAlert(string $alertType, float $target): PriceAlert
    {
        $alert = new PriceAlert();
        $alert->alert_type   = $alertType;
        $alert->target_price = $target;

        return $alert;
    }

    /**
     * Reached is the rule the engine fires on: at or past the target in the alert's direction.
     */
    public function test_is_reached_at_follows_the_alert_direction(): void
    {
        $below = $this->_targetAlert('PRICE_BELOW', 100.0);
        $this->assertTrue($below->isReachedAt(99.0));
        $this->assertTrue($below->isReachedAt(100.0));
        $this->assertFalse($below->isReachedAt(101.0));

        $above = $this->_targetAlert('PRICE_ABOVE', 100.0);
        $this->assertTrue($above->isReachedAt(101.0));
        $this->assertTrue($above->isReachedAt(100.0));
        $this->assertFalse($above->isReachedAt(99.0));
    }

    /**
     * "To go" and "past" are both measured on the current price: 610.25 -> 596.20 is 2.30% to go,
     * 261.37 past a 309.38 target is 18.37% past (not 15.5%, measured on the target).
     */
    public function test_target_distance_is_measured_on_the_current_price(): void
    {
        $toGo = $this->_targetAlert('PRICE_BELOW', 596.20)->targetDistance(610.25);
        $this->assertFalse($toGo['reached']);
        $this->assertEqualsWithDelta(14.05, $toGo['amount'], 0.0001);
        $this->assertEqualsWithDelta(2.3023, $toGo['pct'], 0.0001);

        $past = $this->_targetAlert('PRICE_BELOW', 309.38)->targetDistance(261.37);
        $this->assertTrue($past['reached']);
        $this->assertEqualsWithDelta(48.01, $past['amount'], 0.0001);
        $this->assertEqualsWithDelta(18.3686, $past['pct'], 0.0001);

        $this->assertNull($this->_targetAlert('PRICE_ABOVE', 10.0)->targetDistance(0.0)['pct']);
    }
}
