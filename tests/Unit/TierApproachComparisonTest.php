<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use ovidiuro\myfinance2\App\Services\TierApproachCatalog as Catalog;
use ovidiuro\myfinance2\App\Services\TierApproachComparison as Cmp;
use ovidiuro\myfinance2\App\Services\TierCalculationService as Tiers;
use ovidiuro\myfinance2\App\Services\TierDecision;
use PHPUnit\Framework\TestCase;

/**
 * The per-symbol cells and the per-approach portfolio split of the health card's approach comparison.
 */
class TierApproachComparisonTest extends TestCase
{
    private const DAY = '2026-06-01';

    private Cmp $_cmp;

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
        $this->_cmp = new Cmp();
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    private function _cells(string $symbol, array $cat, array $window = [], ?float $index1y = 20.0): array
    {
        return $this->_cmp->cells($symbol, $cat, $window, $index1y, self::DAY);
    }

    /**
     * @param float[] $values one stored 1Y market return per day, oldest first, ending the day before DAY
     */
    private function _rows(array $values): array
    {
        $rows = [];
        $date = strtotime(self::DAY);
        foreach (array_reverse($values) as $value) {
            $date -= 86400;
            $rows[date('Y-m-d', $date)] = ['m' => $value];
        }

        return $rows;
    }

    private function _cat(?float $market1y, ?float $market2y = null, ?float $alpha = null, bool $short = false): array
    {
        return [
            'candidates'            => ['market_1y_pct' => $market1y],
            'momenta'               => ['2y' => $market2y],
            'alpha_vs_vusa_pct'     => $alpha,
            'alpha_is_short_period' => $short,
            'own_rule'              => [
                'tier' => Tiers::SILVER, 'value' => 7.0,
                'basis' => TierDecision::BASIS_RAW_RETURN, 'basis_label' => 'raw return',
            ],
        ];
    }

    public function test_absolute_approaches_use_the_usual_tier_lines(): void
    {
        $cells = $this->_cells('TEST', $this->_cat(12.0, 3.0));

        $this->assertSame(Tiers::GOLD, $cells[Cmp::MARKET_SNAPSHOT]['tier']);
        $this->assertSame(12.0, $cells[Cmp::MARKET_SNAPSHOT]['value']);
        $this->assertSame(Tiers::BRONZE, $cells[Cmp::MARKET_2Y_SNAPSHOT]['tier']);
        $this->assertSame(Tiers::SILVER, $cells[Cmp::OWN_LIFETIME]['tier']);
        $this->assertTrue($cells[Cmp::OWN_LIFETIME]['flag'], 'Not annualized, so marked.');
    }

    public function test_market_return_below_the_trusted_floor_falls_back_to_your_return(): void
    {
        $cells = $this->_cells('TEST', $this->_cat(-90.0));

        $this->assertSame(Tiers::SILVER, $cells[Cmp::MARKET_SNAPSHOT]['tier']);
        $this->assertSame(7.0, $cells[Cmp::MARKET_SNAPSHOT]['value']);
        $this->assertTrue($cells[Cmp::MARKET_SNAPSHOT]['flag']);
        $this->assertStringContainsString('your own return', $cells[Cmp::MARKET_SNAPSHOT]['title']);
    }

    public function test_unusual_market_rally_is_tiered_with_a_note_and_no_stand_in_mark(): void
    {
        $cell = $this->_cells('TEST', $this->_cat(250.0))[Cmp::MARKET_SNAPSHOT];

        $this->assertSame(Tiers::PLATINUM, $cell['tier']);
        $this->assertSame(250.0, $cell['value']);
        $this->assertFalse($cell['flag'], 'A real figure, not a stand-in.');
        $this->assertStringContainsString('unusual move', $cell['title']);
    }

    public function test_gap_to_index_falls_back_to_your_alpha_without_market_data(): void
    {
        $cell = $this->_cells('TEST', $this->_cat(null, null, 6.0))[Cmp::INDEX_GAP_SNAPSHOT];

        $this->assertSame(6.0, $cell['value']);
        $this->assertSame(Tiers::PLATINUM, $cell['tier']);
        $this->assertTrue($cell['flag']);
    }

    public function test_no_figure_at_all_gives_no_tier(): void
    {
        $cat   = $this->_cat(null);
        $cat['own_rule'] = ['tier' => null, 'value' => null, 'basis' => TierDecision::BASIS_NONE];
        $cells = $this->_cells('TEST', $cat, [], null);

        foreach ($cells as $key => $cell) {
            $this->assertNull($cell['tier'], $key);
        }
    }

    public function test_relative_approach_puts_the_index_on_the_gold_line(): void
    {
        $above = $this->_cells('TEST', $this->_cat(23.0), [], 20.0)[Cmp::INDEX_GAP_SNAPSHOT];
        $this->assertSame(3.0, $above['value']);
        $this->assertSame(Tiers::GOLD, $above['tier']);
        $this->assertSame(Cmp::UNIT_PTS, $above['unit']);

        $this->assertSame(Tiers::PLATINUM, $this->_cells('TEST', $this->_cat(26.0))[Cmp::INDEX_GAP_SNAPSHOT]['tier']);
        $this->assertSame(Tiers::SILVER, $this->_cells('TEST', $this->_cat(17.0))[Cmp::INDEX_GAP_SNAPSHOT]['tier']);
        $this->assertSame(Tiers::BRONZE, $this->_cells('TEST', $this->_cat(12.0))[Cmp::INDEX_GAP_SNAPSHOT]['tier']);
        $this->assertSame(Tiers::RUST, $this->_cells('TEST', $this->_cat(8.0))[Cmp::INDEX_GAP_SNAPSHOT]['tier']);
    }

    public function test_benchmark_is_gold_against_itself(): void
    {
        $cells = $this->_cells(Tiers::BENCHMARK_SYMBOL, $this->_cat(20.0, null, 0.0), [], 20.0);

        $this->assertSame(Tiers::GOLD, $cells[Cmp::INDEX_GAP_SNAPSHOT]['tier']);
        $this->assertSame(Tiers::GOLD, $cells[Cmp::OWN_ALPHA]['tier']);
    }

    public function test_benchmark_settled_shows_its_median_and_a_zero_gap_pinned_to_gold(): void
    {
        $window = [Tiers::BENCHMARK_SYMBOL => $this->_rows(array_fill(0, 70, 21.0))];
        $cells  = $this->_cells(Tiers::BENCHMARK_SYMBOL, $this->_cat(21.0), $window, 21.0);

        $this->assertSame(21.0, $cells[Cmp::MARKET_SETTLED]['value']);
        $this->assertSame(Tiers::GOLD, $cells[Cmp::MARKET_SETTLED]['tier'], 'Pinned, though the median is Platinum.');
        $this->assertStringContainsString('pinned to Gold', $cells[Cmp::MARKET_SETTLED]['title']);
        $this->assertSame(0.0, $cells[Cmp::INDEX_GAP_SETTLED]['value']);
        $this->assertSame(Tiers::GOLD, $cells[Cmp::INDEX_GAP_SETTLED]['tier']);
    }

    public function test_alpha_is_tiered_relative_and_flagged_when_short(): void
    {
        $cell = $this->_cells('TEST', $this->_cat(null, null, -7.0, true))[Cmp::OWN_ALPHA];

        $this->assertSame(-7.0, $cell['value']);
        $this->assertSame(Tiers::BRONZE, $cell['tier']);
        $this->assertTrue($cell['flag']);
    }

    public function test_settled_market_takes_the_median_daily_reading(): void
    {
        $window = ['TEST' => $this->_rows(array_merge(array_fill(0, 10, 2.0), array_fill(0, 60, 12.0)))];
        $cell   = $this->_cells('TEST', $this->_cat(2.0), $window)[Cmp::MARKET_SETTLED];

        $this->assertSame(Tiers::GOLD, $cell['tier']);
        $this->assertSame(12.0, $cell['value']);
        $this->assertStringContainsString('Median of 70 daily readings', $cell['title']);
        $nbsp = "\u{00A0}";
        $this->assertStringContainsString(
            "+{$nbsp}12.00{$nbsp}%. Today's reading: +{$nbsp}2.00{$nbsp}%.", $cell['title']
        );
        $this->assertStringContainsString("Time in tier: G{$nbsp}86% · B{$nbsp}14%.", $cell['title']);
    }

    public function test_settled_market_falls_back_to_today_with_too_short_a_history(): void
    {
        $window = ['TEST' => $this->_rows(array_fill(0, 7, 12.0))];
        $cell   = $this->_cells('TEST', $this->_cat(3.0), $window)[Cmp::MARKET_SETTLED];

        $this->assertSame(Tiers::BRONZE, $cell['tier']);
        $this->assertSame(3.0, $cell['value']);
        $this->assertTrue($cell['flag']);
        $this->assertStringContainsString('history too short (7 days)', $cell['title']);
    }

    public function test_settled_market_follows_today_after_a_lasting_drop_like_the_rule_in_use(): void
    {
        $window = ['TEST' => $this->_rows(array_merge(array_fill(0, 40, 20.0), array_fill(0, 30, -5.0)))];
        $cell   = $this->_cells('TEST', $this->_cat(-6.0), $window)[Cmp::MARKET_SETTLED];

        $this->assertSame(Tiers::RUST, $cell['tier'], 'The median alone would be Platinum.');
        $this->assertSame(-6.0, $cell['value']);
        $this->assertTrue($cell['flag']);
        $this->assertStringContainsString('the tier now follows today', $cell['title']);
    }

    public function test_approaches_are_numbered_with_tradeoffs(): void
    {
        $approaches = Cmp::approaches();

        $this->assertSame(range(1, 7), array_column($approaches, 'number'));
        foreach ($approaches as $key => $approach) {
            $this->assertNotEmpty($approach['pros'], $key);
            $this->assertNotEmpty($approach['cons'], $key);
            $this->assertNotEmpty($approach['measures'], $key);
            $this->assertNotEmpty($approach['verdict_text'], $key);
        }
    }

    public function test_names_are_unique_and_the_decision_matches_the_verdicts(): void
    {
        $approaches = Cmp::approaches();
        $names      = array_column($approaches, 'name');

        $this->assertSame($names, array_unique($names));
        $this->assertSame(Cmp::MARKET_SETTLED, Catalog::DECISION['approach']);
        $this->assertSame([Cmp::MARKET_SETTLED], array_keys(array_filter($approaches, fn ($a) => $a['in_use'])));
        $this->assertSame(Catalog::VERDICT_CHOSEN, $approaches[Cmp::MARKET_SETTLED]['verdict']);
        $this->assertSame(Catalog::VERDICT_RUNNER_UP, $approaches[Cmp::INDEX_GAP_SETTLED]['verdict']);
        $chosen = array_filter($approaches, fn ($a) => $a['verdict'] === Catalog::VERDICT_CHOSEN);
        $this->assertCount(1, $chosen);
    }

    public function test_settled_vs_index_uses_the_daily_gap_on_shared_days(): void
    {
        $window = [
            'TEST'                  => $this->_rows(array_fill(0, 70, 20.0)),
            Tiers::BENCHMARK_SYMBOL => $this->_rows(array_fill(0, 70, 26.0)),
        ];
        $cell = $this->_cells('TEST', $this->_cat(20.0), $window)[Cmp::INDEX_GAP_SETTLED];

        $this->assertSame(-6.0, $cell['value']);
        $this->assertSame(Tiers::BRONZE, $cell['tier']);
    }

    public function test_summary_splits_market_value_per_approach_with_its_signal(): void
    {
        $rows = [
            ['mvalue_eur' => 60.0, 'cells' => [Cmp::MARKET_SNAPSHOT => ['tier' => Tiers::PLATINUM]]],
            ['mvalue_eur' => 30.0, 'cells' => [Cmp::MARKET_SNAPSHOT => ['tier' => Tiers::SILVER]]],
            ['mvalue_eur' => 10.0, 'cells' => [Cmp::MARKET_SNAPSHOT => ['tier' => null]]],
        ];
        $summary = $this->_cmp->summarize($rows);

        $this->assertSame(60.0, $summary[Cmp::MARKET_SNAPSHOT]['platinum_gold']);
        $this->assertSame(30.0, $summary[Cmp::MARKET_SNAPSHOT]['silver']);
        $this->assertSame(0.0, $summary[Cmp::MARKET_SNAPSHOT]['bronze_rust']);
        $this->assertSame(10.0, $summary[Cmp::MARKET_SNAPSHOT]['none']);
        $this->assertSame('healthy', $summary[Cmp::MARKET_SNAPSHOT]['signal']);
        $this->assertSame(100.0, $summary[Cmp::OWN_LIFETIME]['none'], 'No cells: everything without a tier.');
        $this->assertSame('caution', $summary[Cmp::OWN_LIFETIME]['signal']);
    }
    /**
     * Cells for the 7 approaches, the first $strong Platinum / Gold and the rest Silver.
     */
    private function _tierCells(int $strong): array
    {
        $cells = [];
        foreach (array_keys(Cmp::approaches()) as $i => $key) {
            $cells[$key] = ['tier' => $i < $strong ? ($i % 2 ? Tiers::GOLD : Tiers::PLATINUM) : Tiers::SILVER];
        }

        return $cells;
    }

    public function test_superstar_needs_platinum_or_gold_under_most_approaches(): void
    {
        $this->assertNull(Cmp::superstar($this->_tierCells(3)));

        $star = Cmp::superstar($this->_tierCells(4));
        $this->assertSame(4, $star['count']);
        $this->assertSame(7, $star['total']);
        $this->assertStringContainsString('Platinum or Gold under 4 of the 7 tier approaches', $star['title']);

        $this->assertSame(7, Cmp::superstar($this->_tierCells(7))['count']);
        $this->assertNull(Cmp::superstar([]), 'No cells: no tiers, no star.');
    }
}
