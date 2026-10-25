<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail\Concerns;

use ovidiuro\myfinance2\App\Services\RangeBoundPattern;
use ovidiuro\myfinance2\App\Services\TierSignalChip;
use ovidiuro\myfinance2\App\Services\WeakTierProfit;

/**
 * How a peak-proximity alert email names the symbol's reading in its subject and header: the action it shows and
 * the chip's words (range-bound, weak tier in profit or a tier signal), then what follows the tiers.
 */
trait DescribesPeakProximityReading
{
    /**
     * The action the email shows: the range-bound reading's (TRIM / HOLD / ADD ...), else the weak-tier-in-profit
     * one (TAKE PROFIT), else the tier signal chip's (SELL / TRIM / ADD / BUY / REVIEW), else the quadrant's.
     */
    public static function readingAction(array $cat): ?string
    {
        return $cat['range_bound']['action']
            ?? $cat['take_profit']['action']
            ?? TierSignalChip::shown($cat)['action']
            ?? $cat['action']
            ?? null;
    }

    /**
     * What follows the symbol and tiers in the subject: " => at 97 % of its 52-week range" for a range-bound alert,
     * " => up + 71.4 % on the open position, near peak: -3.68% to 6M" for a take-profit one and
     * " => Silver or better on only 19 % of the last 12 months" for a tier signal (the near-peak part only when a
     * window is near peak), " near peak => -3.68% to 6M" otherwise.
     *
     * @param string[] $parts the windows near peak, "-3.68% to 6M", largest window first
     */
    private function _subjectTail(?array $rangeBound, ?array $takeProfit, ?array $tierSignal, array $parts): string
    {
        if ($rangeBound !== null) {
            return ' => ' . RangeBoundPattern::positionText($rangeBound);
        }
        $nearPeak = !empty($parts) ? ', near peak: ' . implode(', ', $parts) : '';
        if ($takeProfit !== null) {
            return ' => ' . WeakTierProfit::gainText($takeProfit) . $nearPeak;
        }
        if ($tierSignal !== null) {
            return ' => ' . TierSignalChip::shortText($tierSignal) . $nearPeak;
        }

        return ' near peak => ' . implode(', ', $parts);
    }

    /**
     * "Tier, ACTION" for the subject and the header, omitting whichever piece is unknown ('' when both are). For a
     * range-bound, a weak-tier-in-profit or a tier signal symbol the action reads as its chip does:
     * "Tier; Range-bound, near the high: TRIM", "Tier; Weak tier in profit: TAKE PROFIT", "Tier; Rare high: SELL"
     * ("Tier; Rare high" without an action).
     *
     * @param array|null $reading RangeBoundPattern::detect(), WeakTierProfit::detect() or TierSignalChip::detect():
     *                            'label', 'action'
     */
    private function _tierSummary(
        ?string $tierLabel,
        ?string $action,
        ?array $reading = null
    ): string
    {
        if ($reading !== null) {
            $chip = $reading['label'] . (!empty($reading['action']) ? ': ' . $reading['action'] : '');

            return implode('; ', array_filter([$tierLabel, $chip]));
        }

        return implode(', ', array_filter([$tierLabel, $action]));
    }
}
