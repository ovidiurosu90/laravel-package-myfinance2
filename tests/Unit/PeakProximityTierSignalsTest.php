<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use ovidiuro\myfinance2\App\Services\PeakProximityTierSignals;
use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use PHPUnit\Framework\TestCase;

/**
 * The drop a deterioration alert belongs to: its first day, which a dismissal holds for.
 */
class PeakProximityTierSignalsTest extends TestCase
{
    public function testDropSinceCountsTodayAndSkipsWeekends(): void
    {
        $tuesday = Carbon::parse('2026-10-13');
        $drop    = ['signal' => SettledTierResolver::SIGNAL_DETERIORATING];

        // Today alone is a one-day drop; two days began yesterday; three began on Friday (the weekend skipped).
        $this->assertSame('2026-10-13', PeakProximityTierSignals::dropSince($drop + ['deteriorating_days' => 1],
            $tuesday)->toDateString());
        $this->assertSame('2026-10-12', PeakProximityTierSignals::dropSince($drop + ['deteriorating_days' => 2],
            $tuesday)->toDateString());
        $this->assertSame('2026-10-09', PeakProximityTierSignals::dropSince($drop + ['deteriorating_days' => 3],
            $tuesday)->toDateString());
        // 20 trading days: four full weeks back.
        $this->assertSame('2026-09-16', PeakProximityTierSignals::dropSince($drop + ['deteriorating_days' => 20],
            $tuesday)->toDateString());
    }

    public function testOnlyADeteriorationIsADrop(): void
    {
        $this->assertTrue(PeakProximityTierSignals::isDrop(['signal' => SettledTierResolver::SIGNAL_DETERIORATING]));
        $this->assertFalse(PeakProximityTierSignals::isDrop(['signal' => SettledTierResolver::SIGNAL_RARE_HIGH]));
    }
}
