<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\SettledTierResolver;
use ovidiuro\myfinance2\App\Services\TierSignalChip;
use ovidiuro\myfinance2\Mail\Concerns\DescribesPeakProximityReading;
use PHPUnit\Framework\TestCase;

/**
 * How the peak-proximity email names a symbol's reading: the action shown and the subject's tiers and tail, for the
 * near-peak, take-profit and tier signal alerts.
 */
class PeakProximityReadingTextTest extends TestCase
{
    private object $text;

    protected function setUp(): void
    {
        parent::setUp();
        $this->text = new class
        {
            use DescribesPeakProximityReading;

            public function tierSummary(...$args): string
            {
                return $this->_tierSummary(...$args);
            }

            public function subjectTail(...$args): string
            {
                return $this->_subjectTail(...$args);
            }
        };
    }

    private function chip(?string $action, array $overrides = []): array
    {
        $cat = [
            'today_tier'  => 'RUST',
            'tier_signal' => array_replace([
                'settled_tier' => 'RUST', 'signal' => SettledTierResolver::SIGNAL_DETERIORATING, 'action' => $action,
                'deteriorating_days' => 35, 'explanation' => 'Two tiers below its settled Silver tier for 35 days.',
                'drop_from' => 'SILVER',
                'first_date' => '2025-10-13', 'last_date' => '2026-10-12',
            ], $overrides),
        ];

        return (new TierSignalChip(20))->detect($cat);
    }

    public function testReadingActionPrefersRangeThenTakeProfitThenTheShownChipThenTheQuadrant(): void
    {
        $chip = $this->chip('REVIEW');
        $this->assertSame('TRIM', $this->text::readingAction([
            'range_bound' => ['action' => 'TRIM'], 'take_profit' => ['action' => 'TAKE PROFIT'], 'action' => 'EXIT',
        ]));
        $this->assertSame('REVIEW', $this->text::readingAction(['tier_signal_chip' => $chip, 'action' => 'EXIT']));
        $this->assertSame('EXIT', $this->text::readingAction([
            'tier_signal_chip' => ['deferred_to' => 'take_profit'] + $chip, 'action' => 'EXIT',
        ]));
        $this->assertSame('EXIT', $this->text::readingAction(['action' => 'EXIT']));
    }

    public function testTierSummaryReadsAsTheChip(): void
    {
        $this->assertSame('Rust; Deteriorating: REVIEW', $this->text->tierSummary('Rust', 'REVIEW',
            $this->chip('REVIEW')));
        $this->assertSame('Rust; Deteriorating', $this->text->tierSummary('Rust', null, $this->chip(null)));
        $this->assertSame('Bronze, EXIT', $this->text->tierSummary('Bronze', 'EXIT'));
    }

    public function testSubjectTailForATierSignal(): void
    {
        $chip = $this->chip('REVIEW');
        $this->assertSame(' => down from Silver for 35 trading days', $this->text->subjectTail(null, null, $chip, []));
        $this->assertSame(
            ' => down from Silver for 35 trading days, near peak: -2.74% to 6M',
            $this->text->subjectTail(null, null, $chip, ['-2.74% to 6M'])
        );
        $this->assertSame(' near peak => -2.74% to 6M', $this->text->subjectTail(null, null, null, ['-2.74% to 6M']));
    }
}
