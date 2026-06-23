<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ovidiuro\myfinance2\App\Services\FinanceUtils;

/**
 * Unit tests for FinanceUtils — pure logic without DB or FinanceAPI calls.
 */
class FinanceUtilsTest extends TestCase
{
    private function _cumulative(
        float $regularChange,
        float $postMarketChange,
        ?float $previousClose,
        float $regularChangePercent
    ): array
    {
        $m = new ReflectionMethod(FinanceUtils::class, '_cumulativePostMarketDayChange');
        $m->setAccessible(true);
        return $m->invoke(
            null,
            $regularChange,
            $postMarketChange,
            $previousClose,
            $regularChangePercent
        );
    }

    /**
     * Post-market day change cumulates the regular-session move with the after-hours move,
     * and the percentage is recomputed against the previous close (not summed). AMD example:
     * +14.26 (regular) then -5.13 (after hours), previous close 537.37.
     */
    public function test_cumulative_post_market_uses_previous_close_base(): void
    {
        $result = $this->_cumulative(14.26, -5.13, 537.37, 2.65);

        // Dollar change is purely additive: 14.26 + (-5.13).
        $this->assertEqualsWithDelta(9.13, $result['change'], 0.0001);
        // Percentage recomputed against previous close: 9.13 / 537.37 * 100 ≈ 1.699.
        $this->assertEqualsWithDelta(1.699, $result['percentage'], 0.001);
    }

    /**
     * Adding the two raw percentages (regular + after-hours) is only an approximation
     * because they use different bases; the recomputed value must differ from that sum.
     */
    public function test_cumulative_percentage_is_not_the_raw_sum(): void
    {
        $result = $this->_cumulative(14.26, -5.13, 537.37, 2.65);

        $rawSum = 2.65 + (-0.93); // 1.72, the naive additive estimate
        $this->assertNotEqualsWithDelta($rawSum, $result['percentage'], 0.001);
    }

    /**
     * A negative after-hours move can flip a positive regular day into a smaller gain
     * or a loss; the cumulative figure tracks the full day.
     */
    public function test_cumulative_handles_after_hours_outweighing_regular(): void
    {
        $result = $this->_cumulative(2.0, -5.0, 100.0, 2.04);

        $this->assertEqualsWithDelta(-3.0, $result['change'], 0.0001);
        $this->assertEqualsWithDelta(-3.0, $result['percentage'], 0.0001);
    }

    /**
     * When the previous close is unavailable, the percentage falls back to the
     * regular-session percentage rather than producing a division by zero.
     */
    public function test_cumulative_falls_back_to_regular_pct_without_previous_close(): void
    {
        $result = $this->_cumulative(14.26, -5.13, null, 2.65);

        $this->assertEqualsWithDelta(9.13, $result['change'], 0.0001);
        $this->assertEqualsWithDelta(2.65, $result['percentage'], 0.0001);
    }
}
