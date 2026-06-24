<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\DrawdownService;

/**
 * Unit tests for DrawdownService::overlayLivePrice — pure logic, no DB or cache.
 */
class DrawdownOverlayLivePriceTest extends TestCase
{
    private function _entry(): array
    {
        return [
            'latest_price_eur' => 100.0,
            'momentum_annualized_pct' => 20.0,
            'momenta' => [
                '3m' => 50.0,  // raw +50%
                '6m' => 30.0,  // raw +30%
                '1y' => 20.0,  // 20%/y CAGR
                '2y' => 10.0,  // 10%/y CAGR
            ],
            'exit_zones' => [
                '3m' => [
                    'peak_price_eur'    => 110.0,
                    'peak_price_native' => 120.0,
                    'peak_currency'     => 'USD',
                    'peak_price_date'   => '2026-06-22',
                    'proximity_pct'     => 0.0,
                    'in_zone'           => true,
                ],
            ],
            'exit_zone' => null,
        ];
    }

    /**
     * A live price below the stored close rescales every window's market return by the
     * live/stored ratio (raw windows linearly, annualized windows through the CAGR).
     */
    public function test_overlay_rescales_momenta_by_live_ratio(): void
    {
        // liveEur 90 vs stored 100 => scale 0.9.
        $out = DrawdownService::overlayLivePrice($this->_entry(), 90.0, 108.0);

        $this->assertEqualsWithDelta(35.0, $out['momenta']['3m'], 0.001);   // 1.5 * 0.9 - 1
        $this->assertEqualsWithDelta(17.0, $out['momenta']['6m'], 0.001);   // 1.3 * 0.9 - 1
        $this->assertEqualsWithDelta(8.0,  $out['momenta']['1y'], 0.001);   // 1.2 * 0.9 - 1
        // 2y: stored ratio 1.1^2 = 1.21; live 1.089; annualized sqrt(1.089) - 1.
        $this->assertEqualsWithDelta(4.3551, $out['momenta']['2y'], 0.001);
        $this->assertEqualsWithDelta(8.0, $out['momentum_annualized_pct'], 0.001);
    }

    /**
     * Peak proximity is recomputed in the native trade currency (FX-free), and the exit-zone
     * flag tracks the live price against the historical peak.
     */
    public function test_overlay_recomputes_proximity_in_native(): void
    {
        // Native 108 vs peak 120 => -10%, still within the 0.85 exit zone.
        $out = DrawdownService::overlayLivePrice($this->_entry(), 90.0, 108.0);
        $this->assertEqualsWithDelta(-10.0, $out['exit_zones']['3m']['proximity_pct'], 0.001);
        $this->assertTrue($out['exit_zones']['3m']['in_zone']);
        // Historical peak is preserved when the live price is below it.
        $this->assertSame(120.0, $out['exit_zones']['3m']['peak_price_native']);
        $this->assertSame('2026-06-22', $out['exit_zones']['3m']['peak_price_date']);

        // A deeper drop leaves the exit zone.
        $deep = DrawdownService::overlayLivePrice($this->_entry(), 80.0, 96.0);
        $this->assertEqualsWithDelta(-20.0, $deep['exit_zones']['3m']['proximity_pct'], 0.001);
        $this->assertFalse($deep['exit_zones']['3m']['in_zone']);
    }

    /**
     * A live price above the window peak (a fresh high reached in pre/post-market) becomes the
     * new peak, dated today, at 0% from peak.
     */
    public function test_overlay_live_high_sets_new_peak(): void
    {
        $out = DrawdownService::overlayLivePrice($this->_entry(), 115.0, 130.0);

        $this->assertSame(130.0, $out['exit_zones']['3m']['peak_price_native']);
        $this->assertSame(115.0, $out['exit_zones']['3m']['peak_price_eur']);
        $this->assertSame(Carbon::today()->format('Y-m-d'), $out['exit_zones']['3m']['peak_price_date']);
        $this->assertSame(0.0, $out['exit_zones']['3m']['proximity_pct']);
        $this->assertTrue($out['exit_zones']['3m']['in_zone']);
    }

    /**
     * A live price equal to the stored close leaves the market-return figures untouched.
     */
    public function test_overlay_noop_when_live_equals_close(): void
    {
        $entry = $this->_entry();
        // Same EUR close, same native peak: no Gain change, no proximity change.
        $out = DrawdownService::overlayLivePrice($entry, 100.0, 120.0);

        $this->assertSame(50.0, $out['momenta']['3m']);
        $this->assertEqualsWithDelta(0.0, $out['exit_zones']['3m']['proximity_pct'], 0.001);
    }

    /**
     * Peak proximity ("From peak") is overlaid even when the stored close (latest_price_eur) is
     * absent, e.g. an older drawdown cache entry built before that field existed. Only the
     * market-return rescale, which needs the stored close, is skipped.
     */
    public function test_overlay_proximity_without_latest_price(): void
    {
        $entry = $this->_entry();
        $entry['latest_price_eur'] = null;

        $out = DrawdownService::overlayLivePrice($entry, 90.0, 108.0);

        // From peak still recomputed from the live native price vs the stored peak.
        $this->assertEqualsWithDelta(-10.0, $out['exit_zones']['3m']['proximity_pct'], 0.001);
        // Gain is left as-is because the stored close is unavailable.
        $this->assertSame(50.0, $out['momenta']['3m']);
    }

    /**
     * Native-only live price (no live EUR rate, e.g. a foreign-currency watchlist symbol with no
     * held position): peak proximity is still refreshed FX-free in the native currency, the market
     * return is left untouched (it needs the live EUR price), and the cached EUR peak is preserved
     * rather than zeroed.
     */
    public function test_overlay_native_only_refreshes_proximity(): void
    {
        // liveEur 0 (unavailable), liveNative 108 vs peak 120 => -10%, still in the exit zone.
        $out = DrawdownService::overlayLivePrice($this->_entry(), 0.0, 108.0);

        $this->assertEqualsWithDelta(-10.0, $out['exit_zones']['3m']['proximity_pct'], 0.001);
        $this->assertTrue($out['exit_zones']['3m']['in_zone']);
        // Gain is left as-is: rescaling needs the live EUR price, which is unavailable.
        $this->assertSame(50.0, $out['momenta']['3m']);
        // The cached EUR peak is kept (not overwritten with 0) since there is no live EUR price.
        $this->assertSame(110.0, $out['exit_zones']['3m']['peak_price_eur']);
        $this->assertSame(120.0, $out['exit_zones']['3m']['peak_price_native']);
    }

    /**
     * Native-only live price above the stored peak sets the new native peak (dated today) at 0%
     * from peak, while the cached EUR peak is preserved because there is no live EUR price.
     */
    public function test_overlay_native_only_live_high_keeps_eur_peak(): void
    {
        $out = DrawdownService::overlayLivePrice($this->_entry(), 0.0, 130.0);

        $this->assertSame(130.0, $out['exit_zones']['3m']['peak_price_native']);
        $this->assertSame(110.0, $out['exit_zones']['3m']['peak_price_eur']); // preserved, not zeroed
        $this->assertSame(Carbon::today()->format('Y-m-d'), $out['exit_zones']['3m']['peak_price_date']);
        $this->assertSame(0.0, $out['exit_zones']['3m']['proximity_pct']);
        $this->assertTrue($out['exit_zones']['3m']['in_zone']);
    }

    /**
     * With neither a usable EUR nor a usable native live price, the entry is returned unchanged.
     */
    public function test_overlay_noop_when_no_usable_price(): void
    {
        $entry = $this->_entry();

        $this->assertSame($entry, DrawdownService::overlayLivePrice($entry, 0.0, 0.0));
        $this->assertSame($entry, DrawdownService::overlayLivePrice($entry, 0.0, null));
    }
}
