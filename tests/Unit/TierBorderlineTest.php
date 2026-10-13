<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\TierCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * The cosmetic "near a tier line" flag (isBorderline): it marks a soft label without changing the tier.
 */
class TierBorderlineTest extends TestCase
{
    private TierCalculationService $tiers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tiers = new TierCalculationService();
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
