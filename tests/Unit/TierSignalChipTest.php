<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierSignalChip;
use PHPUnit\Framework\TestCase;

/**
 * Tier signal chip (phase 3): the resolver's signal in chip words, deferring to the range-bound and TAKE PROFIT
 * readings (one chip per symbol, the deferred signal named in the winner's tooltip), and the table filter words.
 */
class TierSignalChipTest extends TestCase
{
    private TierSignalChip $chip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chip = new TierSignalChip(20);
    }

    /**
     * A categorization entry with a tier signal; $signal overrides SettledTier::toArray() keys.
     */
    private function cat(array $signal, array $overrides = []): array
    {
        return array_replace([
            'today_tier'   => 'SILVER',
            'is_benchmark' => false,
            'range_bound'  => null,
            'take_profit'  => null,
            'tier_signal'  => array_replace([
                'settled_tier'          => 'RUST',
                'status'                => SettledTierResolver::STATUS_OK,
                'first_date'            => '2025-10-13',
                'last_date'             => '2026-10-12',
                'at_or_above_today_pct' => 19.2,
                'at_or_below_today_pct' => 93.6,
                'signal'                => SettledTierResolver::SIGNAL_RARE_HIGH,
                'action'                => SettledTierResolver::ACTION_SELL,
                'deteriorating_days'    => 0,
                'explanation'           => 'Settled Rust: ...',
            ], $signal),
        ], $overrides);
    }

    public function testNoSignalNoChip(): void
    {
        $this->assertNull($this->chip->detect($this->cat(['signal' => null, 'action' => null])));
        $this->assertNull($this->chip->detect(['tier_signal' => null]));
    }

    public function testBenchmarkNeverShowsAChip(): void
    {
        $this->assertNull($this->chip->detect($this->cat([], ['is_benchmark' => true])));
    }

    public function testRareHighSell(): void
    {
        $reading = $this->chip->detect($this->cat([]));

        $this->assertSame('Rare high', $reading['label']);
        $this->assertSame('SELL', $reading['action']);
        $this->assertNull($reading['deferred_to']);
        $this->assertSame(
            "Silver today, but Silver or better on only 19\u{00A0}% of the last 12 months (settled Rust).",
            $reading['summary']
        );
        $this->assertStringStartsWith('Rare high: Silver today', $reading['title']);
        $this->assertStringEndsWith('consider selling.', $reading['title']);
    }

    public function testWatchOnlyRareHighHasNoActionAndSaysNothingToSell(): void
    {
        $reading = $this->chip->detect($this->cat(['action' => null]));

        $this->assertNull($reading['action']);
        $this->assertStringContainsString('nothing to sell', $reading['title']);
    }

    public function testRareLowCountsTowardTheDropRule(): void
    {
        $reading = $this->chip->detect($this->cat([
            'settled_tier' => 'PLATINUM', 'signal' => SettledTierResolver::SIGNAL_RARE_LOW,
            'action' => SettledTierResolver::ACTION_ADD, 'at_or_below_today_pct' => 6.8, 'deteriorating_days' => 15,
        ]));

        $this->assertSame('Rare low', $reading['label']);
        $this->assertSame(6.8, $reading['share_pct']);
        $this->assertStringContainsString("Silver or lower on only 7\u{00A0}%", $reading['summary']);
        $this->assertStringContainsString('(settled Platinum)', $reading['summary']);
        $this->assertStringContainsString('for 15 of the 20 trading days', $reading['summary']);
        $this->assertStringEndsWith('consider adding.', $reading['title']);
    }

    public function testDeterioratingUsesTheResolverSentence(): void
    {
        $reading = $this->chip->detect($this->cat([
            'settled_tier' => 'RUST', 'signal' => SettledTierResolver::SIGNAL_DETERIORATING,
            'action' => SettledTierResolver::ACTION_REVIEW, 'status' => SettledTierResolver::STATUS_DETERIORATING,
            'deteriorating_days' => 51,
            'explanation' => 'Two tiers below its settled Silver tier for 51 trading days; the tier now follows today.',
        ], ['today_tier' => 'RUST']));

        $this->assertSame(
            'Deteriorating: two tiers below its settled Silver tier for 51 trading days; the tier now follows today.'
                . ' Review the thesis.',
            $reading['title']
        );
    }

    public function testShortHistoryNamesTheSpan(): void
    {
        $reading = $this->chip->detect($this->cat(['first_date' => '2026-05-04']));

        $this->assertStringContainsString('of the days since 2026-05-04', $reading['summary']);
    }

    public function testDefersToRangeBoundAndNamesItInItsTooltip(): void
    {
        $cat = $this->cat([], ['range_bound' => ['label' => 'Range-bound', 'action' => 'TRIM', 'title' => 'Swing.']]);
        $cat['tier_signal_chip'] = $this->chip->detect($cat);
        $cat = TierSignalChip::noteOnWinner($cat);

        $this->assertSame(TierSignalChip::DEFERRED_RANGE_BOUND, $cat['tier_signal_chip']['deferred_to']);
        $this->assertSame(
            "Swing. Also a rare high: Silver today, but Silver or better on only 19\u{00A0}% of the last 12 months"
                . ' (settled Rust).',
            $cat['range_bound']['title']
        );
    }

    public function testDefersToTakeProfit(): void
    {
        $cat = $this->cat([
            'signal' => SettledTierResolver::SIGNAL_DETERIORATING, 'action' => SettledTierResolver::ACTION_REVIEW,
            'explanation' => 'Two tiers below its settled Silver tier for 26 trading days; the tier now follows today.',
        ], ['take_profit' => ['title' => 'Take it.']]);
        $cat['tier_signal_chip'] = $this->chip->detect($cat);
        $cat = TierSignalChip::noteOnWinner($cat);

        $this->assertSame(TierSignalChip::DEFERRED_TAKE_PROFIT, $cat['tier_signal_chip']['deferred_to']);
        $this->assertStringStartsWith('Take it. Also deteriorating: two tiers below', $cat['take_profit']['title']);
    }

    public function testNoteOnWinnerLeavesAShownChipAlone(): void
    {
        $cat = $this->cat([]);
        $cat['tier_signal_chip'] = $this->chip->detect($cat);

        $this->assertSame($cat, TierSignalChip::noteOnWinner($cat));
    }

    public function testShownAndAlertable(): void
    {
        $cat = $this->cat([]);
        $cat['tier_signal_chip'] = $this->chip->detect($cat);
        $this->assertSame($cat['tier_signal_chip'], TierSignalChip::shown($cat));
        $this->assertSame($cat['tier_signal_chip'], TierSignalChip::alertable($cat));

        $watch = $this->cat(['action' => null]);
        $watch['tier_signal_chip'] = $this->chip->detect($watch);
        $this->assertNotNull(TierSignalChip::shown($watch));
        $this->assertNull(TierSignalChip::alertable($watch), 'a chip without an action never alerts');

        $deferred = $this->cat([], ['take_profit' => ['title' => 'x']]);
        $deferred['tier_signal_chip'] = $this->chip->detect($deferred);
        $this->assertNull(TierSignalChip::shown($deferred));
        $this->assertNull(TierSignalChip::alertable($deferred));
        $this->assertNull(TierSignalChip::shown(null));
    }

    public function testShortText(): void
    {
        $this->assertSame(
            "Silver or better on only 19\u{00A0}% of the last 12 months",
            TierSignalChip::shortText($this->chip->detect($this->cat([])))
        );
        $this->assertSame('two or more tiers down for 35 trading days', TierSignalChip::shortText($this->chip->detect(
            $this->cat(['signal' => SettledTierResolver::SIGNAL_DETERIORATING, 'deteriorating_days' => 35])
        )));
        $this->assertSame('down from Platinum for 35 trading days', TierSignalChip::shortText($this->chip->detect(
            $this->cat([
                'signal' => SettledTierResolver::SIGNAL_DETERIORATING, 'deteriorating_days' => 35,
                'drop_from' => 'PLATINUM',
            ])
        )));
    }

    public function testFilterLabelsListEveryReading(): void
    {
        $cat = $this->cat([], ['range_bound' => ['title' => 'x']]);
        $cat['tier_signal_chip'] = $this->chip->detect($cat);

        $this->assertSame(['Range-bound', 'Rare high'], TierSignalChip::filterLabels($cat));
        $this->assertSame(['Take profit'], TierSignalChip::filterLabels(['take_profit' => ['title' => 'x']]));
        $this->assertSame([], TierSignalChip::filterLabels(null));
    }
}
