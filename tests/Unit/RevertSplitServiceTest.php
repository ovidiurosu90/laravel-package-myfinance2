<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ovidiuro\myfinance2\App\Services\RevertSplitService;

/**
 * Unit tests for RevertSplitService — pure computation without DB or model saves.
 *
 * Tests the private helper and the inverse-math patterns via reflection:
 *   _buildAnnotation, and the bcmath operations used in _revertTrades / _revertAlerts.
 *
 * These cover the highest-risk logic: correct inversion of the math applied
 * by ApplySplitService and clean annotation stripping.
 */
class RevertSplitServiceTest extends TestCase
{
    private RevertSplitService $_service;
    private ReflectionClass $_reflection;

    protected function setUp(): void
    {
        $this->_service    = new RevertSplitService();
        $this->_reflection = new ReflectionClass(RevertSplitService::class);
    }

    private function _invoke(string $method, array $args = []): mixed
    {
        $m = $this->_reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->_service, $args);
    }

    // -----------------------------------------------------------------------
    // _buildAnnotation
    // -----------------------------------------------------------------------

    /**
     * Annotation format must match exactly what ApplySplitService appended,
     * so that LIKE-queries and str_replace can locate it.
     */
    public function test_build_annotation_format(): void
    {
        $result = $this->_invoke('_buildAnnotation', ['25:1', '2026-04-06']);
        $this->assertSame('[Split 25:1 applied 2026-04-06]', $result);
    }

    // -----------------------------------------------------------------------
    // Annotation stripping (str_replace + trim pattern used in _revertTrades / _revertAlerts)
    // -----------------------------------------------------------------------

    /**
     * Annotation appended at the end is fully removed, leaving no trailing whitespace.
     */
    public function test_annotation_stripped_from_end_of_description(): void
    {
        $annotation  = $this->_invoke('_buildAnnotation', ['25:1', '2026-04-06']);
        $description = 'Initial purchase ' . $annotation;
        $result      = trim(str_replace($annotation, '', $description));

        $this->assertSame('Initial purchase', $result);
    }

    /**
     * Description that was originally empty ends up empty again after stripping.
     */
    public function test_annotation_stripped_from_empty_description(): void
    {
        $annotation = $this->_invoke('_buildAnnotation', ['25:1', '2026-04-06']);
        $result     = trim(str_replace($annotation, '', $annotation));

        $this->assertSame('', $result);
    }

    // -----------------------------------------------------------------------
    // Roundtrip invariant: apply (×N, ÷N) then revert (÷N, ×N) = original
    // -----------------------------------------------------------------------

    /**
     * BKNG case: 7 shares @ $4,760 survive an apply→revert cycle exactly.
     * Verifies that bcdiv/bcmul at the stored precisions round-trip cleanly
     * for whole-number quantities and prices.
     */
    public function test_roundtrip_quantity_and_price_whole_numbers(): void
    {
        $origQty   = '7';
        $origPrice = '4760';
        $ratio     = 25;

        // Apply
        $appliedQty   = bcmul($origQty, (string) $ratio, 8);
        $appliedPrice = bcdiv($origPrice, (string) $ratio, 4);

        // Revert
        $revertedQty   = bcdiv($appliedQty, (string) $ratio, 8);
        $revertedPrice = bcmul($appliedPrice, (string) $ratio, 4);

        $this->assertSame($origQty . '.00000000', $revertedQty);
        $this->assertSame($origPrice . '.0000', $revertedPrice);
    }

    /**
     * Cost basis is preserved across the full apply→revert cycle.
     * qty × price must equal the original total both after apply and after revert.
     */
    public function test_roundtrip_preserves_cost_basis(): void
    {
        $origQty   = '7';
        $origPrice = '4760';
        $ratio     = 25;

        $origCost = bcmul($origQty, $origPrice, 2);

        // Apply
        $appliedQty   = bcmul($origQty, (string) $ratio, 8);
        $appliedPrice = bcdiv($origPrice, (string) $ratio, 4);
        $appliedCost  = bcmul($appliedQty, $appliedPrice, 2);

        // Revert
        $revertedQty   = bcdiv($appliedQty, (string) $ratio, 8);
        $revertedPrice = bcmul($appliedPrice, (string) $ratio, 4);
        $revertedCost  = bcmul($revertedQty, $revertedPrice, 2);

        $this->assertSame($origCost, $appliedCost);
        $this->assertSame($origCost, $revertedCost);
    }
}
