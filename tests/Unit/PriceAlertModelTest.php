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
}
