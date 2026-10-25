<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\TradeVsIndex;
use PHPUnit\Framework\TestCase;

/**
 * A trade against VUSA.AS since it was made: positive when the decision was right.
 */
class TradeVsIndexTest extends TestCase
{
    public function testABuyThatBeatTheIndex(): void
    {
        // +20 % against +5 %: 15 points.
        $c = TradeVsIndex::compare('BUY', 100.0, 120.0, 50.0, 52.5, '2026-01-05', 'today (2026-10-24)');
        $this->assertSame(15.0, $c['points']);
        $this->assertStringContainsString('Bought on 2026-01-05; to today (2026-10-24) the symbol moved', $c['title']);
    }

    public function testASellIsRightWhenTheSymbolThenTrailed(): void
    {
        // The symbol fell 10 %, the index rose 5 %: the sale was right by 15 points.
        $c = TradeVsIndex::compare('SELL', 100.0, 90.0, 50.0, 52.5, '2026-01-05', 'today');
        $this->assertSame(15.0, $c['points']);
        $this->assertStringContainsString('so the sale was right', $c['title']);
    }

    public function testNoComparisonWithoutPricesOrInMismatchedUnits(): void
    {
        $this->assertNull(TradeVsIndex::compare('BUY', 100.0, 120.0, null, 52.5, '2026-01-05', 'today'));
        $this->assertNull(TradeVsIndex::compare('BUY', 0.0, 120.0, 50.0, 52.5, '2026-01-05', 'today'));
        // 1.20 GBP bought, 120 GBp quoted: a unit mismatch, not a 9,900 % gain.
        $this->assertNull(TradeVsIndex::compare('BUY', 1.2, 120.0, 50.0, 52.5, '2026-01-05', 'today'));
    }
}
