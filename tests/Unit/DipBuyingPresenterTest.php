<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\DipBuyingPresenter;

/**
 * DipBuyingPresenter display decisions that must never contradict the ladder: deployed never reads
 * below 0 (a note says what came back instead), and the "no current dip" note only claims "above the
 * first band" when the plan's band agrees.
 */
class DipBuyingPresenterTest extends TestCase
{
    public function test_deployed_reads_zero_with_the_cash_that_came_back(): void
    {
        $current = [
            'peak_date' => '2026-08-14', 'pool_eur' => 120000.0,
            'actual'    => ['deployed_eur' => 0.0, 'deployed_pct' => 0.0, 'cash_change_eur' => 20000.0],
            'guided'    => ['target_pct' => 30.0],
        ];

        $basis = DipBuyingPresenter::deployBasis([], $current);

        $this->assertSame(0.0, $basis['deployed_pct']);
        $this->assertSame("cash +€20,000 since 14 Aug '26", $basis['note']);
    }

    public function test_plan_basis_net_sold_reads_zero_with_a_note(): void
    {
        $basis = DipBuyingPresenter::deployBasis(
            ['deployed_eur' => -1500.0, 'deployed_pct' => -1.5, 'pool_amount_eur' => 100000.0,
             'anchor_date' => '2026-10-12'],
            null
        );

        $this->assertSame(0.0, $basis['deployed_pct']);
        $this->assertSame(0.0, $basis['deployed_eur']);
        $this->assertSame("net sold €1,500 since 12 Oct '26", $basis['note']);
    }

    public function test_no_dip_note_never_contradicts_the_ladder(): void
    {
        $this->assertNull(DipBuyingPresenter::noDipNote(['effective_dd_pct' => 10.56, 'target_pct' => 30.0], []));

        $above = DipBuyingPresenter::noDipNote(['effective_dd_pct' => 4.2, 'target_pct' => 0.0], null);
        $this->assertStringContainsString("above the ladder's first band", $above);

        $inBand = DipBuyingPresenter::noDipNote(['effective_dd_pct' => 10.56, 'target_pct' => 30.0], null);
        $this->assertStringNotContainsString('above the ladder', $inBand);
        $this->assertStringContainsString('targets 30% of the fallback pool', $inBand);
    }

    /**
     * The "Deployed so far" tooltip: the cash-used definition, then the dip's flows (bought, sold, the
     * biggest symbols, other money in, the cash at both ends) and why deployed reads 0%.
     */
    public function test_deployed_tooltip_explains_the_flows(): void
    {
        $ce = [
            'peak_date' => '2026-08-14', 'pool_eur' => 120000.0,
            'actual'    => [
                'deployed_pct' => 0.0,
                'flows'        => [
                    'bought_eur' => 60000.0, 'buy_count' => 12, 'sold_eur' => 75000.0, 'sell_count' => 8,
                    'top_buys'   => ['TST.A' => 30000.0, 'TST.B' => 20000.0],
                    'top_sells'  => ['TST.C' => 40000.0, '<b>X</b>' => 1.0],
                    'cash_start_eur' => 100000.0, 'cash_end_eur' => 120000.0,
                    'cash_change_eur' => 20000.0, 'other_eur' => 5000.0,
                ],
            ],
        ];

        $html = DipBuyingPresenter::deployedTooltip($ce);
        $text = preg_replace('/\s+/', ' ', html_entity_decode(preg_replace('/<[^>]+>/', ' ', $html), ENT_QUOTES));
        $text = preg_replace('/ ([:)])/', '$1', $text);

        $this->assertStringContainsString('Deployed = cash used from your reserve', $text);
        $this->assertStringContainsString("Since 14 Aug '26", $text);
        $this->assertStringContainsString('Bought (12 buys) €60,000', $text);
        $this->assertStringContainsString('TST.A €30,000, TST.B €20,000', $text);
        $this->assertStringContainsString('Sold (8 sells) €75,000', $text);
        $this->assertStringContainsString('Other money in €5,000', $text);
        $this->assertStringContainsString('Cash €100,000 → €120,000 (+€20,000)', $text);
        $this->assertStringContainsString('none of the reserve was used: deployed 0%', $text);
        // Symbols are escaped.
        $this->assertStringContainsString('&lt;b&gt;X&lt;/b&gt;', $html);

        $this->assertNull(DipBuyingPresenter::deployedTooltip(null));
    }

    /**
     * The email's "Trades this dip": newest first as the service lists them, a buy with its trade detail, EUR
     * amount and entry drawdown, a sell without one; only the newest EPISODE_TRADES_SHOWN rows, the rest counted.
     */
    public function test_episode_trades_formats_the_dip_trades(): void
    {
        $trade = fn (string $date, string $action, ?float $dd): array => [
            'date' => $date, 'action' => $action, 'symbol' => 'TST.A', 'eur' => 4321.6, 'dd' => $dd,
            'quantity' => 12.0, 'unit_price' => 410.5, 'currency' => '&#36;',
        ];
        $ce = ['actual' => ['flows' => ['trades' => [
            $trade('2026-10-20', 'SELL', null), $trade('2026-09-03', 'BUY', 7.84),
        ]]]];

        $trades = DipBuyingPresenter::episodeTrades($ce);

        $this->assertSame(0, $trades['more']);
        $this->assertSame([
            ['date' => "20 Oct '26", 'action' => 'sell', 'trade' => 'sell TST.A 12x @ 410.50 $', 'eur' => '€4,322',
             'entry_dd' => null],
            ['date' => "03 Sep '26", 'action' => 'buy', 'trade' => 'buy TST.A 12x @ 410.50 $', 'eur' => '€4,322',
             'entry_dd' => '-7.84%'],
        ], $trades['rows']);

        $many = ['actual' => ['flows' => ['trades' => array_fill(0, 13, $trade('2026-09-03', 'BUY', 1.0))]]];
        $capped = DipBuyingPresenter::episodeTrades($many);
        $this->assertCount(DipBuyingPresenter::EPISODE_TRADES_SHOWN, $capped['rows']);
        $this->assertSame(13 - DipBuyingPresenter::EPISODE_TRADES_SHOWN, $capped['more']);

        $this->assertSame(['rows' => [], 'more' => 0], DipBuyingPresenter::episodeTrades(null));
    }

    /**
     * The open episode reads as the dip still in progress, from its own peak; a pullback after a closed
     * episode keeps the local-peak wording.
     */
    public function test_ce_labels_name_the_open_dip(): void
    {
        $open = DipBuyingPresenter::ceLabels(['is_open' => true]);
        $this->assertSame('Current dip', $open['title']);
        $this->assertSame("this dip's peak", $open['peak']);

        $pullback = DipBuyingPresenter::ceLabels(['is_open' => false]);
        $this->assertSame('Current pullback', $pullback['title']);
        $this->assertSame('the most recent local peak', $pullback['peak']);
    }
}
