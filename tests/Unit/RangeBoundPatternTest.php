<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\RangeBoundPattern;
use ovidiuro\myfinance2\App\Services\TierDecision;
use PHPUnit\Framework\TestCase;

/**
 * The range-bound pattern: traded, small 1Y move, wide 52-week range, good own results; the zone and action come
 * from where today's price sits in the range.
 */
class RangeBoundPatternTest extends TestCase
{
    private RangeBoundPattern $pattern;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pattern = new RangeBoundPattern();
    }

    /**
     * A range-bound holding: 1Y +5.2 %, low 65 of high 100, Own Lifetime +27.5 %/y Platinum, 3 of 3 won, open.
     */
    private function cat(array $overrides = []): array
    {
        return array_replace_recursive([
            'is_benchmark'     => false,
            'candidates'       => ['market_1y_pct' => 5.2],
            'closing_extremes' => [
                'low_eur' => 65.0, 'high_eur' => 100.0, 'low_native' => 130.0, 'high_native' => 200.0,
                'low_date' => '2026-04-07', 'high_date' => '2026-10-09',
            ],
            'own_rule'         => [
                'tier' => 'PLATINUM', 'value' => 27.5, 'basis' => TierDecision::BASIS_ANNUALIZED_RETURN,
            ],
        ], $overrides);
    }

    private function perf(bool $open = true, ?array $winRate = ['wins' => 3, 'completed' => 3]): array
    {
        return [
            'window_count' => 4,
            'win_rate'     => $winRate,
            'windows'      => [['is_open' => false], ['is_open' => $open]],
        ];
    }

    public function test_near_the_high_with_an_open_position_reads_trim(): void
    {
        $r = $this->pattern->detect($this->cat(), $this->perf(), 99.0);

        $this->assertSame(RangeBoundPattern::ZONE_HIGH, $r['zone']);
        $this->assertSame(RangeBoundPattern::ACTION_TRIM, $r['action']);
        $this->assertSame('Range-bound, near the high', $r['label']);
        $this->assertEqualsWithDelta(97.1, $r['position_pct'], 0.05);
        $this->assertEqualsWithDelta(65.0, $r['low_high_pct'], 0.05);
        $this->assertTrue($r['is_owned']);
    }

    private function action(float $priceEur, bool $open = true): string
    {
        return $this->pattern->detect($this->cat(), $this->perf($open), $priceEur)['action'];
    }

    public function test_zones_and_actions_follow_the_position_in_the_range(): void
    {
        // low 65, high 100: 30 % of the range is 75.5, 70 % is 89.5
        $this->assertSame(RangeBoundPattern::ACTION_ADD, $this->action(70.0));
        $this->assertSame(RangeBoundPattern::ACTION_ADD, $this->action(75.4));
        $this->assertSame(RangeBoundPattern::ACTION_HOLD, $this->action(75.6));
        $this->assertSame(RangeBoundPattern::ACTION_HOLD, $this->action(89.4));
        $this->assertSame(RangeBoundPattern::ACTION_TRIM, $this->action(89.6));
    }

    public function test_without_an_open_position_the_actions_are_wait_watch_buy(): void
    {
        $this->assertSame(RangeBoundPattern::ACTION_WAIT, $this->action(99.0, false));
        $this->assertSame(RangeBoundPattern::ACTION_WATCH, $this->action(80.0, false));
        $this->assertSame(RangeBoundPattern::ACTION_BUY, $this->action(66.0, false));
    }

    public function test_a_price_outside_the_stored_range_is_clamped(): void
    {
        $this->assertSame(100.0, $this->pattern->detect($this->cat(), $this->perf(), 104.0)['position_pct']);
        $this->assertSame(0.0, $this->pattern->detect($this->cat(), $this->perf(), 60.0)['position_pct']);
    }

    private function isRangeBound(array $catOverrides, ?array $perf = null): bool
    {
        return $this->pattern->detect($this->cat($catOverrides), $perf ?? $this->perf(), 99.0) !== null;
    }

    public function test_a_big_1y_move_is_a_trend_not_a_range(): void
    {
        $this->assertFalse($this->isRangeBound(['candidates' => ['market_1y_pct' => 10.0]]));
        $this->assertFalse($this->isRangeBound(['candidates' => ['market_1y_pct' => -24.8]]));
        $this->assertTrue($this->isRangeBound(['candidates' => ['market_1y_pct' => -9.9]]));
        $this->assertFalse($this->isRangeBound(['candidates' => ['market_1y_pct' => null]]));
    }

    public function test_a_narrow_range_does_not_match(): void
    {
        // 79 % matches under the 80 % line; 80 % does not
        $this->assertTrue($this->isRangeBound(['closing_extremes' => ['low_eur' => 79.0]]));
        $this->assertFalse($this->isRangeBound(['closing_extremes' => ['low_eur' => 80.0]]));
    }

    public function test_own_results_qualify_by_tier_or_by_win_rate(): void
    {
        $silver = ['own_rule' => ['tier' => 'SILVER', 'value' => 7.0]];

        // Gold or better alone is enough (no completed trades)
        $gold = ['own_rule' => ['tier' => 'GOLD', 'value' => 12.6]];
        $this->assertTrue($this->isRangeBound($gold, $this->perf(true, null)));
        // Silver needs at least 2 completed trades with 75 % or more won
        $this->assertTrue($this->isRangeBound($silver, $this->perf(true, ['wins' => 3, 'completed' => 4])));
        $this->assertFalse($this->isRangeBound($silver, $this->perf(true, ['wins' => 2, 'completed' => 3])));
        $this->assertFalse($this->isRangeBound($silver, $this->perf(true, ['wins' => 1, 'completed' => 1])));
    }

    public function test_a_market_stand_in_is_not_your_own_result(): void
    {
        // A watchlist-only symbol: Own Lifetime stands in with the market figure, and nothing was traded
        $this->assertFalse($this->isRangeBound(
            ['own_rule' => ['basis' => TierDecision::BASIS_MARKET_MOMENTUM]], $this->perf(true, null)
        ));
        $this->assertFalse($this->isRangeBound([], ['window_count' => 0]));
    }

    public function test_an_exited_symbol_is_judged_on_its_realized_lifetime_return(): void
    {
        // Exited: the own-return rule tiers it on the market, so the realized CAGR is your own result.
        $exited = ['own_rule' => ['basis' => TierDecision::BASIS_MARKET_MOMENTUM, 'tier' => 'SILVER', 'value' => 5.2]];
        $closed = $this->perf(false, null) + ['annualized_percentage_gain' => 14.0];

        $r = $this->pattern->detect($this->cat($exited), $closed, 66.0);
        $this->assertSame('GOLD', $r['own_tier']);
        $this->assertSame(14.0, $r['own_value']);
        $this->assertSame(RangeBoundPattern::ACTION_BUY, $r['action']);

        // A Silver realized return with a single completed trade does not qualify.
        $silver = $this->perf(false, ['wins' => 1, 'completed' => 1]) + ['annualized_percentage_gain' => 7.7];
        $this->assertNull($this->pattern->detect($this->cat($exited), $silver, 66.0));
    }

    public function test_a_new_holding_on_a_market_stand_in_does_not_use_the_realized_return(): void
    {
        // Held but too new for its own return: the stand-in is not your result, and the open position's CAGR is not
        // used either.
        $new  = ['own_rule' => ['basis' => TierDecision::BASIS_MARKET_MOMENTUM]];
        $perf = $this->perf(true, null) + ['annualized_percentage_gain' => 30.0];

        $this->assertFalse($this->isRangeBound($new, $perf));
    }

    public function test_the_benchmark_and_missing_prices_never_match(): void
    {
        $this->assertFalse($this->isRangeBound(['is_benchmark' => true]));
        $this->assertFalse($this->isRangeBound(['closing_extremes' => ['low_eur' => 0.0]]));
        $this->assertNull($this->pattern->detect($this->cat(), $this->perf(), null));
    }

    public function test_the_title_explains_the_reading_without_dashes(): void
    {
        $title = $this->pattern->detect($this->cat(), $this->perf(), 99.0)['title'];
        $nbsp  = "\u{00A0}";

        $this->assertStringStartsWith('Range-bound: ', $title);
        $this->assertStringContainsString("1Y +{$nbsp}5.2{$nbsp}%", $title);
        $this->assertStringContainsString("low at 65{$nbsp}% of the high", $title);
        $this->assertStringContainsString(
            "Own Lifetime +{$nbsp}27.5{$nbsp}%/y, Platinum; 3 of 3 completed trades won", $title
        );
        $this->assertStringContainsString("at 97{$nbsp}% of its 52-week range", $title);
        $this->assertStringContainsString('near the high: TRIM, take the swing profit.', $title);
        $this->assertStringNotContainsString('—', $title);
        $this->assertStringNotContainsString('–', $title);
    }

    public function test_the_levels_are_the_range_in_the_trade_currency_with_the_zone_line_flagged(): void
    {
        // native low 130, high 200 (EUR 65 / 100 at the same rate): ADD line 151, TRIM line 179
        $reading = $this->pattern->detect($this->cat(), $this->perf(), 99.0);
        $levels  = RangeBoundPattern::levelRows($reading, 198.0);
        $nbsp    = "\u{00A0}";

        $this->assertSame(
            ['52-week high', "TRIM line, 70{$nbsp}% of the range", "ADD line, 30{$nbsp}% of the range", '52-week low'],
            array_column($levels, 'label')
        );
        $this->assertSame([200.0, 179.0, 151.0, 130.0], array_column($levels, 'price'));
        $this->assertSame(['2026-10-09', null, null, '2026-04-07'], array_column($levels, 'date'));
        $this->assertSame([false, true, false, false], array_column($levels, 'in_zone'));
        $this->assertEqualsWithDelta(1.01, $levels[0]['from_price_pct'], 0.01);
        $this->assertEqualsWithDelta(-34.34, $levels[3]['from_price_pct'], 0.01);

        $low = $this->pattern->detect($this->cat(), $this->perf(), 66.0);
        $lowLevels = RangeBoundPattern::levelRows($low, null);
        $this->assertSame([false, false, true, false], array_column($lowLevels, 'in_zone'));
        $this->assertNull($lowLevels[0]['from_price_pct']);
    }

    public function test_levels_without_native_extremes_are_left_out(): void
    {
        $cat = $this->cat();
        unset($cat['closing_extremes']['low_native'], $cat['closing_extremes']['high_native']);

        $this->assertSame([], RangeBoundPattern::levelRows($this->pattern->detect($cat, $this->perf(), 99.0), 99.0));
    }

    public function test_the_position_text(): void
    {
        $this->assertSame(
            "at 97\u{00A0}% of its 52-week range",
            RangeBoundPattern::positionText($this->pattern->detect($this->cat(), $this->perf(), 99.0))
        );
    }
}
