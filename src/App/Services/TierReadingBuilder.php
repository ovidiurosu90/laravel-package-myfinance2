<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * A symbol's tier reading as the Tier and Tier History lines of /watchlist-symbols show it, in one array the shared
 * partial (watchlistsymbols/tables/partials/tier-reading) renders: the tier under each of the 7 approaches, the tier
 * in use, the flags, the superstar star, the share of the portfolio, the 12-month ribbon and the chips. Tooltips are
 * stored rendered, so a reading saved with an order (TierSnapshot) keeps its wording when the rules change later.
 *
 * Shape (schema SCHEMA_VERSION):
 *   symbol, effective_tier, has_override, is_benchmark, tier_tooltip, explanation
 *   cells        list of {key, number, name, measures, in_use, is_today, tier, value, unit, flag, title, tip}
 *   flags        list of {icon, color, title}: stale basis, market artifact, unusual move, low confidence
 *   superstar    {count, total, title} or null
 *   portfolio    {pct, mvalue_eur, color} or null
 *   history      {settled_shown, note, note_title, ribbon, ribbon_note, split} or null
 *   chips        range_bound | take_profit | tier_signal | near_peak: the chip partials' arrays, or null
 *   facts        OrderTierWarnings' input (facts()), added by the order pages only
 *
 * Pure apart from the approach catalog's config read.
 */
final class TierReadingBuilder
{
    public const SCHEMA_VERSION = 1;

    private const CHIP_KEYS = [
        'range_bound' => ['label', 'action', 'title', 'zone'],
        'take_profit' => ['label', 'action', 'title'],
        'tier_signal' => ['signal', 'label', 'action', 'title', 'severity', 'deferred_to'],
        'near_peak'   => ['label', 'action', 'title'],
    ];

    /**
     * @param array|null $cat         CategorizationService entry (null: not categorized)
     * @param array|null $healthRow   the symbol's PortfolioHealthScore row (mvalue_eur, mvalue_pct), when owned
     * @param string|null $mvalueColor the health card's colour for the share of the portfolio
     */
    public static function fromCategorization(
        string $symbol,
        ?array $cat,
        ?array $healthRow = null,
        ?string $mvalueColor = null
    ): ?array
    {
        if ($cat === null) {
            return null;
        }
        $tier = $cat['effective_tier'] ?? null;

        return [
            'symbol'         => $symbol,
            'effective_tier' => $tier,
            'has_override'   => !empty($cat['has_override']),
            'is_benchmark'   => !empty($cat['is_benchmark']),
            'tier_tooltip'   => (string) ($cat['tier_tooltip'] ?? ($cat['explanation'] ?? '')),
            'explanation'    => (string) ($cat['explanation'] ?? ''),
            'cells'          => self::cells($cat['approach_cells'] ?? []),
            'flags'          => self::_flags($cat),
            'superstar'      => $cat['superstar'] ?? null,
            'portfolio'      => ($healthRow['mvalue_eur'] ?? null) !== null ? [
                'pct'        => $healthRow['mvalue_pct'] ?? null,
                'mvalue_eur' => (float) $healthRow['mvalue_eur'],
                'color'      => $mvalueColor,
            ] : null,
            'history'        => $tier !== null ? self::_history($cat) : null,
            'chips'          => self::chips($cat),
        ];
    }

    /**
     * The reading of a symbol without a tier reading (not on the watchlist, or before the stored tier history): an
     * Unrated badge whose tooltip says why.
     */
    public static function unrated(string $symbol, string $explanation): array
    {
        return [
            'symbol'         => $symbol,
            'effective_tier' => null,
            'has_override'   => false,
            'is_benchmark'   => false,
            'tier_tooltip'   => $explanation,
            'explanation'    => $explanation,
            'cells'          => [],
            'flags'          => [],
            'superstar'      => null,
            'portfolio'      => null,
            'history'        => null,
            'chips'          => self::chips([]),
        ];
    }

    /**
     * The approach cells in display order, each with its badge tooltip rendered.
     *
     * @param array $approachCells approach => cell (TierApproachComparison::cells())
     */
    public static function cells(array $approachCells): array
    {
        if (empty($approachCells)) {
            return [];
        }
        $cells = [];
        foreach (TierApproachComparison::approaches() as $key => $approach) {
            $cell    = $approachCells[$key] ?? [];
            $isToday = $key === TierApproachComparison::MARKET_SNAPSHOT;
            $value   = TierApproachComparison::formatShort($cell['value'] ?? null, $cell['unit'] ?? '%')
                . (!empty($cell['flag']) ? '*' : '');
            $cells[] = [
                'key'      => $key,
                'number'   => $approach['number'],
                'name'     => $approach['name'],
                'measures' => $approach['measures'],
                'in_use'   => (bool) $approach['in_use'],
                'is_today' => $isToday,
                'tier'     => $cell['tier'] ?? null,
                'value'    => $cell['value'] ?? null,
                'unit'     => $cell['unit'] ?? TierApproachComparison::UNIT_PCT,
                'flag'     => !empty($cell['flag']),
                'title'    => (string) ($cell['title'] ?? ''),
                'tip'      => 'Approach ' . $approach['number'] . ', ' . $approach['name']
                    . ($approach['in_use'] ? ' (in use)' : ($isToday ? ' (today)' : '')) . ': ' . $value . '. '
                    . $approach['measures'] . ' ' . ($cell['title'] ?? ''),
            ];
        }

        return $cells;
    }

    /**
     * The chips the entry carries, each reduced to what its partial reads.
     */
    public static function chips(array $cat): array
    {
        $source = [
            'range_bound' => $cat['range_bound'] ?? null,
            'take_profit' => $cat['take_profit'] ?? null,
            'tier_signal' => $cat['tier_signal_chip'] ?? null,
            'near_peak'   => $cat['near_peak'] ?? null,
        ];
        $chips = [];
        foreach (self::CHIP_KEYS as $name => $keys) {
            $chips[$name] = empty($source[$name]) ? null
                : array_intersect_key($source[$name], array_flip($keys));
        }

        return $chips;
    }

    /**
     * OrderTierWarnings' input from a live entry.
     *
     * @param array|null $health PortfolioHealthScore::build() (signal, bronze_rust_pct), null when nothing is owned
     */
    public static function facts(
        string $symbol,
        array $cat,
        array $cells,
        ?array $health,
        NearPeakReading $nearPeak
    ): array
    {
        $signal    = $cat['tier_signal'] ?? [];
        $triggered = $nearPeak->triggeredWindows($cat['exit_zones'] ?? []);
        $windows   = $nearPeak->meaningful($triggered);

        return [
            'has_reading'            => true,
            'symbol'                 => $symbol,
            'effective_tier'         => $cat['effective_tier'] ?? null,
            'tier_basis'             => !empty($cat['has_override']) ? 'your manual tier' : self::basisText($cells),
            'index_gap_settled_tier' => self::cellTier($cells, TierApproachComparison::INDEX_GAP_SETTLED),
            'settled_tier'           => $cat['settled_tier'] ?? null,
            'take_profit'            => !empty($cat['take_profit']),
            'signal'                 => !empty($cat['is_benchmark']) ? null : ($signal['signal'] ?? null),
            'deteriorating_days'     => (int) ($signal['deteriorating_days'] ?? 0),
            'drop_from'              => $signal['drop_from'] ?? null,
            'rare_share_pct'         => $signal['at_or_above_today_pct'] ?? null,
            'range_zone'             => $cat['range_bound']['zone'] ?? null,
            'near_peak_windows'      => $windows,
            'near_peak_closest_pct'  => PeakProximityAlertService::closestProximityPct(
                array_intersect_key($triggered, array_flip($windows))
            ),
            'superstar'              => $cat['superstar'] ?? null,
            'health_signal'          => $health['signal'] ?? null,
            'bronze_rust_pct'        => $health['bronze_rust_pct'] ?? null,
            'bronze_rust_target_pct' => $health['bronze_rust_target'] ?? null,
        ];
    }

    /**
     * The approach in use and its figure, e.g. "Market Settled, - 10.6 %"; null without cells.
     */
    public static function basisText(array $cells): ?string
    {
        foreach ($cells as $cell) {
            if (!empty($cell['in_use'])) {
                return $cell['name'] . ', ' . TierApproachComparison::formatShort($cell['value'], $cell['unit'])
                    . (!empty($cell['flag']) ? '*' : '');
            }
        }

        return null;
    }

    public static function cellTier(array $cells, string $key): ?string
    {
        foreach ($cells as $cell) {
            if ($cell['key'] === $key) {
                return $cell['tier'];
            }
        }

        return null;
    }

    /**
     * The icons after the approaches on the Tier line, in display order. The low-confidence one is left out when
     * the artifact icon already explains the fallback.
     */
    private static function _flags(array $cat): array
    {
        $explanation = (string) ($cat['explanation'] ?? '');
        $override    = !empty($cat['has_override']);
        $artifact    = !empty($cat['market_artifact']);
        $flags       = [];
        if (!empty($cat['is_stale'])) {
            $flags[] = ['icon' => 'fa-clock-o', 'color' => 'text-warning', 'title' => $explanation];
        }
        if ($artifact) {
            $flags[] = ['icon' => 'fa-exclamation-triangle', 'color' => 'text-danger',
                'title' => (string) ($cat['market_artifact_note'] ?? '')];
        }
        if (!empty($cat['market_unusual'])) {
            $flags[] = ['icon' => 'fa-exclamation-triangle', 'color' => 'text-warning',
                'title' => (string) ($cat['market_unusual_note'] ?? '')];
        }
        if (!$override && !$artifact && ($cat['confidence'] ?? TierDecision::CONFIDENCE_HIGH)
            === TierDecision::CONFIDENCE_LOW
        ) {
            $flags[] = ['icon' => 'fa-exclamation-circle', 'color' => 'text-warning', 'title' => $explanation];
        }

        return $flags;
    }

    /**
     * The Tier History line's own part: the ribbon, why there is no median when there is none, the split-year note.
     */
    private static function _history(array $cat): ?array
    {
        $history = $cat['tier_history'] ?? null;
        if ($history === null) {
            return null;
        }
        $override = !empty($cat['has_override']);
        $status   = $cat['tier_signal']['status'] ?? null;

        return [
            'settled_shown' => !$override && ($history['settled_value'] ?? null) !== null,
            'note'          => match (true) {
                $override => 'manual override',
                $status === SettledTierResolver::STATUS_DETERIORATING => 'follows today after a lasting drop',
                $status === SettledTierResolver::STATUS_INSUFFICIENT  => 'too short, today\'s reading used',
                default => 'today\'s reading used',
            },
            'note_title'    => (string) ($cat['tier_tooltip'] ?? ($cat['explanation'] ?? '')),
            'ribbon'        => $history['ribbon'] ?? [],
            'ribbon_note'   => (string) ($history['ribbon_note'] ?? ''),
            'split'         => $history['split'] ?? null,
        ];
    }
}
