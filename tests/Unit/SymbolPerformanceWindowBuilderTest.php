<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use ovidiuro\myfinance2\App\Services\SymbolPerformanceWindowBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Window building must not depend on the order the database returns trades that share a timestamp:
 * the live page and the daily tier history load the same ledger with different queries.
 */
class SymbolPerformanceWindowBuilderTest extends TestCase
{
    private function _trade(int $id, string $action, float $qty, string $at): object
    {
        return (object) [
            'id'        => $id,
            'symbol'    => 'TEST',
            'action'    => $action,
            'quantity'  => $qty,
            'timestamp' => Carbon::parse($at),
        ];
    }

    private function _spans(array $trades): array
    {
        $windows = (new SymbolPerformanceWindowBuilder())->build(new Collection($trades))['TEST'] ?? [];

        return array_map(fn ($w) => [
            $w['start_date']->format('Y-m-d'),
            $w['end_date']?->format('Y-m-d'),
            count($w['trades']),
        ], $windows);
    }

    public function testSameSecondSellAndBuyWhileHeldSplitsTheWindowInEitherInputOrder(): void
    {
        $buy      = $this->_trade(1, 'BUY', 100, '2020-01-02 10:00:00');
        $transIn  = $this->_trade(2, 'BUY', 100, '2022-01-24 21:12:13');
        $transOut = $this->_trade(3, 'SELL', 100, '2022-01-24 21:12:13');
        $exit     = $this->_trade(4, 'SELL', 100, '2023-05-01 16:00:00');
        $expected = [['2020-01-02', '2022-01-24', 2], ['2022-01-24', '2023-05-01', 2]];

        $this->assertSame($expected, $this->_spans([$buy, $transIn, $transOut, $exit]));
        $this->assertSame($expected, $this->_spans([$buy, $transOut, $transIn, $exit]));
    }

    public function testSameSecondRoundTripFromZeroStillOpensItsWindow(): void
    {
        $sell = $this->_trade(2, 'SELL', 10, '2024-03-01 15:00:00');
        $buy  = $this->_trade(1, 'BUY', 10, '2024-03-01 15:00:00');

        $this->assertSame([['2024-03-01', '2024-03-01', 2]], $this->_spans([$sell, $buy]));
    }

    public function testUnsortedInputIsOrderedByTimestamp(): void
    {
        $spans = $this->_spans([
            $this->_trade(2, 'SELL', 5, '2024-06-01 10:00:00'),
            $this->_trade(1, 'BUY', 5, '2024-01-01 10:00:00'),
        ]);

        $this->assertSame([['2024-01-01', '2024-06-01', 2]], $spans);
    }
}
