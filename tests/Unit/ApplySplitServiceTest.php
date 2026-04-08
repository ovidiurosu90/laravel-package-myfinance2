<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ovidiuro\myfinance2\App\Services\ApplySplitService;

/**
 * Unit tests for ApplySplitService — pure computation without DB or model saves.
 *
 * Tests the private calculation methods via reflection:
 *   _computeNewQuantity, _computeNewPrice, _computeNewAlertPrice, _buildAnnotation
 *
 * These methods implement the core split math and are the highest-risk logic in the feature.
 */
class ApplySplitServiceTest extends TestCase
{
    private ApplySplitService $_service;
    private ReflectionClass $_reflection;

    protected function setUp(): void
    {
        $this->_service    = new ApplySplitService();
        $this->_reflection = new ReflectionClass(ApplySplitService::class);
    }

    private function _invoke(string $method, array $args = []): mixed
    {
        $m = $this->_reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->_service, $args);
    }

    // -----------------------------------------------------------------------
    // _computeNewQuantity
    // -----------------------------------------------------------------------

    /**
     * BKNG real-world case: 1 share × 25 = 25 shares.
     */
    public function test_compute_new_quantity_whole_share_25_to_1(): void
    {
        $result = $this->_invoke('_computeNewQuantity', ['1', 25]);
        $this->assertSame('25.00000000', $result);
    }

    /**
     * Multi-share case: 7 shares × 25 = 175 shares (BKNG Schwab position).
     */
    public function test_compute_new_quantity_seven_shares_25_to_1(): void
    {
        $result = $this->_invoke('_computeNewQuantity', ['7', 25]);
        $this->assertSame('175.00000000', $result);
    }

    /**
     * Fractional quantity preserved with 8-decimal precision.
     */
    public function test_compute_new_quantity_fractional_share(): void
    {
        $result = $this->_invoke('_computeNewQuantity', ['2.5', 4]);
        $this->assertSame('10.00000000', $result);
    }

    /**
     * Common ratios: 3:1, 5:1, 10:1, 20:1.
     */
    public function test_compute_new_quantity_common_ratios(): void
    {
        $this->assertSame('3.00000000', $this->_invoke('_computeNewQuantity', ['1', 3]));
        $this->assertSame('5.00000000', $this->_invoke('_computeNewQuantity', ['1', 5]));
        $this->assertSame('10.00000000', $this->_invoke('_computeNewQuantity', ['1', 10]));
        $this->assertSame('20.00000000', $this->_invoke('_computeNewQuantity', ['1', 20]));
    }

    // -----------------------------------------------------------------------
    // _computeNewPrice
    // -----------------------------------------------------------------------

    /**
     * BKNG real-world case: ~$4,200 ÷ 25 = $168.00 (4-decimal trade price).
     */
    public function test_compute_new_price_bkng_25_to_1(): void
    {
        $result = $this->_invoke('_computeNewPrice', ['4200', 25]);
        $this->assertSame('168.0000', $result);
    }

    /**
     * Price is stored with 4 decimal places — verify precision is maintained.
     */
    public function test_compute_new_price_maintains_4_decimal_precision(): void
    {
        // 168.04 ÷ 25 = 6.7216
        $result = $this->_invoke('_computeNewPrice', ['168.04', 25]);
        $this->assertSame('6.7216', $result);
    }

    /**
     * Cost basis invariant: qty × (price ÷ ratio) × ratio == original total cost.
     * 7 shares @ $4,760 → 175 shares @ $190.40 → same $33,320 total.
     */
    public function test_compute_new_price_preserves_cost_basis(): void
    {
        $oldQty   = '7';
        $oldPrice = '4760';
        $ratio    = 25;

        $newQty   = $this->_invoke('_computeNewQuantity', [$oldQty, $ratio]);
        $newPrice = $this->_invoke('_computeNewPrice', [$oldPrice, $ratio]);

        $oldCost = bcmul($oldQty, $oldPrice, 2);
        $newCost = bcmul($newQty, $newPrice, 2);

        $this->assertSame($oldCost, $newCost);
    }

    // -----------------------------------------------------------------------
    // _computeNewAlertPrice
    // -----------------------------------------------------------------------

    /**
     * Alert prices use 6-decimal precision (more than trade prices).
     */
    public function test_compute_new_alert_price_uses_6_decimal_precision(): void
    {
        // 4200 ÷ 25 = 168.000000
        $result = $this->_invoke('_computeNewAlertPrice', ['4200', 25]);
        $this->assertSame('168.000000', $result);
    }

    /**
     * Non-even division is truncated at 6 decimals by bcmath (no rounding).
     * 100 ÷ 3 = 33.333333...
     */
    public function test_compute_new_alert_price_truncates_at_6_decimals(): void
    {
        $result = $this->_invoke('_computeNewAlertPrice', ['100', 3]);
        $this->assertSame('33.333333', $result);
    }

    // -----------------------------------------------------------------------
    // _buildAnnotation
    // -----------------------------------------------------------------------

    /**
     * Annotation format must be exactly "[Split {label} applied {date}]".
     */
    public function test_build_annotation_format(): void
    {
        $result = $this->_invoke('_buildAnnotation', ['25:1', '2026-04-06']);
        $this->assertSame('[Split 25:1 applied 2026-04-06]', $result);
    }

    /**
     * Annotation works for other common ratios.
     */
    public function test_build_annotation_other_ratio(): void
    {
        $result = $this->_invoke('_buildAnnotation', ['10:1', '2025-01-15']);
        $this->assertSame('[Split 10:1 applied 2025-01-15]', $result);
    }

    /**
     * Two annotations concatenated correctly (trade updated twice in theory).
     * Verifies the trim() join pattern produces no double-spaces.
     */
    public function test_annotation_appended_to_existing_description(): void
    {
        $existing   = 'Initial purchase';
        $annotation = $this->_invoke('_buildAnnotation', ['25:1', '2026-04-06']);
        $result     = trim($existing . ' ' . $annotation);

        $this->assertSame('Initial purchase [Split 25:1 applied 2026-04-06]', $result);
    }

    /**
     * Empty description: annotation appended without leading space.
     */
    public function test_annotation_appended_to_empty_description(): void
    {
        $annotation = $this->_invoke('_buildAnnotation', ['25:1', '2026-04-06']);
        $result     = trim('' . ' ' . $annotation);

        $this->assertSame('[Split 25:1 applied 2026-04-06]', $result);
    }
}
