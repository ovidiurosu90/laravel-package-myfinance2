<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;

use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\StoredTierReading;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\Mail\Concerns\LinksSymbolActions;

/**
 * Pure tests for what keeps the emails consistent: the two percentage styles (the body's "- 6.31 %", as the web pages,
 * and the subject's compact "-6.31%"), the price alert's stored tier reading and the order side a reading calls for.
 * No DB, no HTTP.
 */
class EmailFormattingTest extends TestCase
{
    private const NBSP = "\u{00A0}";

    public function test_gain_plain_is_the_body_style_without_colour_or_wrapping(): void
    {
        $words = fn (string ...$parts): string => implode(self::NBSP, $parts);

        $this->assertSame($words('-', '6.31', '%'), MoneyFormat::get_formatted_gain_plain('%', -6.31));
        $this->assertSame($words('+', '1,234', '€'), MoneyFormat::get_formatted_gain_plain('€', 1234.4));
        $this->assertSame($words('0', '%'), MoneyFormat::get_formatted_gain_plain('%', 0.0));
        $this->assertSame('n/a', MoneyFormat::get_formatted_gain_plain('%', null));
    }

    public function test_signed_pct_compact_is_the_subject_style(): void
    {
        $this->assertSame('-3.68%', MoneyFormat::get_formatted_signed_pct_compact(-3.68));
        $this->assertSame('+1.20%', MoneyFormat::get_formatted_signed_pct_compact(1.2));
        $this->assertSame('0.00%', MoneyFormat::get_formatted_signed_pct_compact(0.0));
        $this->assertSame('n/a', MoneyFormat::get_formatted_signed_pct_compact(null));
    }

    public function test_stored_tier_reading_prefers_override_then_settled_then_today(): void
    {
        $none = ['range_bound' => null, 'take_profit' => null, 'tier_signal' => null];

        $settled = StoredTierReading::fromState(
            TierCalculationService::SILVER, TierCalculationService::GOLD, null, $none
        );
        $this->assertSame(TierCalculationService::GOLD, $settled['effective_tier']);
        $this->assertSame(['tier' => TierCalculationService::SILVER, 'label' => 'Silver'], $settled['today_chip']);

        $overridden = StoredTierReading::fromState(
            TierCalculationService::SILVER, TierCalculationService::GOLD, TierCalculationService::RUST, $none
        );
        $this->assertSame(TierCalculationService::RUST, $overridden['effective_tier']);
        $this->assertNull($overridden['today_chip'], 'An override already says what the computed tier was.');

        $short = StoredTierReading::fromState(TierCalculationService::BRONZE, null, null, $none);
        $this->assertSame(TierCalculationService::BRONZE, $short['effective_tier']);
        $this->assertNull($short['today_chip']);
    }

    public function test_stored_tier_reading_carries_the_chips_in_the_categorization_shape(): void
    {
        $range = ['label' => 'Range-bound, near the high', 'action' => 'TRIM'];
        $chip  = ['label' => 'Rare high', 'action' => 'SELL'];

        $reading = StoredTierReading::fromState(TierCalculationService::GOLD, TierCalculationService::GOLD, null, [
            'range_bound' => $range, 'take_profit' => [], 'tier_signal' => $chip,
        ]);

        $this->assertSame($range, $reading['range_bound']);
        $this->assertNull($reading['take_profit']);
        $this->assertSame($chip, $reading['tier_signal_chip']);
    }

    public function test_order_side_follows_the_reading_action(): void
    {
        $links = new class
        {
            use LinksSymbolActions;
        };

        $this->assertSame('SELL', $links::orderSideFor('EXIT'));
        $this->assertSame('SELL', $links::orderSideFor('TAKE PROFIT'));
        $this->assertSame('BUY', $links::orderSideFor('ADD'));
        $this->assertSame('BUY', $links::orderSideFor('ACCUMULATE'));
        $this->assertNull($links::orderSideFor('HOLD'));
        $this->assertNull($links::orderSideFor('REVIEW'));
        $this->assertNull($links::orderSideFor(null));
    }
}
