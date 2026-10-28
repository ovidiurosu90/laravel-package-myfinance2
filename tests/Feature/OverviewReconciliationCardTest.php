<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use ovidiuro\myfinance2\App\Services\OverviewReconciliation;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * The Reconciliation card renders collapsed, with growth / explained / gap in its header and the breakdown in its
 * body. Rendered from synthetic data, nothing read or written.
 */
class OverviewReconciliationCardTest extends TestCase
{
    public function test_card_renders_collapsed_with_the_summary_and_breakdown(): void
    {
        $account = (object) ['name' => 'TST Broker', 'currency' => (object) ['display_code' => '&#36;']];
        $reconciliation = OverviewReconciliation::build(
            [1 => ['balance_in_eur' => -10000.0]],
            [],
            [2 => ['accountModel' => $account, 'balance_in_eur' => 12500.0, 'total_change' => -400.0,
                'exchange_rate' => 0.5]],
            [2025 => [2 => ['TST.A' => ['total_gain_in_account_currency' => 2000.0]]]],
            [],
            [2025 => [2 => ['exchange_rate' => 0.5]]]
        );

        $html = view('myfinance2::overview.cards.reconciliation', compact('reconciliation'))->render();

        $this->assertStringContainsString('id="reconciliation-body" class="collapse"', $html);
        $this->assertStringContainsString('Reconciliation', $html);
        // growth 2,500; explained 1,000 - 200 = 800 (32 % of the growth); gap 1,700
        $this->assertStringContainsString('+ 2,500 &euro;', $html);
        $this->assertStringContainsString('+ 800.00 &euro;', $html);
        $this->assertStringContainsString('+ 1,700 &euro;', $html);
        $this->assertStringContainsString('32.00 % of the growth', $html);
        $this->assertStringNotContainsString('fa-info-circle', $html);
        $this->assertStringContainsString('TST Broker', $html);
    }
}
