<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use ovidiuro\myfinance2\App\Models\TierSnapshot;
use ovidiuro\myfinance2\App\Services\OrderTierWarnings as W;
use ovidiuro\myfinance2\App\Services\TierVerdictSummary;
use PHPUnit\Framework\TestCase;

/**
 * The order and trade lists' "last 12 months vs the tiers" line.
 */
class TierVerdictSummaryTest extends TestCase
{
    private function _trade(
        string $action,
        string $date,
        ?string $verdict,
        string $source = TierSnapshot::SOURCE_LIVE,
        bool $confirmed = false,
        bool $transfer = false
    ): object
    {
        return (object) [
            'action'       => $action,
            'timestamp'    => Carbon::parse($date),
            'is_transfer'  => $transfer,
            'tierSnapshot' => $verdict === null ? null : (object) [
                'verdict'         => $verdict,
                'source'          => $source,
                'acknowledged_at' => $confirmed ? Carbon::parse($date) : null,
            ],
        ];
    }

    public function testCountsPerActionAndVerdict(): void
    {
        $summary = TierVerdictSummary::build([
            $this->_trade('SELL', '2026-09-01', W::VERDICT_AGAINST),
            $this->_trade('BUY', '2026-10-01', W::VERDICT_AGAINST, TierSnapshot::SOURCE_LIVE, true),
            $this->_trade('BUY', '2026-06-01', W::VERDICT_AGAINST, TierSnapshot::SOURCE_REBUILT),
            $this->_trade('BUY', '2026-05-01', W::VERDICT_SOFT),
            $this->_trade('BUY', '2026-04-01', W::VERDICT_OK, TierSnapshot::SOURCE_REBUILT),
            $this->_trade('BUY', '2026-03-01', null),
            $this->_trade('BUY', '2026-03-02', W::VERDICT_NONE, TierSnapshot::SOURCE_NONE),
            // Left out: older than 12 months, and a transfer.
            $this->_trade('BUY', '2025-09-01', W::VERDICT_AGAINST),
            $this->_trade('BUY', '2026-10-02', W::VERDICT_AGAINST, TierSnapshot::SOURCE_LIVE, false, true),
        ], fn ($trade) => $trade->timestamp, Carbon::parse('2026-10-24'));

        $this->assertSame(['BUY', 'SELL'], array_keys($summary));
        $buy = $summary['BUY'];
        $this->assertSame(6, $buy['total']);
        $this->assertSame(2, $buy['against']);
        $this->assertSame(1, $buy['confirmed']);
        $this->assertSame(1, $buy['rebuilt']['against']);
        $this->assertSame(1, $buy['soft']);
        $this->assertSame(1, $buy['ok']);
        $this->assertSame(1, $buy['rebuilt']['ok']);
        $this->assertSame(2, $buy['unread']);
        $this->assertSame(1, $summary['SELL']['against']);

        $lines = TierVerdictSummary::lines($summary, 'trades');
        $this->assertSame('BUY, 6 trades: 2 against (1 confirmed through the warning, 1 rebuilt); 1 soft; 1 no'
            . ' warning (1 rebuilt); 2 without a reading', $lines['BUY']);
        $this->assertSame('SELL, 1 trade: 1 against', $lines['SELL']);
    }

    public function testEachVerdictCarriesTheMedianAgainstTheIndex(): void
    {
        $trades = [
            $this->_trade('BUY', '2026-10-01', W::VERDICT_AGAINST),
            $this->_trade('BUY', '2026-09-01', W::VERDICT_AGAINST),
            $this->_trade('BUY', '2026-08-01', W::VERDICT_AGAINST),
            $this->_trade('BUY', '2026-07-01', W::VERDICT_OK),
        ];
        foreach ($trades as $i => $trade) {
            $trade->id = $i + 1;
        }
        // Against: -10, -4 and no price (left out of the median); no warning: +6.
        $outcomes = [1 => ['points' => -10.0], 2 => ['points' => -4.0], 3 => null, 4 => ['points' => 6.0]];
        $summary  = TierVerdictSummary::build($trades, fn ($trade) => $trade->timestamp, Carbon::parse('2026-10-24'),
            $outcomes);

        $this->assertSame(-7.0, $summary['BUY']['median']['against']);
        $this->assertSame(6.0, $summary['BUY']['median']['ok']);
        $this->assertNull($summary['BUY']['median']['soft']);
        $this->assertStringContainsString("3 against (median -\u{00A0}7.0\u{00A0}pts vs the index)",
            TierVerdictSummary::lines($summary, 'trades')['BUY']);
    }

    public function testNothingInTheLastYear(): void
    {
        $this->assertSame([], TierVerdictSummary::build([$this->_trade('BUY', '2024-01-01', W::VERDICT_OK)],
            fn ($trade) => $trade->timestamp, Carbon::parse('2026-10-24')));
    }
}
