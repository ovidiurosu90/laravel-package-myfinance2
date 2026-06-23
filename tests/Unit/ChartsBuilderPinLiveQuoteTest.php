<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\ChartsBuilder;

/**
 * Unit tests for ChartsBuilder::pinLiveQuote — pure logic, no DB or cache.
 */
class ChartsBuilderPinLiveQuoteTest extends TestCase
{
    private function _series(): array
    {
        return [
            ['time' => '2026-06-19', 'value' => 540.0],
            ['time' => '2026-06-22', 'value' => 551.63],
        ];
    }

    /**
     * Same date as the series tail (e.g. after-hours of that session): the close is
     * overwritten with the live price, and no point is added.
     */
    public function test_overwrites_tail_when_quote_is_same_date(): void
    {
        $result = ChartsBuilder::pinLiveQuote(
            $this->_series(), 546.50, new \DateTime('2026-06-22 23:30:00')
        );

        $this->assertCount(2, $result);
        $this->assertSame('2026-06-22', $result[1]['time']);
        $this->assertSame(546.50, $result[1]['value']);
    }

    /**
     * Live quote newer than the tail (e.g. pre/post-market dated to a later day with no
     * persisted stat yet): a new point is appended so the chart ends on the latest price.
     */
    public function test_appends_point_when_quote_is_newer(): void
    {
        $result = ChartsBuilder::pinLiveQuote(
            $this->_series(), 546.50, new \DateTime('2026-06-23 01:59:59')
        );

        $this->assertCount(3, $result);
        $this->assertSame('2026-06-23', $result[2]['time']);
        $this->assertSame(546.50, $result[2]['value']);
        // Existing tail is left intact.
        $this->assertSame(551.63, $result[1]['value']);
    }

    /**
     * A quote older than the tail must never rewrite history.
     */
    public function test_leaves_series_untouched_when_quote_is_older(): void
    {
        $series = $this->_series();
        $result = ChartsBuilder::pinLiveQuote($series, 500.0, new \DateTime('2026-06-10 12:00:00'));

        $this->assertSame($series, $result);
    }

    /**
     * No live price (null) leaves the stored series unchanged.
     */
    public function test_null_price_leaves_series_untouched(): void
    {
        $series = $this->_series();
        $result = ChartsBuilder::pinLiveQuote($series, null, new \DateTime('2026-06-23 01:59:59'));

        $this->assertSame($series, $result);
    }

    /**
     * An empty series stays empty (nothing to pin to).
     */
    public function test_empty_series_stays_empty(): void
    {
        $this->assertSame([], ChartsBuilder::pinLiveQuote([], 546.50, new \DateTime('2026-06-23')));
    }
}
