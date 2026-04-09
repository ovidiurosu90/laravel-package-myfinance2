<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Models\StockSplit;

/**
 * Unit tests for StockSplit model — pure logic without DB.
 */
class StockSplitModelTest extends TestCase
{
    private function _makeSplit(int $numerator, int $denominator = 1): StockSplit
    {
        $split = new StockSplit();
        $split->setRawAttributes([
            'ratio_numerator'   => $numerator,
            'ratio_denominator' => $denominator,
        ], true);
        return $split;
    }

    /**
     * Standard 25:1 forward split label.
     */
    public function test_get_ratio_label_25_to_1(): void
    {
        $this->assertSame('25:1', $this->_makeSplit(25)->getRatioLabel());
    }

    /**
     * Other supported ratios return the correct label.
     */
    public function test_get_ratio_label_common_ratios(): void
    {
        $this->assertSame('3:1', $this->_makeSplit(3)->getRatioLabel());
        $this->assertSame('5:1', $this->_makeSplit(5)->getRatioLabel());
        $this->assertSame('10:1', $this->_makeSplit(10)->getRatioLabel());
        $this->assertSame('20:1', $this->_makeSplit(20)->getRatioLabel());
    }

    /**
     * Denominator is preserved in the label (always 1 for forward splits, but tested generically).
     */
    public function test_get_ratio_label_uses_actual_denominator(): void
    {
        $this->assertSame('4:1', $this->_makeSplit(4, 1)->getRatioLabel());
    }

    // -----------------------------------------------------------------------
    // isReverted
    // -----------------------------------------------------------------------

    /**
     * A split with no reverted_at is not reverted.
     */
    public function test_is_reverted_false_when_reverted_at_is_null(): void
    {
        $split = new StockSplit();
        $split->setRawAttributes(['reverted_at' => null], true);
        $this->assertFalse($split->isReverted());
    }

    /**
     * A split with reverted_at set is considered reverted.
     * Store a Carbon instance directly to bypass fromDateTime() → getConnection() → config().
     */
    public function test_is_reverted_true_when_reverted_at_is_set(): void
    {
        $split = new StockSplit();
        $split->setRawAttributes(
            array_merge($split->getAttributes(), ['reverted_at' => Carbon::now()]),
            true
        );
        $this->assertTrue($split->isReverted());
    }
}
