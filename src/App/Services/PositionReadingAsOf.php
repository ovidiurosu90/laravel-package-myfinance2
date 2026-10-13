<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;

/**
 * The range-bound and weak-tier-in-profit readings of a past day, for the signal backtest (TierSignalBacktest): the
 * live detectors (RangeBoundPattern, WeakTierProfit) fed with what the stored history knows as of that day, in the
 * live precedence (a range-bound symbol is never weak-tier-in-profit). Only the readings that raise an alert count:
 * range-bound TRIM (RANGE_HIGH), ADD (RANGE_LOW) and BUY (RANGE_BUY), and TAKE PROFIT.
 *
 * Inputs as of the day, and where they are simplified:
 *   - 1Y market return and your own return: the day's stored values (market_value, own_value).
 *   - 52-week closing range and the day's price: the symbol's own closes in its trade currency (the live reading
 *     uses EUR closes; the range position barely differs).
 *   - Traded: an own return exists by that day, or the symbol is held. Your record: Own Lifetime only (the live rule
 *     also accepts 2+ completed trades with 75 % won, which would need the trades replayed as of the day).
 *   - Open position and its gain: the day's held flag and holding-period return (the TAKE PROFIT line is any profit,
 *     so its sign is what matters). The tier in use: the backtest's settled tier of that day (no manual override).
 *
 * Pure: no database, no config; the detectors are passed in.
 */
final class PositionReadingAsOf
{
    private TierCalculationService $_tiers;

    public function __construct(
        private readonly RangeBoundPattern $_rangeBound = new RangeBoundPattern(),
        private readonly WeakTierProfit $_weakProfit = new WeakTierProfit()
    )
    {
        $this->_tiers = new TierCalculationService();
    }

    /**
     * The alerting reading of the day, or null.
     *
     * @param array       $row         the day's stored row (TierHistoryRepository: m, w, h, o)
     * @param ?string     $tierInUse   the settled tier of the day (today's after a lasting drop)
     * @param ?string     $settledTier the settled tier as SettledTier reports it
     * @param float[]     $closes      the closes of the 52 weeks up to and including the day (any order)
     * @param ?float      $price       the day's close
     * @param bool        $traded      whether you had traded the symbol by that day
     *
     * @return array{signal: string, action: string, owned: bool}|null
     */
    public function read(
        array $row,
        ?string $tierInUse,
        ?string $settledTier,
        array $closes,
        ?float $price,
        bool $traded
    ): ?array
    {
        $owned = !empty($row['o']);
        $range = $this->_rangeBound->detect($this->_cat($row, $closes), [
            'window_count'               => $traded ? 1 : 0,
            'windows'                    => $owned ? [['is_open' => true]] : [],
            'annualized_percentage_gain' => $row['w'] ?? null,
        ], $price);
        if ($range !== null) {
            $signal = match ($range['action']) {
                RangeBoundPattern::ACTION_TRIM => PeakProximityAlertEvent::SIGNAL_RANGE_HIGH,
                RangeBoundPattern::ACTION_ADD  => PeakProximityAlertEvent::SIGNAL_RANGE_LOW,
                RangeBoundPattern::ACTION_BUY  => PeakProximityAlertEvent::SIGNAL_RANGE_BUY,
                default                        => null,
            };

            // A range-bound symbol is read on its swing only: mid-range (HOLD / WATCH) or too high (WAIT) raises
            // nothing, and it is never weak-tier-in-profit.
            return $signal === null ? null : ['signal' => $signal, 'action' => $range['action'], 'owned' => $owned];
        }

        $profit = $this->_weakProfit->detect([
            'effective_tier' => $tierInUse,
            'settled_tier'   => $settledTier,
            'has_override'   => false,
            'is_benchmark'   => false,
            'range_bound'    => null,
        ], $owned && isset($row['h']) ? (float) $row['h'] : null);

        return $profit === null ? null : [
            'signal' => PeakProximityAlertEvent::SIGNAL_TAKE_PROFIT,
            'action' => WeakTierProfit::ACTION,
            'owned'  => true,
        ];
    }

    /**
     * The categorization entry RangeBoundPattern reads, as of the day.
     */
    private function _cat(array $row, array $closes): array
    {
        $own = isset($row['w']) ? (float) $row['w'] : null;

        return [
            'is_benchmark'     => false,
            'candidates'       => ['market_1y_pct' => $row['m'] ?? null],
            'own_rule'         => $own === null ? [] : [
                'basis' => TierDecision::BASIS_ANNUALIZED_RETURN,
                'tier'  => $this->_tiers->getTier($own),
                'value' => $own,
            ],
            'closing_extremes' => empty($closes) ? [] : ['low_eur' => min($closes), 'high_eur' => max($closes)],
        ];
    }
}
