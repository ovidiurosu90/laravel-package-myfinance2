<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ovidiuro\myfinance2\App\Console\Commands\DipBuyingAlerts;
use ovidiuro\myfinance2\App\Models\DipBuyingNotification;
use ovidiuro\myfinance2\App\Services\DipBuyingPlanService;

/**
 * Unit tests for DipBuyingAlerts::_resolveTrigger — pure trigger logic with no DB, mail or HTTP.
 * All scenarios from the re-entry gap fix are exercised here so the coverage lives alongside the code.
 */
class DipBuyingAlertsTest extends TestCase
{
    private DipBuyingAlerts $_cmd;

    protected function setUp(): void
    {
        $this->_cmd = new DipBuyingAlerts();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function _plan(
        float $targetPct,
        string $verdict = DipBuyingPlanService::VERDICT_BEHIND,
        bool $stallActive = false,
        ?string $anchorDate = '2025-06-01'
    ): array
    {
        return [
            'target_pct'  => $targetPct,
            'verdict'     => $verdict,
            'stall_active' => $stallActive,
            'anchor_date' => $anchorDate,
        ];
    }

    private function _lastNotif(
        float $targetPct,
        string $verdict,
        ?Carbon $anchorDate
    ): DipBuyingNotification
    {
        $notif = new DipBuyingNotification();
        $notif->target_pct = $targetPct;
        $notif->verdict    = $verdict;
        // setRawAttributes with a Carbon bypasses fromDateTime() -> getConnection() -> config()
        $notif->setRawAttributes(
            array_merge($notif->getAttributes(), ['anchor_date' => $anchorDate]),
            true
        );
        return $notif;
    }

    private function _trigger(array $plan, ?DipBuyingNotification $last): ?string
    {
        $m = new ReflectionMethod(DipBuyingAlerts::class, '_resolveTrigger');
        $m->setAccessible(true);
        return $m->invoke($this->_cmd, $plan, $last);
    }

    // -------------------------------------------------------------------------
    // new_episode trigger
    // -------------------------------------------------------------------------

    public function test_new_episode_fires_when_anchor_changes_and_target_positive(): void
    {
        $plan = $this->_plan(targetPct: 30.0, anchorDate: '2025-06-15');
        $last = $this->_lastNotif(
            targetPct: 75.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            anchorDate: Carbon::parse('2025-01-01')
        );

        $this->assertSame('new_episode', $this->_trigger($plan, $last));
    }

    public function test_new_episode_does_not_fire_when_target_is_zero(): void
    {
        // Anchor changed but dd is still under the first band, so target=0.
        $plan = $this->_plan(targetPct: 0.0, anchorDate: '2025-06-15');
        $last = $this->_lastNotif(
            targetPct: 50.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            anchorDate: Carbon::parse('2025-01-01')
        );

        $this->assertNull($this->_trigger($plan, $last));
    }

    public function test_new_episode_does_not_fire_when_last_anchor_is_null(): void
    {
        // Legacy notification row without anchor_date: new_episode guard must be skipped.
        $plan = $this->_plan(targetPct: 30.0, anchorDate: '2025-06-15');
        $last = $this->_lastNotif(
            targetPct: 75.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            anchorDate: null
        );

        // Falls through to existing triggers; target dropped (75 -> 30), so no band_deepened.
        // verdict is already BEHIND, so no crossed_behind. No stall. Should return null.
        $this->assertNull($this->_trigger($plan, $last));
    }

    public function test_new_episode_does_not_fire_when_anchor_unchanged(): void
    {
        $plan = $this->_plan(targetPct: 60.0, anchorDate: '2025-06-01');
        $last = $this->_lastNotif(
            targetPct: 30.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            anchorDate: Carbon::parse('2025-06-01')
        );

        // Same anchor: falls through to band_deepened (60 > 30).
        $this->assertSame('band_deepened', $this->_trigger($plan, $last));
    }

    // -------------------------------------------------------------------------
    // Existing triggers still work within the same episode
    // -------------------------------------------------------------------------

    public function test_band_deepened_fires_when_target_increases_same_episode(): void
    {
        $plan = $this->_plan(targetPct: 60.0, anchorDate: '2025-06-01');
        $last = $this->_lastNotif(
            targetPct: 30.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            anchorDate: Carbon::parse('2025-06-01')
        );

        $this->assertSame('band_deepened', $this->_trigger($plan, $last));
    }

    public function test_crossed_behind_fires_when_verdict_flips_same_episode(): void
    {
        $plan = $this->_plan(
            targetPct: 30.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            anchorDate: '2025-06-01'
        );
        $last = $this->_lastNotif(
            targetPct: 30.0,
            verdict: DipBuyingPlanService::VERDICT_ON_PLAN,
            anchorDate: Carbon::parse('2025-06-01')
        );

        $this->assertSame('crossed_behind', $this->_trigger($plan, $last));
    }

    public function test_stall_fires_when_stall_active_same_episode(): void
    {
        $plan = $this->_plan(
            targetPct: 30.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            stallActive: true,
            anchorDate: '2025-06-01'
        );
        $last = $this->_lastNotif(
            targetPct: 30.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            anchorDate: Carbon::parse('2025-06-01')
        );

        $this->assertSame('stall', $this->_trigger($plan, $last));
    }

    public function test_null_returned_when_nothing_changed(): void
    {
        $plan = $this->_plan(targetPct: 30.0, anchorDate: '2025-06-01');
        $last = $this->_lastNotif(
            targetPct: 30.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            anchorDate: Carbon::parse('2025-06-01')
        );

        $this->assertNull($this->_trigger($plan, $last));
    }

    // -------------------------------------------------------------------------
    // New user (no prior notification)
    // -------------------------------------------------------------------------

    public function test_new_user_fires_crossed_behind_when_in_band(): void
    {
        $plan = $this->_plan(targetPct: 30.0, verdict: DipBuyingPlanService::VERDICT_BEHIND);

        $this->assertSame('crossed_behind', $this->_trigger($plan, null));
    }

    public function test_new_user_fires_stall_when_stall_active(): void
    {
        $plan = $this->_plan(
            targetPct: 30.0,
            verdict: DipBuyingPlanService::VERDICT_BEHIND,
            stallActive: true
        );

        $this->assertSame('stall', $this->_trigger($plan, null));
    }

    public function test_new_user_returns_null_when_no_dip(): void
    {
        $plan = $this->_plan(
            targetPct: 0.0,
            verdict: DipBuyingPlanService::VERDICT_NO_DIP
        );

        $this->assertNull($this->_trigger($plan, null));
    }
}
