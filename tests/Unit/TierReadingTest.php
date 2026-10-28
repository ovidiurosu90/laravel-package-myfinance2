<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use ovidiuro\myfinance2\App\Services\NearPeakReading;
use ovidiuro\myfinance2\App\Services\OrderTierCheck;
use ovidiuro\myfinance2\App\Services\PositionReadingAsOf;
use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierApproachComparison as Cmp;
use ovidiuro\myfinance2\App\Services\TierCalculationService as Tiers;
use ovidiuro\myfinance2\App\Services\TierDecision;
use ovidiuro\myfinance2\App\Services\TierHistoryPresenter;
use ovidiuro\myfinance2\App\Services\TierReadingAsOf;
use ovidiuro\myfinance2\App\Services\TierReadingBuilder;
use ovidiuro\myfinance2\App\Services\TierSignalChip;
use PHPUnit\Framework\TestCase;

/**
 * The tier reading saved with an order: its shape from a live categorization entry (TierReadingBuilder), the warnings'
 * facts, the share of the portfolio after the order, and the reading rebuilt for a past day (TierReadingAsOf).
 */
class TierReadingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        $container->instance('config', new ConfigRepository([
            'myfinance2' => [
                'watchlistsymbols' => ['tier_history' => ['min_history_days' => 60, 'consistency_pct' => 50]],
            ],
        ]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    private function _cells(): array
    {
        $cells = [];
        foreach (array_keys(Cmp::approaches()) as $key) {
            $cells[$key] = ['value' => 12.0, 'tier' => Tiers::GOLD, 'unit' => '%', 'title' => 'T ' . $key,
                'flag' => false];
        }
        $cells[Cmp::MARKET_SETTLED]    = ['value' => -10.6, 'tier' => Tiers::RUST, 'unit' => '%', 'title' => 'Median.',
            'flag' => false];
        $cells[Cmp::INDEX_GAP_SETTLED] = ['value' => -3.0, 'tier' => Tiers::SILVER, 'unit' => 'pts', 'title' => 'Gap.',
            'flag' => false];

        return $cells;
    }

    private function _cat(array $extra = []): array
    {
        return $extra + [
            'effective_tier'   => Tiers::RUST,
            'has_override'     => false,
            'is_benchmark'     => false,
            'tier_tooltip'     => 'Settled Rust.',
            'explanation'      => 'Market 1Y.',
            'confidence'       => TierDecision::CONFIDENCE_HIGH,
            'approach_cells'   => $this->_cells(),
            'superstar'        => null,
            'settled_tier'     => Tiers::RUST,
            'tier_signal'      => ['status' => SettledTierResolver::STATUS_OK, 'signal' => null],
            'tier_history'     => ['settled_value' => -10.6, 'ribbon' => [['width_pct' => 100, 'color' => '#dc3545',
                'title' => 'Rust']], 'ribbon_note' => 'Last 12 months: R 100%.', 'split' => null],
            'range_bound'      => null,
            'take_profit'      => ['label' => 'Weak tier in profit', 'action' => 'TAKE PROFIT', 'title' => 'TP.',
                'tier' => Tiers::RUST, 'gain_pct' => 4.0],
            'tier_signal_chip' => null,
            'near_peak'        => null,
            'exit_zones'       => ['3m' => ['proximity_pct' => -1.0], '1y' => ['proximity_pct' => -12.0]],
        ];
    }

    public function testTheReadingCarriesTheCellsInOrderWithTheirTooltips(): void
    {
        $reading = TierReadingBuilder::fromCategorization('TST.A', $this->_cat(), ['mvalue_eur' => 540.0,
            'mvalue_pct' => 5.4], '#dc3545');

        $this->assertSame(array_keys(Cmp::approaches()), array_column($reading['cells'], 'key'));
        $inUse = array_values(array_filter($reading['cells'], fn ($c) => $c['in_use']));
        $this->assertSame(Cmp::MARKET_SETTLED, $inUse[0]['key']);
        $this->assertStringContainsString('Market Settled (in use): -', $inUse[0]['tip']);
        $this->assertSame(['pct' => 5.4, 'mvalue_eur' => 540.0, 'color' => '#dc3545'], $reading['portfolio']);
        $this->assertTrue($reading['history']['settled_shown']);
        // The chips keep only what their partials read.
        $this->assertSame(['label', 'action', 'title'], array_keys($reading['chips']['take_profit']));
        $this->assertNull($reading['chips']['range_bound']);
    }

    public function testFlagsAndHistoryNotes(): void
    {
        $reading = TierReadingBuilder::fromCategorization('TST.A', $this->_cat([
            'is_stale'       => true,
            'market_unusual' => true, 'market_unusual_note' => 'Unusual.',
            'confidence'     => TierDecision::CONFIDENCE_LOW,
            'tier_signal'    => ['status' => SettledTierResolver::STATUS_DETERIORATING],
        ]));
        $this->assertSame(['fa-clock-o', 'fa-exclamation-triangle', 'fa-exclamation-circle'],
            array_column($reading['flags'], 'icon'));
        $this->assertSame('follows today after a lasting drop', $reading['history']['note']);
        $this->assertNull(TierReadingBuilder::fromCategorization('TST.A', null));
    }

    public function testFactsNameTheBasisAndTheMeaningfulWindowsNearPeak(): void
    {
        $cat     = $this->_cat(['exit_zones' => [
            '3m' => ['proximity_pct' => -1.0], '1y' => ['proximity_pct' => -3.0],
        ]]);
        $reading = TierReadingBuilder::fromCategorization('TST.A', $cat);
        $facts   = TierReadingBuilder::facts('TST.A', $cat, $reading['cells'], ['signal' => 'warning',
            'bronze_rust_pct' => 31.0, 'bronze_rust_target' => 15.0], new NearPeakReading());

        $this->assertSame("Market Settled, -\u{00A0}10.6\u{00A0}%", $facts['tier_basis']);
        $this->assertSame(Tiers::SILVER, $facts['index_gap_settled_tier']);
        $this->assertTrue($facts['take_profit']);
        // The 3M window alone is context: only the meaningful 1Y is a warning.
        $this->assertSame(['1y'], $facts['near_peak_windows']);
        $this->assertSame(-3.0, $facts['near_peak_closest_pct']);
        $this->assertSame('warning', $facts['health_signal']);

        $override = TierReadingBuilder::facts('TST.A', ['has_override' => true] + $cat, $reading['cells'], null,
            new NearPeakReading());
        $this->assertSame('your manual tier', $override['tier_basis']);
    }

    public function testThePositionAfterAnOrder(): void
    {
        $position = ['mvalue_eur' => 540.0, 'total_mvalue_eur' => 10000.0, 'price_native' => 60.0, 'eur_rate' => 0.9];
        // 80 x 58 USD x 0.9 = 4176 EUR: (540 + 4176) / (10000 + 4176)
        $this->assertSame(33.3, OrderTierCheck::positionAfter($position, 'BUY', 80.0, 58.0));
        // Without a limit price, today's price.
        $this->assertSame(round((540 + 4320) / 14320 * 100, 1), OrderTierCheck::positionAfter($position, 'BUY', 80.0,
            null));
        $this->assertSame(0.0, OrderTierCheck::positionAfter($position, 'SELL', 10.0, 60.0));
        $this->assertNull(OrderTierCheck::positionAfter($position, 'BUY', null, 58.0));
        $this->assertNull(OrderTierCheck::positionAfter(['eur_rate' => null] + $position, 'BUY', 80.0, 58.0));
        $this->assertNull(OrderTierCheck::positionAfter(null, 'BUY', 80.0, 58.0));
    }

    private function _asOf(): TierReadingAsOf
    {
        return new TierReadingAsOf(
            new SettledTierResolver(), new PositionReadingAsOf(), new NearPeakReading(), new TierSignalChip(),
            new TierHistoryPresenter(), 365, '2025-01-01'
        );
    }

    /**
     * Weekday rows from 2025-01-01 to $to, the 1Y market return $m (and owned).
     */
    private function _rows(string $to, float $m, bool $owned = true): array
    {
        $rows = [];
        for ($t = strtotime('2025-01-01'); $t <= strtotime($to); $t += 86400) {
            if ((int) date('N', $t) >= 6) {
                continue;
            }
            $rows[date('Y-m-d', $t)] = ['v' => $m, 'm' => $m, 'w' => 3.0, 'h' => 2.0, 's' => '2025-01-01',
                'b' => TierDecision::BASIS_MARKET_MOMENTUM, 'o' => $owned];
        }

        return $rows;
    }

    public function testARebuiltReadingUsesTheLastStoredDayOnOrBefore(): void
    {
        $rows  = $this->_rows('2025-09-30', -6.0);
        $index = $this->_rows('2025-09-30', 15.0, false);
        // A Saturday: the reading of Friday's row.
        $read  = $this->_asOf()->read('TST.A', '2025-09-27', $rows, $index, []);

        $this->assertSame(TierReadingAsOf::SOURCE_REBUILT, $read['source']);
        $this->assertSame('2025-09-26', $read['as_of']);
        $reading = $read['reading'];
        $this->assertSame(Tiers::RUST, $reading['effective_tier']);
        $this->assertNull($reading['superstar']);
        $this->assertNull($reading['portfolio']);
        $byKey = array_column($reading['cells'], null, 'key');
        $this->assertNull($byKey[Cmp::MARKET_2Y_SNAPSHOT]['tier']);
        $this->assertNull($byKey[Cmp::OWN_ALPHA]['value']);
        $this->assertStringContainsString('not rebuilt', $byKey[Cmp::OWN_ALPHA]['title']);
        $this->assertSame(Tiers::RUST, $byKey[Cmp::MARKET_SETTLED]['tier']);
        // -6 against an index at +15: 21 points behind, Rust.
        $this->assertSame(Tiers::RUST, $byKey[Cmp::INDEX_GAP_SETTLED]['tier']);
        $this->assertSame(Tiers::RUST, $read['reading']['facts']['index_gap_settled_tier']);
        $this->assertNotEmpty($reading['history']['ribbon']);
    }

    public function testNoReadingBeforeTheHistoryOrWithoutRows(): void
    {
        $before = $this->_asOf()->read('TST.A', '2024-11-12', $this->_rows('2025-03-01', 5.0), [], []);
        $this->assertSame(TierReadingAsOf::SOURCE_NONE, $before['source']);
        $this->assertStringContainsString('before 2025-01-01', $before['reading']['explanation']);

        $empty = $this->_asOf()->read('TST.A', '2025-06-02', [], [], []);
        $this->assertSame(TierReadingAsOf::SOURCE_NONE, $empty['source']);
        $this->assertFalse($empty['reading']['facts']['has_reading']);
    }

    public function testNearPeakFromTheStoredCloses(): void
    {
        $closes = [];
        for ($t = strtotime('2023-09-01'); $t <= strtotime('2025-09-26'); $t += 86400) {
            $closes[date('Y-m-d', $t)] = 100.0;
        }
        $closes['2025-09-26'] = 99.0;
        $read = $this->_asOf()->read('TST.A', '2025-09-26', $this->_rows('2025-09-30', 20.0),
            $this->_rows('2025-09-30', 15.0, false), $closes);

        $this->assertSame(['6m', '1y', '2y'], $read['reading']['facts']['near_peak_windows']);
        $this->assertSame(-1.0, $read['reading']['facts']['near_peak_closest_pct']);
    }
}
