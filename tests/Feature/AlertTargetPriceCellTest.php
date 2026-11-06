<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * The Target Price cell of the alerts list, rendered on unsaved alerts (no rows, no live quote):
 * labelled target and current price, the distance measured on the current price ("to go" / "past")
 * and the Reached badge.
 */
class AlertTargetPriceCellTest extends TestCase
{
    private function _render(string $alertType, float $target, ?float $current): string
    {
        $item = new PriceAlert();
        $item->alert_type   = $alertType;
        $item->target_price = $target;
        $item->status       = 'ACTIVE';

        return view('myfinance2::alerts.tables.partials.target-price-cell', [
            'item'         => $item,
            'qd'           => $current !== null ? ['price' => $current] : null,
            'cp'           => $current,
            'distance'     => $current !== null ? $item->targetDistance($current) : null,
            'currencyCode' => '&dollar;',
        ])->render();
    }

    private function _text(string $html): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    public function test_target_not_reached_shows_the_distance_to_go(): void
    {
        $html = $this->_render('PRICE_BELOW', 596.20, 610.25);
        $text = $this->_text($html);

        $this->assertStringContainsString('Target ▼ 596.20 $', $text);
        $this->assertStringContainsString('now 610.25 $', $text);
        $this->assertStringContainsString('2.30% to go', $text);
        $this->assertStringNotContainsString('Reached', $text);
        // The tooltip names the move, with the currency decoded (no "&amp;dollar;").
        $this->assertStringContainsString('title="The price has to fall 14.05 $ (2.30%) to reach the target"', $html);
    }

    public function test_reached_target_shows_the_badge_and_how_far_past(): void
    {
        $html = $this->_render('PRICE_BELOW', 309.38, 261.37);
        $text = $this->_text($html);

        $this->assertStringContainsString('Reached', $text);
        $this->assertStringContainsString('18.37% past', $text);
        $this->assertStringContainsString('the alert fires on its next check', $html);
    }

    public function test_without_a_quote_shows_only_the_target(): void
    {
        $text = $this->_text($this->_render('PRICE_ABOVE', 120.0, null));

        $this->assertStringContainsString('Target ▲ 120.00 $', $text);
        $this->assertStringNotContainsString('to go', $text);
        $this->assertStringNotContainsString('Reached', $text);
    }
}
