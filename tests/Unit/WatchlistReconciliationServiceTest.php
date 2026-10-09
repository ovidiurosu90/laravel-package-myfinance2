<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\WatchlistReconciliationService;

/**
 * Unit tests for WatchlistReconciliationService: the Portfolio Health card totals vs its tier
 * groups, its symbol rows and the open-position rows. Pure, no config or storage access.
 */
class WatchlistReconciliationServiceTest extends TestCase
{
    private WatchlistReconciliationService $_service;

    protected function setUp(): void
    {
        $this->_service = new WatchlistReconciliationService(0.05);
    }

    private function _position(string $currency, float $mvalue, float $cost): array
    {
        return [
            'accountModel' => (object) ['currency' => (object) ['iso_code' => $currency]],
            'market_value_in_account_currency' => $mvalue,
            'cost2_in_account_currency' => $cost,
        ];
    }

    /**
     * EURUSD 1.25. AAA (EUR account, gold) 1000/800, BBB (USD account, silver) 500/400 USD =
     * 400/320 EUR, CCC (EUR account, unrated) 100/90, left out of the card totals.
     */
    private function _groupedItems(): array
    {
        return [
            1 => [
                'AAA' => $this->_position('EUR', 1000.0, 800.0),
                'CCC' => $this->_position('EUR', 100.0, 90.0),
            ],
            2 => ['BBB' => $this->_position('USD', 500.0, 400.0)],
        ];
    }

    private function _healthScore(array $overrides = []): array
    {
        return array_merge([
            'total_mvalue_eur' => 1400.0,
            'total_cost_eur' => 1120.0,
            'platinum_gold_mvalue_eur' => 1000.0,
            'platinum_gold_cost_eur' => 800.0,
            'silver_mvalue_eur' => 400.0,
            'silver_cost_eur' => 320.0,
            'bronze_rust_mvalue_eur' => 0.0,
            'bronze_rust_cost_eur' => 0.0,
            'platinum_gold_symbols' => [['symbol' => 'AAA', 'mvalue_eur' => 1000.0, 'cost_eur' => 800.0]],
            'silver_symbols' => [['symbol' => 'BBB', 'mvalue_eur' => 400.0, 'cost_eur' => 320.0]],
            'bronze_rust_symbols' => [],
            'unrated_symbols' => [['symbol' => 'CCC', 'mvalue_eur' => 100.0, 'cost_eur' => 90.0]],
        ], $overrides);
    }

    public function test_consistent_card_produces_no_issue(): void
    {
        $this->assertSame([], $this->_service->reconcile($this->_healthScore(), $this->_groupedItems(), 1.25));
    }

    public function test_no_owned_positions_is_skipped(): void
    {
        $this->assertSame([], $this->_service->reconcile(null, [], 1.25));
    }

    /**
     * W1: a tier group that does not add up to the total (e.g. a tier the groups do not cover).
     */
    public function test_tier_groups_off_from_the_total_are_flagged(): void
    {
        $issues = $this->_service->reconcile(
            $this->_healthScore(['silver_mvalue_eur' => 350.0]), $this->_groupedItems(), 1.25
        );

        $this->assertCount(1, $issues);
        $this->assertSame('tiers', $issues[0]['check']);
        $this->assertSame('mvalue', $issues[0]['metric']);
        $this->assertEqualsWithDelta(50.0, $issues[0]['diff'], 0.0001);
    }

    /**
     * W2: a symbol row missing from the tier lists while still counted in the totals.
     */
    public function test_symbol_rows_off_from_the_total_are_flagged(): void
    {
        $issues = $this->_service->reconcile(
            $this->_healthScore(['silver_symbols' => []]), $this->_groupedItems(), 1.25
        );

        $this->assertSame(['symbols', 'symbols'], array_column($issues, 'check'));
        $this->assertSame(['mvalue', 'cost'], array_column($issues, 'metric'));
    }

    /**
     * W3: the card dropped a held position altogether (totals, tiers and rows all agree with each
     * other, but not with the open positions).
     */
    public function test_card_missing_a_position_is_flagged_against_the_open_positions(): void
    {
        $issues = $this->_service->reconcile(
            $this->_healthScore(['unrated_symbols' => []]), $this->_groupedItems(), 1.25
        );

        $this->assertSame(['portfolio', 'portfolio'], array_column($issues, 'check'));
        $this->assertEqualsWithDelta(-100.0, $issues[0]['diff'], 0.0001);
        $this->assertEqualsWithDelta(-90.0, $issues[1]['diff'], 0.0001);
    }

    /**
     * W3: the card converted USD at another rate than /positions (here 1.0 instead of 1.25).
     */
    public function test_card_converted_at_another_rate_is_flagged(): void
    {
        $issues = $this->_service->reconcile($this->_healthScore(), $this->_groupedItems(), 1.0);

        $this->assertSame(['portfolio', 'portfolio'], array_column($issues, 'check'));
        $this->assertEqualsWithDelta(-100.0, $issues[0]['diff'], 0.0001);
    }

    /**
     * Rows rounded to the cent may drift by up to half a cent each without being flagged.
     */
    public function test_cent_rounding_of_many_rows_is_tolerated(): void
    {
        $rows = array_fill(0, 20, ['symbol' => 'X', 'mvalue_eur' => 50.004, 'cost_eur' => 40.0]);
        $healthScore = $this->_healthScore([
            'total_mvalue_eur' => 1000.0,
            'total_cost_eur' => 800.0,
            'platinum_gold_mvalue_eur' => 1000.0,
            'platinum_gold_cost_eur' => 800.0,
            'silver_mvalue_eur' => 0.0,
            'silver_cost_eur' => 0.0,
            'platinum_gold_symbols' => $rows,
            'silver_symbols' => [],
            'unrated_symbols' => [],
        ]);

        $groupedItems = [1 => ['X' => $this->_position('EUR', 1000.0, 800.0)]];

        $this->assertSame([], $this->_service->reconcile($healthScore, $groupedItems, 1.25));
    }
}
