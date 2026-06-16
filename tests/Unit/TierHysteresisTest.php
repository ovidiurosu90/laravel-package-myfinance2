<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\TierCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Locks down the boundary-chatter defences added on top of the plain tier thresholds:
 *   - getTierWithHysteresis: a dead-band so a value on a tier line keeps the tier it last
 *     settled on instead of flipping as it wiggles.
 *   - isBorderline: the cosmetic "near a tier line" flag.
 */
class TierHysteresisTest extends TestCase
{
    private TierCalculationService $tiers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tiers = new TierCalculationService();
    }

    public function test_no_previous_tier_uses_plain_threshold(): void
    {
        // First sighting: nothing to be sticky about, so the plain bucket applies.
        $this->assertSame(TierCalculationService::SILVER, $this->tiers->getTierWithHysteresis(9.8, null));
        $this->assertSame(TierCalculationService::GOLD, $this->tiers->getTierWithHysteresis(10.2, null));
    }

    public function test_stays_gold_inside_the_dead_band_when_dropping(): void
    {
        // Was Gold, value dips just under 10 but not past the 0.5pp band: keep Gold, no flip.
        $this->assertSame(
            TierCalculationService::GOLD,
            $this->tiers->getTierWithHysteresis(9.8, TierCalculationService::GOLD)
        );
    }

    public function test_drops_to_silver_only_past_the_lower_band(): void
    {
        // Clears the band on the way down (<= 9.5): the drop is real, accept Silver.
        $this->assertSame(
            TierCalculationService::SILVER,
            $this->tiers->getTierWithHysteresis(9.4, TierCalculationService::GOLD)
        );
    }

    public function test_stays_silver_inside_the_dead_band_when_rising(): void
    {
        // Was Silver, value nudges just over 10 but not past the band: keep Silver, no flip.
        $this->assertSame(
            TierCalculationService::SILVER,
            $this->tiers->getTierWithHysteresis(10.3, TierCalculationService::SILVER)
        );
    }

    public function test_rises_to_gold_only_past_the_upper_band(): void
    {
        // Clears the band on the way up (>= 10.5): accept Gold.
        $this->assertSame(
            TierCalculationService::GOLD,
            $this->tiers->getTierWithHysteresis(10.6, TierCalculationService::SILVER)
        );
    }

    public function test_non_adjacent_jump_is_accepted_outright(): void
    {
        // A clear, large move past a whole tier has no chatter to damp; take it immediately even
        // though the value sits just inside a band.
        $this->assertSame(
            TierCalculationService::GOLD,
            $this->tiers->getTierWithHysteresis(10.2, TierCalculationService::BRONZE)
        );
    }

    public function test_borderline_flags_values_near_a_line(): void
    {
        $this->assertTrue($this->tiers->isBorderline(9.8));   // near the 10 line
        $this->assertTrue($this->tiers->isBorderline(15.4));  // near the 15 line
        $this->assertTrue($this->tiers->isBorderline(0.3));   // near the 0 line
    }

    public function test_borderline_is_false_mid_band_and_for_null(): void
    {
        $this->assertFalse($this->tiers->isBorderline(7.5));  // comfortably mid-Silver
        $this->assertFalse($this->tiers->isBorderline(null));
    }
}
