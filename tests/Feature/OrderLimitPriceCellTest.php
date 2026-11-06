<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\Tests\TestCase;

/**
 * The Limit cell of the orders list, rendered on unsaved orders (no rows, no live quote), laid out
 * like the alerts' Target Price cell (AlertTargetPriceCellTest): labelled limit with the side it
 * fills on, the current price, the distance for an open order ("to go" / "past") and the Reached
 * badge.
 */
class OrderLimitPriceCellTest extends TestCase
{
    private function _render(string $action, string $status, float $limit, ?float $current): string
    {
        $currency = new Currency();
        $currency->display_code = '&dollar;';

        $item = new Order();
        $item->action      = $action;
        $item->status      = $status;
        $item->limit_price = $limit;
        $item->setRelation('tradeCurrencyModel', $currency);

        return view('myfinance2::orders.tables.partials.limit-price-cell', [
            'item'         => $item,
            'qd'           => $current !== null ? ['price' => $current] : null,
            'cp'           => $current,
            'distance'     => $item->isOpen() && $current !== null ? $item->limitDistance($current) : null,
            'currencyCode' => '&dollar;',
        ])->render();
    }

    private function _text(string $html): string
    {
        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    public function test_open_buy_not_reached_shows_the_distance_to_go(): void
    {
        $html = $this->_render('BUY', 'PLACED', 125.0, 126.28);
        $text = $this->_text($html);

        $this->assertStringContainsString('Limit ▼ 125.00 $', $text);
        $this->assertStringContainsString('now 126.28 $', $text);
        $this->assertStringContainsString('1.01% to go', $text);
        $this->assertStringNotContainsString('Reached', $text);
        // The tooltip names the move, with the currency decoded (no "&amp;dollar;").
        $this->assertStringContainsString('title="The price has to fall 1.28 $ (1.01%) to reach the limit"', $html);
    }

    public function test_reached_sell_limit_shows_the_badge_and_how_far_past(): void
    {
        $html = $this->_render('SELL', 'PLACED', 200.0, 210.0);
        $text = $this->_text($html);

        $this->assertStringContainsString('Limit ▲ 200.00 $', $text);
        $this->assertStringContainsString('Reached', $text);
        $this->assertStringContainsString('4.76% past', $text);
        $this->assertStringContainsString('the order can fill at the limit or better', $html);
    }

    public function test_reached_draft_says_it_is_not_at_the_broker(): void
    {
        $html = $this->_render('BUY', 'DRAFT', 125.0, 120.0);

        $this->assertStringContainsString('Reached', $this->_text($html));
        $this->assertStringContainsString('the order is a draft, so it is not at the broker yet', $html);
    }

    public function test_closed_order_shows_the_current_price_without_a_distance(): void
    {
        $text = $this->_text($this->_render('BUY', 'FILLED', 125.0, 120.0));

        $this->assertStringContainsString('Limit ▼ 125.00 $', $text);
        $this->assertStringContainsString('now 120.00 $', $text);
        $this->assertStringNotContainsString('past', $text);
        $this->assertStringNotContainsString('Reached', $text);
    }

    public function test_without_a_quote_shows_only_the_limit(): void
    {
        $text = $this->_text($this->_render('SELL', 'PLACED', 120.0, null));

        $this->assertStringContainsString('Limit ▲ 120.00 $', $text);
        $this->assertStringContainsString('now —', $text);
        $this->assertStringNotContainsString('to go', $text);
    }
}
