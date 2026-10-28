<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use ovidiuro\myfinance2\Tests\TestCase;

/**
 * The Gains Per Year card renders the gains / losses filter and the search, and gives the filter script
 * (overview.scripts.gains-per-year) what it needs: each symbol row's amounts and each account's name and currency,
 * on the cells whose totals it recomputes. Rendered from synthetic data, nothing read or written.
 */
class GainsPerYearFiltersTest extends TestCase
{
    public function test_card_renders_the_filters_and_the_rows_data(): void
    {
        $account = (object) [
            'name'     => 'TST Broker',
            'currency' => (object) ['display_code' => '&#36;', 'iso_code' => 'USD'],
        ];
        $html = view('myfinance2::overview.cards.gains-per-year', [
            'gainsPerYear'     => [2025 => [7 => [
                'TST.WIN'  => ['accountModel' => $account, 'total_gain_in_account_currency' => 500.0],
                'TST.LOSS' => ['accountModel' => $account, 'total_gain_in_account_currency' => -200.0],
            ]]],
            'dividendsPerYear' => [],
            'eurRatesPerYear'  => [2025 => [7 => ['exchange_rate' => 0.5, 'eurusd_rate' => 2.0,
                'conversion_pair' => 'USD->EUR']]],
        ])->render();

        $this->assertStringContainsString('data-gpy-mode="all"', $html);
        $this->assertStringContainsString('data-gpy-mode="gains"', $html);
        $this->assertStringContainsString('data-gpy-mode="losses"', $html);
        $this->assertStringContainsString('id="gpy-search"', $html);

        $this->assertStringContainsString('data-gpy-name="TST Broker"', $html);
        $this->assertStringContainsString('data-gpy-symbol="TST.LOSS"', $html);
        $this->assertStringContainsString('data-gpy-gain="-200"', $html);
        $this->assertStringContainsString('data-gpy-gain-eur="-100"', $html);
        foreach (['gain', 'div', 'gain-eur', 'div-eur', 'total-eur'] as $col) {
            $this->assertStringContainsString('data-gpy-col="' . $col . '"', $html);
        }
        $this->assertStringContainsString('class="gpy-foot-label"', $html);
    }
}
