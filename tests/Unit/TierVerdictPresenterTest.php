<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Models\TierSnapshot;
use ovidiuro\myfinance2\App\Services\OrderTierWarnings as W;
use ovidiuro\myfinance2\App\Services\TierCalculationService as Tiers;
use ovidiuro\myfinance2\App\Services\TierVerdictPresenter as P;
use PHPUnit\Framework\TestCase;

/**
 * The verdict of a saved tier reading in the order and trade lists: its icon, its sort value and its tooltip.
 */
class TierVerdictPresenterTest extends TestCase
{
    private function _snapshot(
        string $verdict,
        array $warnings,
        ?string $tier = Tiers::RUST,
        array $extra = []
    ): TierSnapshot
    {
        $snapshot = new TierSnapshot();
        $snapshot->setDateFormat('Y-m-d H:i:s');
        $snapshot->setRawAttributes($extra + [
            'verdict'         => $verdict,
            'effective_tier'  => $tier,
            'source'          => TierSnapshot::SOURCE_LIVE,
            'as_of_date'      => '2026-10-24',
            'acknowledged_at' => null,
            'warnings'        => json_encode($warnings),
            'reading'         => json_encode(['effective_tier' => $tier]),
        ]);

        return $snapshot;
    }

    private function _weakTier(): array
    {
        return ['code' => W::BUY_WEAK_TIER, 'level' => W::LEVEL_SERIOUS, 'label' => 'Weak tier',
            'text' => 'the tier in use is Rust <b>.'];
    }

    public function testIcons(): void
    {
        $this->assertSame('fa-check', P::icon(W::VERDICT_OK)[0]);
        $this->assertSame('text-warning', P::icon(W::VERDICT_SOFT)[1]);
        $this->assertSame('Against the tiers', P::icon(W::VERDICT_AGAINST)[2]);
        $this->assertSame('fa-minus', P::icon('unknown')[0]);
    }

    public function testSortsByVerdictThenTheWeakerTier(): void
    {
        $againstRust = P::sortValue($this->_snapshot(W::VERDICT_AGAINST, [], Tiers::RUST));
        $againstGold = P::sortValue($this->_snapshot(W::VERDICT_AGAINST, [], Tiers::GOLD));
        $softRust    = P::sortValue($this->_snapshot(W::VERDICT_SOFT, [], Tiers::RUST));
        $this->assertGreaterThan($againstGold, $againstRust);
        $this->assertGreaterThan($softRust, $againstGold);
    }

    public function testTheTooltipListsTheConfirmedWarningsEscaped(): void
    {
        $tip = P::tooltip($this->_snapshot(W::VERDICT_AGAINST, [$this->_weakTier()], Tiers::RUST,
            ['acknowledged_at' => '2026-10-24 17:02:00']));

        $this->assertStringContainsString('<strong>Against the tiers</strong> (1 serious warning, confirmed on save'
            . ' 2026-10-24 17:02)', $tip);
        $this->assertStringContainsString('Weak tier: the tier in use is Rust &lt;b&gt;.', $tip);
        $this->assertStringNotContainsString('Now:', $tip);
    }

    public function testTheTooltipAddsTodaysTierWhenItDiffers(): void
    {
        $snapshot = $this->_snapshot(W::VERDICT_AGAINST, [$this->_weakTier()]);
        $tip      = P::tooltip($snapshot, Tiers::BRONZE, [$this->_weakTier()]);
        $this->assertStringContainsString('Now: Bronze; today\'s reading would raise: weak tier.', $tip);
        $this->assertStringNotContainsString('Now:', P::tooltip($snapshot, Tiers::RUST, []));
    }

    public function testNoWarningIsNotAnEndorsement(): void
    {
        $tip = P::tooltip($this->_snapshot(W::VERDICT_OK, [], Tiers::GOLD));
        $this->assertStringContainsString('raised no tier warning (not the same as the tiers endorsing it)', $tip);
    }
}
