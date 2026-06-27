<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\Stats;

/**
 * Unit tests for Stats::_isStatFromToday — pure logic, no DB or Laravel boot.
 *
 * It gates which stats_today rows may become today_last. Stale intraday rows
 * left in stats_today (until app:stats-cron prunes them) must be excluded, so
 * the chart formatter does not re-date them to today and draw a phantom point
 * at a stale price on a non-trading day.
 */
class StatsIsStatFromTodayTest extends TestCase
{
    private function _call(array $stat, string $today): bool
    {
        $method = new \ReflectionMethod(Stats::class, '_isStatFromToday');
        $method->setAccessible(true);

        return $method->invoke(null, $stat, $today);
    }

    /**
     * A datetime stamped today (any time of day) counts as today.
     */
    public function test_datetime_dated_today_is_today(): void
    {
        $this->assertTrue($this->_call(['timestamp' => '2026-06-26 17:35:34'], '2026-06-26'));
    }

    /**
     * A stale row from an earlier session is not today, even though it still sits
     * in stats_today.
     */
    public function test_datetime_dated_earlier_is_not_today(): void
    {
        $this->assertFalse($this->_call(['timestamp' => '2026-06-24 17:35:34'], '2026-06-27'));
    }

    /**
     * The model serializes the datetime cast to ISO 8601; the leading date must
     * still be read correctly.
     */
    public function test_iso_serialized_timestamp_is_compared_by_date(): void
    {
        $this->assertTrue($this->_call(['timestamp' => '2026-06-26T17:35:34.000000Z'], '2026-06-26'));
        $this->assertFalse($this->_call(['timestamp' => '2026-06-24T17:35:34.000000Z'], '2026-06-27'));
    }

    /**
     * A bare date with no time portion still resolves to its date.
     */
    public function test_date_only_timestamp_is_compared_by_date(): void
    {
        $this->assertTrue($this->_call(['timestamp' => '2026-06-27'], '2026-06-27'));
        $this->assertFalse($this->_call(['timestamp' => '2026-06-26'], '2026-06-27'));
    }

    /**
     * A missing, empty, truncated, or non-string timestamp is treated as not-today.
     */
    public function test_absent_or_invalid_timestamp_is_not_today(): void
    {
        $this->assertFalse($this->_call([], '2026-06-27'));
        $this->assertFalse($this->_call(['timestamp' => ''], '2026-06-27'));
        $this->assertFalse($this->_call(['timestamp' => '2026'], '2026-06-27'));
        $this->assertFalse($this->_call(['timestamp' => null], '2026-06-27'));
    }
}
