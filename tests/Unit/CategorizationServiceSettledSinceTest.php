<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\CategorizationService;
use PHPUnit\Framework\TestCase;

/**
 * The day the settled tier was first seen, as the cron stores it in symbol_tier_states.settled_since.
 */
class CategorizationServiceSettledSinceTest extends TestCase
{
    private const TODAY = '2026-06-01';

    public function test_first_run_starts_today(): void
    {
        $this->assertSame(self::TODAY, CategorizationService::settledSince('GOLD', null, null, self::TODAY));
    }

    public function test_unchanged_tier_keeps_its_day(): void
    {
        $this->assertSame(
            '2026-03-02',
            CategorizationService::settledSince('GOLD', 'GOLD', '2026-03-02', self::TODAY)
        );
    }

    public function test_changed_tier_restarts_today(): void
    {
        $this->assertSame(
            self::TODAY,
            CategorizationService::settledSince('SILVER', 'GOLD', '2026-03-02', self::TODAY)
        );
    }

    public function test_same_tier_without_a_stored_day_starts_today(): void
    {
        // Defensive: a stored tier without a day.
        $this->assertSame(self::TODAY, CategorizationService::settledSince('GOLD', 'GOLD', null, self::TODAY));
    }

    public function test_no_settled_tier_has_no_day(): void
    {
        $this->assertNull(CategorizationService::settledSince(null, 'GOLD', '2026-03-02', self::TODAY));
    }
}
