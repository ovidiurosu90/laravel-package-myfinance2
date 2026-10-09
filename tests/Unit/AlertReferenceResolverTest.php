<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\AlertReferenceResolver;

/**
 * Unit tests for the pure parts of AlertReferenceResolver: per-window highs / lows, coverage,
 * the live-price bump and the target math. No DB, no cache, no container.
 */
class AlertReferenceResolverTest extends TestCase
{
    private const TODAY = '2026-10-09';

    /**
     * Daily closes (weekends included, for simplicity) from $daysBack days ago to yesterday, all
     * at $price, with optional overrides by date.
     *
     * @param array<string, float> $overrides
     *
     * @return array<string, float>
     */
    private function _closes(int $daysBack, float $price = 100.0, array $overrides = []): array
    {
        $closes = [];
        $today  = Carbon::parse(self::TODAY);
        for ($i = $daysBack; $i >= 1; $i--) {
            $closes[$today->copy()->subDays($i)->format('Y-m-d')] = $price;
        }

        return array_merge($closes, $overrides);
    }

    private function _daysAgo(int $days): string
    {
        return Carbon::parse(self::TODAY)->subDays($days)->format('Y-m-d');
    }

    public function test_high_and_low_per_window(): void
    {
        $closes = $this->_closes(730, 100.0, [
            $this->_daysAgo(30)  => 120.0, // inside every window
            $this->_daysAgo(150) => 140.0, // 6m, 1y, 2y
            $this->_daysAgo(300) => 80.0,  // 1y, 2y
            $this->_daysAgo(600) => 160.0, // 2y only
        ]);

        $windows = AlertReferenceResolver::computeWindows($closes, self::TODAY);

        $this->assertSame(120.0, $windows['3m']['high']);
        $this->assertSame($this->_daysAgo(30), $windows['3m']['high_date']);
        $this->assertSame(100.0, $windows['3m']['low']);
        $this->assertSame(140.0, $windows['6m']['high']);
        $this->assertSame(140.0, $windows['1y']['high']);
        $this->assertSame(80.0, $windows['1y']['low']);
        $this->assertSame($this->_daysAgo(300), $windows['1y']['low_date']);
        $this->assertSame(160.0, $windows['2y']['high']);
        $this->assertTrue($windows['2y']['covered']);
    }

    public function test_today_close_is_excluded(): void
    {
        $closes = $this->_closes(100, 100.0, [self::TODAY => 500.0]);

        $windows = AlertReferenceResolver::computeWindows($closes, self::TODAY);

        $this->assertSame(100.0, $windows['3m']['high']);
    }

    public function test_window_with_too_few_closes_is_not_covered(): void
    {
        // 29 closes inside the 3m window, starting right at the window start
        $closes = [];
        for ($i = 0; $i < 29; $i++) {
            $closes[$this->_daysAgo(91 - $i * 3)] = 100.0;
        }

        $windows = AlertReferenceResolver::computeWindows($closes, self::TODAY);

        $this->assertSame(29, $windows['3m']['count']);
        $this->assertFalse($windows['3m']['covered']);
    }

    public function test_history_starting_after_window_start_plus_grace_is_not_covered(): void
    {
        // 120 days of history: covers 3m, not 6m (starts 62 days after the 6m start)
        $windows = AlertReferenceResolver::computeWindows($this->_closes(120), self::TODAY);

        $this->assertTrue($windows['3m']['covered']);
        $this->assertFalse($windows['6m']['covered']);
        $this->assertFalse($windows['1y']['covered']);
        $this->assertFalse($windows['2y']['covered']);
    }

    public function test_history_starting_within_grace_days_is_covered(): void
    {
        // 6m window = 182 days; history starts 10 days after the window start
        $windows = AlertReferenceResolver::computeWindows($this->_closes(172), self::TODAY);

        $this->assertTrue($windows['6m']['covered']);
    }

    public function test_window_without_closes_is_null(): void
    {
        $windows = AlertReferenceResolver::computeWindows([], self::TODAY);

        $this->assertNull($windows['3m']);
        $this->assertNull($windows['2y']);
    }

    public function test_live_price_bumps_a_new_high(): void
    {
        $stats = ['high' => 120.0, 'high_date' => '2026-09-01', 'low' => 90.0, 'low_date' => '2026-08-01'];

        $this->assertSame(
            ['price' => 125.0, 'date' => self::TODAY],
            AlertReferenceResolver::applyLiveBump('HIGH', $stats, 125.0, self::TODAY)
        );
        $this->assertSame(
            ['price' => 120.0, 'date' => '2026-09-01'],
            AlertReferenceResolver::applyLiveBump('HIGH', $stats, 110.0, self::TODAY)
        );
    }

    public function test_live_price_bumps_a_new_low(): void
    {
        $stats = ['high' => 120.0, 'high_date' => '2026-09-01', 'low' => 90.0, 'low_date' => '2026-08-01'];

        $this->assertSame(
            ['price' => 85.0, 'date' => self::TODAY],
            AlertReferenceResolver::applyLiveBump('LOW', $stats, 85.0, self::TODAY)
        );
        $this->assertSame(
            ['price' => 90.0, 'date' => '2026-08-01'],
            AlertReferenceResolver::applyLiveBump('LOW', $stats, null, self::TODAY)
        );
    }

    public function test_zero_live_price_never_bumps_the_low(): void
    {
        $stats = ['high' => 120.0, 'high_date' => '2026-09-01', 'low' => 90.0, 'low_date' => '2026-08-01'];

        $this->assertSame(90.0, AlertReferenceResolver::applyLiveBump('LOW', $stats, 0.0, self::TODAY)['price']);
    }

    public function test_target_goes_below_a_high_and_above_a_low(): void
    {
        $this->assertSame(95.0, AlertReferenceResolver::computeTarget('HIGH', 100.0, 5.0));
        $this->assertSame(110.0, AlertReferenceResolver::computeTarget('LOW', 100.0, 10.0));
        $this->assertSame(100.0, AlertReferenceResolver::computeTarget('HIGH', 100.0, 0.0));
    }

    public function test_target_is_rounded_to_six_decimals(): void
    {
        $this->assertSame(0.118695, AlertReferenceResolver::computeTarget('HIGH', 0.123456789, 3.857));
    }

    public function test_describe_closes_reports_all_eight_references(): void
    {
        $closes = $this->_closes(400, 100.0, [$this->_daysAgo(20) => 130.0]);

        $references = AlertReferenceResolver::describeCloses($closes, null, self::TODAY);

        $this->assertCount(8, $references);
        $this->assertSame(130.0, $references['HIGH:1y']['price']);
        $this->assertTrue($references['HIGH:1y']['covered']);
        $this->assertFalse($references['LOW:2y']['covered']);
        $this->assertSame('52W closing high', $references['HIGH:1y']['reference_label']);
    }
}
