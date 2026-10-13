<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierSignalSeverity;
use PHPUnit\Framework\TestCase;

/**
 * Severity and caution flags of a tier signal chip: never a gate, only how strongly the price and the position back
 * the signal.
 */
class TierSignalSeverityTest extends TestCase
{
    private TierSignalSeverity $severity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->severity = new TierSignalSeverity(5.0, 10.0, 70.0, ['6m', '1y', '2y'],
            ['3m' => 2.0, '6m' => 5.0, '1y' => 8.0, '2y' => 10.0]);
    }

    private function chip(?string $action, string $signal = SettledTierResolver::SIGNAL_RARE_HIGH): array
    {
        return [
            'signal' => $signal, 'label' => 'Rare high', 'action' => $action, 'summary' => 'Why.',
            'deferred_to' => null, 'severity' => null, 'reasons' => [], 'flags' => [],
        ];
    }

    private function context(array $overrides = []): array
    {
        return array_replace([
            'position_pct' => 2.0, 'rsi' => 50.0, 'below_ma200' => false, 'near_peak' => [],
            'danger_zone' => false, 'market_artifact' => false,
        ], $overrides);
    }

    public function testSellWithoutBackingIsMediumAndTrimIsLow(): void
    {
        $this->assertSame('MEDIUM', $this->severity->apply($this->chip('SELL'), $this->context())['severity']);
        $this->assertSame('LOW', $this->severity->apply($this->chip('TRIM'), $this->context())['severity']);
    }

    public function testSellIsHighOnSizeRsiOrALongWindowNearPeak(): void
    {
        $chip = $this->severity->apply($this->chip('TRIM'), $this->context([
            'position_pct' => 6.14, 'rsi' => 72.4, 'near_peak' => ['1y'],
        ]));

        $this->assertSame('HIGH', $chip['severity']);
        $this->assertSame(
            ["position 6.1\u{00A0}% of the portfolio", 'RSI 72, overbought', '1Y near peak'],
            $chip['reasons']
        );
        $this->assertStringEndsWith(
            "Severity: High (position 6.1\u{00A0}% of the portfolio; RSI 72, overbought; 1Y near peak).",
            $chip['title']
        );
    }

    public function testBuyIsMediumWithCautionFlags(): void
    {
        $chip = $this->severity->apply($this->chip('ADD', SettledTierResolver::SIGNAL_RARE_LOW), $this->context([
            'position_pct' => 12.0, 'below_ma200' => true, 'danger_zone' => true, 'market_artifact' => true,
        ]));

        $this->assertSame('MEDIUM', $chip['severity']);
        $this->assertCount(4, $chip['flags']);
        $this->assertStringContainsString('Caution: price under MA200; quadrant Danger zone; position already'
            . " 12.0\u{00A0}% of the portfolio;", $chip['title']);
    }

    public function testReviewIsMediumAndNoActionHasNoSeverity(): void
    {
        $this->assertSame('MEDIUM', $this->severity->apply(
            $this->chip('REVIEW', SettledTierResolver::SIGNAL_DETERIORATING), $this->context()
        )['severity']);

        $chip = $this->severity->apply($this->chip(null), $this->context(['rsi' => 80.0]));
        $this->assertNull($chip['severity']);
        $this->assertStringNotContainsString('Severity', $chip['title']);
    }

    public function testAttachUsesPositionSizeIndicatorsAndNearPeakWindows(): void
    {
        $items = [
            'AAA' => [
                'open_positions'       => [['symbol' => 'AAA']],
                'technical_indicators' => ['rsi' => 40.0, 'ma200_diff_pct' => 3.0],
                'categorization'       => [
                    'quadrant'         => 'VOLATILE_WINNERS',
                    // 3M near its peak is context only; 6M at -6 % is outside its 5 % threshold, 1Y at -7 % inside 8 %.
                    'exit_zones'       => ['3m' => ['proximity_pct' => -1.0], '6m' => ['proximity_pct' => -6.0],
                        '1y' => ['proximity_pct' => -7.0]],
                    'tier_signal_chip' => $this->chip('SELL'),
                ],
            ],
            'BBB' => ['categorization' => [
                'tier_signal_chip' => ['deferred_to' => 'range_bound'] + $this->chip('SELL'),
            ]],
        ];
        $health = ['silver_symbols' => [['symbol' => 'AAA', 'mvalue_pct' => 3.0]]];

        $out = $this->severity->attach($items, $health);

        $this->assertSame('HIGH', $out['AAA']['categorization']['tier_signal_chip']['severity']);
        $this->assertSame(['1Y near peak'], $out['AAA']['categorization']['tier_signal_chip']['reasons']);
        $this->assertNull($out['BBB']['categorization']['tier_signal_chip']['severity']);
    }
}
