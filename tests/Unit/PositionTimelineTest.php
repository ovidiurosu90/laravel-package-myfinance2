<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

use ovidiuro\myfinance2\App\Services\PositionTimeline;

/**
 * Pure tests for the trades table rows of the alert emails: a position's trades and the symbol's stock splits merged
 * newest first, and whether the trades span several accounts. No DB, no Laravel boot.
 */
class PositionTimelineTest extends TestCase
{
    private static function _trade(string $date, int $accountId = 1): object
    {
        return (object) ['timestamp' => Carbon::parse($date), 'account_id' => $accountId];
    }

    private static function _split(string $date): object
    {
        return (object) ['split_date' => Carbon::parse($date)];
    }

    public function test_merges_trades_and_splits_newest_first(): void
    {
        $timeline = PositionTimeline::merge(
            [self::_trade('2024-01-10'), self::_trade('2025-06-01')],
            [self::_split('2024-09-15')]
        );

        $this->assertSame(['trade', 'split', 'trade'], array_column($timeline, 'type'));
        $this->assertSame(
            ['2025-06-01', '2024-09-15', '2024-01-10'],
            array_map(fn (array $e): string => $e['ts']->format('Y-m-d'), $timeline)
        );
    }

    public function test_no_trades_and_no_splits_is_an_empty_timeline(): void
    {
        $this->assertSame([], PositionTimeline::merge([], []));
    }

    public function test_spans_accounts_only_when_trades_come_from_several_accounts(): void
    {
        $single = PositionTimeline::merge([self::_trade('2024-01-10', 1), self::_trade('2025-01-10', 1)],
            [self::_split('2024-09-15')]);
        $multi  = PositionTimeline::merge([self::_trade('2024-01-10', 1), self::_trade('2025-01-10', 2)], []);

        $this->assertFalse(PositionTimeline::spansAccounts($single));
        $this->assertTrue(PositionTimeline::spansAccounts($multi));
        $this->assertFalse(PositionTimeline::spansAccounts([]));
    }
}
