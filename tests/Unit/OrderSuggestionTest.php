<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;

use ovidiuro\myfinance2\App\Services\OrderSuggestion;

/**
 * Pure tests for the prefill figures: the order form's per-side figures (a limit 1 % from the price, a quantity worth
 * about 5,000, a SELL capped at the holding) and the alert form's guessed side, which keeps its 2.5 % offset.
 */
class OrderSuggestionTest extends TestCase
{
    private OrderSuggestion $_suggestion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->_suggestion = new OrderSuggestion();
    }

    /**
     * A quote at 100 EUR, $pctBelowHigh % below its 52-week closing high.
     */
    private function _financeData(float $pctBelowHigh = 5.0): array
    {
        return [
            'price'       => 100.0,
            'currency'    => 'EUR',
            'closingHigh' => 100.0 / (1 - $pctBelowHigh / 100),
            'closingLow'  => 50.0,
        ];
    }

    public function testBuyLimitIsOnePercentBelowThePrice(): void
    {
        $buy = $this->_suggestion->forSide('BUY', $this->_financeData(), 1.0, 'EUR');

        $this->assertSame(99.0, $buy['limit_price']);
        $this->assertSame(51.0, $buy['suggested_qty']); // 5,000 / 99
        $this->assertFalse($buy['is_partial_sell']);
    }

    public function testSellLimitIsOnePercentAboveThePrice(): void
    {
        $sell = $this->_suggestion->forSide('SELL', $this->_financeData(), 1.0, 'EUR', 200.0);

        $this->assertSame(101.0, $sell['limit_price']);
        $this->assertSame(50.0, $sell['suggested_qty']); // 5,000 / 101, out of 200 held
        $this->assertTrue($sell['is_partial_sell']);
        $this->assertFalse($sell['weak_signal']);
    }

    public function testSellIsCappedAtTheHolding(): void
    {
        $sell = $this->_suggestion->forSide('SELL', $this->_financeData(), 1.0, 'EUR', 25.0);

        $this->assertSame(25.0, $sell['suggested_qty']);
        $this->assertFalse($sell['is_partial_sell']);
    }

    /**
     * 60 held: selling 50 would leave 10 (about 1,010), less than the 5,000 target, so all of it is sold.
     */
    public function testSellTakesAllWhenTheRestWouldBeSmall(): void
    {
        $sell = $this->_suggestion->forSide('SELL', $this->_financeData(), 1.0, 'EUR', 60.0);

        $this->assertSame(60.0, $sell['suggested_qty']);
        $this->assertFalse($sell['is_partial_sell']);
    }

    public function testSellWithNothingHeldSuggestsNoQuantity(): void
    {
        $sell = $this->_suggestion->forSide('SELL', $this->_financeData(), 1.0, 'EUR', 0.0);

        $this->assertNull($sell['suggested_qty']);
        $this->assertSame(101.0, $sell['limit_price']);
    }

    public function testBuyWithinFifteenPercentOfTheHighIsAWeakSignal(): void
    {
        $this->assertTrue($this->_suggestion->forSide('BUY', $this->_financeData(5.0), 1.0, 'EUR')['weak_signal']);
        $this->assertFalse($this->_suggestion->forSide('BUY', $this->_financeData(20.0), 1.0, 'EUR')['weak_signal']);
    }

    public function testQuantityIsSizedInTheAccountCurrency(): void
    {
        // A EUR account buying a USD symbol at 100 USD, 1 EUR = 1.2 USD: 6,000 USD / 99.
        $data = ['currency' => 'USD'] + $this->_financeData();

        $buy = $this->_suggestion->forSide('BUY', $data, 1.2, 'EUR');

        $this->assertSame(61.0, $buy['suggested_qty']);
    }

    /**
     * The alert form still reads compute(): a guessed side and a target 2.5 % away.
     */
    public function testComputeKeepsTheAlertFormsOffset(): void
    {
        $held    = $this->_suggestion->compute($this->_financeData(5.0), 10.0, 1.0, 'EUR');
        $notHeld = $this->_suggestion->compute($this->_financeData(5.0), 0.0, 1.0, 'EUR');

        $this->assertSame('SELL', $held['action']);
        $this->assertSame(102.5, $held['limit_price']);
        $this->assertSame('BUY', $notHeld['action']);
        $this->assertSame(97.5, $notHeld['limit_price']);
        $this->assertTrue($notHeld['weak_signal']);
    }
}
