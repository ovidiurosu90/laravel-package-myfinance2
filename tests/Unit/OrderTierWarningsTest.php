<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\OrderTierWarnings as W;
use ovidiuro\myfinance2\App\Services\RangeBoundPattern;
use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierCalculationService as Tiers;
use PHPUnit\Framework\TestCase;

/**
 * The order warnings: each code on the facts that raise it, the levels from config, the weak-tier softening on Index
 * Gap Settled, the verdict and the acknowledgement check.
 */
class OrderTierWarningsTest extends TestCase
{
    private function _facts(array $facts = []): array
    {
        return $facts + [
            'has_reading'            => true,
            'symbol'                 => 'TST.A',
            'effective_tier'         => Tiers::GOLD,
            'tier_basis'             => 'Market Settled, + 12.0 %',
            'index_gap_settled_tier' => Tiers::GOLD,
            'settled_tier'           => Tiers::GOLD,
            'take_profit'            => false,
            'signal'                 => null,
            'deteriorating_days'     => 0,
            'drop_from'              => null,
            'rare_share_pct'         => null,
            'range_zone'             => null,
            'near_peak_windows'      => [],
            'near_peak_closest_pct'  => null,
            'superstar'              => null,
            'health_signal'          => 'healthy',
            'bronze_rust_pct'        => 5.0,
            'bronze_rust_target_pct' => 15.0,
            'position_pct_after'     => 3.0,
            'stale_quote_age'        => null,
        ];
    }

    private function _codes(array $warnings): array
    {
        return array_column($warnings, 'level', 'code');
    }

    public function testAStrongTierBuyRaisesNothing(): void
    {
        $this->assertSame([], (new W())->evaluate('BUY', $this->_facts()));
        $this->assertSame(W::VERDICT_OK, W::verdict([]));
    }

    public function testAWeakTierBuyIsSeriousUnlessItFallsWithTheMarket(): void
    {
        $facts = $this->_facts(['effective_tier' => Tiers::RUST, 'index_gap_settled_tier' => Tiers::BRONZE]);
        $codes = $this->_codes((new W())->evaluate('BUY', $facts));
        $this->assertSame(W::LEVEL_SERIOUS, $codes[W::BUY_WEAK_TIER]);

        $soft = (new W())->evaluate('BUY', ['index_gap_settled_tier' => Tiers::SILVER] + $facts);
        $this->assertSame(W::LEVEL_SOFT, $this->_codes($soft)[W::BUY_WEAK_TIER]);
        $this->assertStringContainsString('falls with the market', $soft[0]['text']);

        $off = new W([], 10.0, false);
        $this->assertSame(W::LEVEL_SERIOUS,
            $this->_codes($off->evaluate('BUY', ['index_gap_settled_tier' => Tiers::GOLD] + $facts))[W::BUY_WEAK_TIER]);
    }

    public function testTheWeakTierTextNamesTheTierAndItsBasis(): void
    {
        $warnings = (new W())->evaluate('BUY', $this->_facts([
            'effective_tier' => Tiers::RUST, 'tier_basis' => 'Market Settled, - 10.6 %',
            'index_gap_settled_tier' => Tiers::RUST,
        ]));
        $this->assertSame('Weak tier', $warnings[0]['label']);
        $this->assertStringContainsString('the tier in use is Rust (Market Settled, - 10.6 %)', $warnings[0]['text']);
        $this->assertStringNotContainsString("\u{2014}", $warnings[0]['text']);
    }

    public function testASilverBuyIsSoft(): void
    {
        $codes = $this->_codes((new W())->evaluate('BUY', $this->_facts(['effective_tier' => Tiers::SILVER])));
        $this->assertSame([W::BUY_SILVER => W::LEVEL_SOFT], $codes);
    }

    public function testBuySignalsRaiseTheirCodes(): void
    {
        $facts = $this->_facts([
            'take_profit'        => true,
            'signal'             => SettledTierResolver::SIGNAL_DETERIORATING,
            'deteriorating_days' => 23,
            'drop_from'          => Tiers::GOLD,
            'near_peak_windows'  => ['1y'],
            'position_pct_after' => 12.5,
        ]);
        $codes = $this->_codes((new W())->evaluate('BUY', $facts));
        $this->assertSame(W::LEVEL_SERIOUS, $codes[W::BUY_TAKE_PROFIT]);
        $this->assertSame(W::LEVEL_SOFT, $codes[W::BUY_DETERIORATING]);
        $this->assertSame(W::LEVEL_SERIOUS, $codes[W::BUY_OVER_MAX_POSITION]);
        $this->assertSame(W::LEVEL_SOFT, $codes[W::BUY_NEAR_PEAK]);

        $rare = $this->_codes((new W())->evaluate('BUY', $this->_facts([
            'signal' => SettledTierResolver::SIGNAL_RARE_HIGH, 'rare_share_pct' => 8.0,
        ])));
        $this->assertSame([W::BUY_RARE_HIGH => W::LEVEL_SOFT], $rare);
    }

    public function testTheDeterioratingTextCountsTheDays(): void
    {
        $warnings = (new W())->evaluate('BUY', $this->_facts([
            'signal' => SettledTierResolver::SIGNAL_DETERIORATING, 'deteriorating_days' => 23,
            'drop_from' => Tiers::GOLD,
        ]));
        $this->assertStringContainsString('settled Gold tier for 23 trading days', $warnings[0]['text']);
    }

    public function testAPositionAtTheLimitIsNotOverIt(): void
    {
        $codes = $this->_codes((new W())->evaluate('BUY', $this->_facts(['position_pct_after' => 10.0])));
        $this->assertArrayNotHasKey(W::BUY_OVER_MAX_POSITION, $codes);
    }

    public function testAWeighedDownPortfolioWarnsOnSilverOrLower(): void
    {
        $facts = $this->_facts(['health_signal' => 'warning', 'bronze_rust_pct' => 31.0]);
        $this->assertArrayNotHasKey(W::BUY_HEALTH_WEIGHED_DOWN, $this->_codes((new W())->evaluate('BUY', $facts)));

        $silver = (new W())->evaluate('BUY', ['effective_tier' => Tiers::SILVER] + $facts);
        $this->assertSame(W::LEVEL_SOFT, $this->_codes($silver)[W::BUY_HEALTH_WEIGHED_DOWN]);
        $this->assertStringContainsString("Bronze + Rust hold 31\u{00A0}% of the portfolio (target under 15\u{00A0}%)",
            $silver[1]['text']);
    }

    public function testSellWarnings(): void
    {
        $facts = $this->_facts([
            'superstar'  => ['count' => 5, 'total' => 7, 'title' => ''],
            'signal'     => SettledTierResolver::SIGNAL_RARE_LOW,
            'range_zone' => RangeBoundPattern::ZONE_LOW,
        ]);
        $codes = $this->_codes((new W())->evaluate('SELL', $facts));
        $this->assertSame([
            W::SELL_SUPERSTAR => W::LEVEL_SERIOUS,
            W::SELL_RARE_LOW  => W::LEVEL_SOFT,
            W::SELL_RANGE_LOW => W::LEVEL_SOFT,
        ], $codes);
        // A weak-tier SELL is in line with the tiers: nothing.
        $this->assertSame([], (new W())->evaluate('SELL', $this->_facts(['effective_tier' => Tiers::RUST])));
    }

    public function testARareLowOnASilverIsNotASellWarning(): void
    {
        $codes = $this->_codes((new W())->evaluate('SELL', $this->_facts([
            'signal' => SettledTierResolver::SIGNAL_RARE_LOW, 'settled_tier' => Tiers::SILVER,
        ])));
        $this->assertArrayNotHasKey(W::SELL_RARE_LOW, $codes);
    }

    public function testNoReadingAndAStaleQuote(): void
    {
        $warnings = (new W())->evaluate('BUY', ['has_reading' => false, 'symbol' => 'TST.B',
            'stale_quote_age' => '7 minutes']);
        $this->assertSame([W::NOT_ON_WATCHLIST => W::LEVEL_SOFT, W::STALE_QUOTE => W::LEVEL_SOFT],
            $this->_codes($warnings));
        $this->assertStringContainsString('TST.B is not on the watchlist', $warnings[0]['text']);
        $this->assertStringContainsString('7 minutes old', $warnings[1]['text']);
    }

    public function testConfiguredLevelsApplyAndOffDropsACode(): void
    {
        $facts = $this->_facts([
            'effective_tier' => Tiers::RUST, 'index_gap_settled_tier' => Tiers::RUST, 'take_profit' => true,
        ]);
        $w     = new W([W::BUY_WEAK_TIER => 'soft', W::BUY_TAKE_PROFIT => 'off']);
        $this->assertSame([W::BUY_WEAK_TIER => W::LEVEL_SOFT], $this->_codes($w->evaluate('BUY', $facts)));
        // An unknown level reads as soft.
        $this->assertSame(W::LEVEL_SOFT, (new W([W::SELL_SUPERSTAR => 'loud']))->level(W::SELL_SUPERSTAR));
    }

    public function testSeriousWarningsComeFirst(): void
    {
        $warnings = (new W())->evaluate('BUY', $this->_facts([
            'effective_tier' => Tiers::RUST, 'index_gap_settled_tier' => Tiers::RUST,
            'signal' => SettledTierResolver::SIGNAL_DETERIORATING, 'take_profit' => true,
        ]));
        $this->assertSame([W::LEVEL_SERIOUS, W::LEVEL_SERIOUS, W::LEVEL_SOFT], array_column($warnings, 'level'));
        $this->assertSame(W::VERDICT_AGAINST, W::verdict($warnings));
        $this->assertSame(W::VERDICT_SOFT, W::verdict(W::soft($warnings)));
    }

    public function testUnacknowledgedListsTheSeriousCodesNotConfirmed(): void
    {
        $warnings = [
            ['code' => W::BUY_WEAK_TIER, 'level' => W::LEVEL_SERIOUS],
            ['code' => W::BUY_OVER_MAX_POSITION, 'level' => W::LEVEL_SERIOUS],
            ['code' => W::BUY_NEAR_PEAK, 'level' => W::LEVEL_SOFT],
        ];
        $this->assertSame([W::BUY_WEAK_TIER, W::BUY_OVER_MAX_POSITION], W::unacknowledged($warnings, []));
        $this->assertSame([W::BUY_OVER_MAX_POSITION], W::unacknowledged($warnings, [W::BUY_WEAK_TIER]));
        $this->assertSame([], W::unacknowledged($warnings, [W::BUY_WEAK_TIER, W::BUY_OVER_MAX_POSITION]));
    }

    public function testSameSetComparesCodesAndLevelsOnly(): void
    {
        $a = [['code' => W::BUY_SILVER, 'level' => W::LEVEL_SOFT, 'text' => 'one']];
        $b = [['code' => W::BUY_SILVER, 'level' => W::LEVEL_SOFT, 'text' => 'two']];
        $this->assertTrue(W::sameSet($a, $b));
        $this->assertFalse(W::sameSet($a, [['code' => W::BUY_SILVER, 'level' => W::LEVEL_SERIOUS]]));
        $this->assertFalse(W::sameSet($a, []));
    }
}
