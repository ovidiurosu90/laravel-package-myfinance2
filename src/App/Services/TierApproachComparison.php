<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * Side-by-side tiers of the owned symbols under several ways of measuring return, for the health card,
 * to judge which one should drive the allocation. Each approach gives every symbol a value and a tier,
 * and a portfolio split (Platinum + Gold / Silver / Bronze + Rust by market value) with its signal.
 *
 * Absolute approaches use the usual tier lines (0 / 5 / 10 / 15 %). Relative approaches (vs the index)
 * place the index on the Gold line, the line anchored to its long-run return: a symbol that beats
 * VUSA.AS by more than 5 points is Platinum, one that trails it by more than 10 points is Rust.
 */
final class TierApproachComparison
{
    // Approach keys, in display order; names, descriptions and the decision live in TierApproachCatalog.
    public const OWN_LIFETIME       = 'own_lifetime';
    public const MARKET_SNAPSHOT    = 'market_snapshot';
    public const MARKET_SETTLED     = 'market_settled';
    public const MARKET_2Y_SNAPSHOT = 'market_2y_snapshot';
    public const INDEX_GAP_SNAPSHOT = 'index_gap_snapshot';
    public const INDEX_GAP_SETTLED  = 'index_gap_settled';
    public const OWN_ALPHA          = 'own_alpha';

    public const UNIT_PCT = '%';
    public const UNIT_PTS = 'pts';

    // Joins a sign, a number and its unit so a figure never breaks across lines (cells and tooltips).
    private const NBSP = "\u{00A0}";

    private const INDEX_ANCHOR_PCT = TierCalculationService::GOLD_THRESHOLD_PCT;
    private const BENCHMARK        = TierCalculationService::BENCHMARK_SYMBOL;

    private TierCalculationService $_tiers;
    private SettledTierResolver $_resolver;

    public function __construct()
    {
        $this->_tiers    = new TierCalculationService();
        $this->_resolver = SettledTierResolver::fromConfig();
    }

    /**
     * @param array $healthScore    PortfolioHealthScore::build() result (its four symbol lists)
     * @param array $categorization symbol => CategorizationService entry, each carrying 'approach_cells'
     * @return ?array ['approaches' => key => meta, 'decision', 'rows' => list, 'summary' => key => split], null
     *                when nothing is owned
     */
    public function build(?array $healthScore, array $categorization): ?array
    {
        if (empty($healthScore)) {
            return null;
        }
        $symbolRows = array_merge(
            $healthScore['platinum_gold_symbols'] ?? [],
            $healthScore['silver_symbols'] ?? [],
            $healthScore['bronze_rust_symbols'] ?? [],
            $healthScore['unrated_symbols'] ?? []
        );
        if (empty($symbolRows)) {
            return null;
        }
        usort($symbolRows, fn ($a, $b) => $b['mvalue_eur'] <=> $a['mvalue_eur']);

        $rows = [];
        foreach ($symbolRows as $detail) {
            // A symbol missing from the categorization gets no tier anywhere (flagged in the table).
            $cells  = $categorization[$detail['symbol']]['approach_cells'] ?? $this->_emptyCells();
            $rows[] = [
                'symbol'     => $detail['symbol'],
                'tier'       => $detail['tier'],
                'mvalue_eur' => $detail['mvalue_eur'],
                // Today's tier when it differs from the one in use (outlined next to it, as in Holdings by tier).
                'today_chip' => $detail['today_chip'] ?? null,
                'cells'      => $cells,
                'superstar'  => self::superstar($cells),
            ];
        }

        return [
            'approaches' => self::approaches(),
            'decision'   => TierApproachCatalog::DECISION,
            'rows'       => $rows,
            'summary'    => $this->summarize($rows),
        ];
    }

    /**
     * Every approach's cell for each symbol of the categorization, the index's 1Y return read from the
     * benchmark's own entry. Called once by CategorizationService, so the watchlist rows and the health card
     * read the same figures.
     *
     * @param array $categorization symbol => CategorizationService entry (without 'approach_cells')
     * @param array $window         symbol => date => stored row: the lookback window of $day
     * @return array<string, array<string, array>> symbol => approach => cell
     */
    public function cellsForAll(array $categorization, array $window, Carbon $day): array
    {
        $index1y = $categorization[self::BENCHMARK]['candidates']['market_1y_pct'] ?? null;
        $result  = [];
        foreach ($categorization as $symbol => $cat) {
            $result[$symbol] = $this->cells((string) $symbol, $cat, $window, $index1y, $day->format('Y-m-d'));
        }

        return $result;
    }

    /**
     * Per approach: share of the market value in each tier group, the share without a tier, and the
     * health signal that split would give. Every approach uses the same total, so the splits compare.
     */
    public function summarize(array $rows): array
    {
        $total   = array_sum(array_column($rows, 'mvalue_eur'));
        $summary = [];
        foreach (array_keys(self::approaches()) as $key) {
            $groups = ['platinum_gold' => 0.0, 'silver' => 0.0, 'bronze_rust' => 0.0, 'none' => 0.0];
            foreach ($rows as $row) {
                $groups[self::_group($row['cells'][$key]['tier'] ?? null)] += (float) $row['mvalue_eur'];
            }
            $pct = array_map(fn ($v) => $total > 0.0 ? round($v / $total * 100.0, 1) : 0.0, $groups);
            [$signal, $message] = PortfolioHealthScore::resolveSignal($pct['platinum_gold'], $pct['bronze_rust']);
            $summary[$key] = $pct + ['signal' => $signal, 'signal_message' => $message];
        }

        return $summary;
    }

    /**
     * A superstar: Platinum or Gold under most of the approaches (more than half), so the symbol is strong
     * whichever way its return is measured. Null otherwise; else the count and the tooltip text.
     *
     * @param array $cells approach => cell (each with 'tier')
     * @return array{count: int, total: int, title: string}|null
     */
    public static function superstar(array $cells): ?array
    {
        $total  = count(self::approaches());
        $strong = 0;
        foreach (array_keys(self::approaches()) as $key) {
            $strong += self::_group($cells[$key]['tier'] ?? null) === 'platinum_gold' ? 1 : 0;
        }
        if ($strong * 2 <= $total) {
            return null;
        }

        return [
            'count' => $strong,
            'total' => $total,
            'title' => 'Superstar: Platinum or Gold under ' . $strong . ' of the ' . $total . ' tier approaches,'
                . ' so it is strong whichever way its return is measured.',
        ];
    }

    private static function _group(?string $tier): string
    {
        return match ($tier) {
            TierCalculationService::PLATINUM, TierCalculationService::GOLD => 'platinum_gold',
            TierCalculationService::SILVER                                => 'silver',
            TierCalculationService::BRONZE, TierCalculationService::RUST   => 'bronze_rust',
            default                                                        => 'none',
        };
    }

    /**
     * Each approach falls back the way the rule in use does, so its split compares with the card: no
     * usable market figure, your own return (or your alpha for a gap to the index); too short a history,
     * today's reading. A stand-in is marked (flag) and named in the tooltip.
     */
    public function cells(string $symbol, array $cat, array $window, ?float $index1y, string $day): array
    {
        $market1y = $cat['candidates']['market_1y_pct'] ?? null;
        $ownRule  = $cat['own_rule'] ?? [];
        $standIn  = ' Stand-in, as in the rule in use:';

        $own   = $this->_cell(
            $ownRule['value'] ?? null, $ownRule['tier'] ?? null, self::UNIT_PCT,
            'Decided on: ' . ($ownRule['basis_label'] ?? 'n/a') . '.',
            ($ownRule['value'] ?? null) !== null
                && ($ownRule['basis'] ?? null) !== TierDecision::BASIS_ANNUALIZED_RETURN
        );
        $alpha = $this->_relative($symbol, $cat['alpha_vs_vusa_pct'] ?? null, 0.0,
            (bool) ($cat['alpha_is_short_period'] ?? false));
        $m1y   = $this->_fallback($this->_absolute($market1y, '1Y market return'), $own,
            $standIn . ' your own return.');
        $vs1y  = $this->_fallback($this->_relative($symbol, $market1y, $index1y, false), $alpha,
            $standIn . ' your alpha.');
        $relativeSeries = $this->_relativeSeries($window[$symbol] ?? [], $window[self::BENCHMARK] ?? []);

        return [
            self::OWN_LIFETIME      => $own,
            self::MARKET_SNAPSHOT         => $m1y,
            self::MARKET_SETTLED => $this->_fallback(
                $this->_settled($symbol, $this->_marketSeries($window[$symbol] ?? []), $day, 0.0, $m1y),
                $m1y, $standIn . ' today\'s reading.'
            ),
            self::MARKET_2Y_SNAPSHOT         => $this->_fallback(
                $this->_absolute($cat['momenta']['2y'] ?? null, '2Y market return (CAGR)'), $own,
                $standIn . ' your own return.'
            ),
            self::INDEX_GAP_SNAPSHOT       => $vs1y,
            self::INDEX_GAP_SETTLED  => $this->_fallback(
                $this->_settled($symbol, $relativeSeries, $day, self::INDEX_ANCHOR_PCT, $vs1y),
                $vs1y, $standIn . ' today\'s gap.'
            ),
            self::OWN_ALPHA             => $alpha,
        ];
    }

    /**
     * $cell when it has a tier; otherwise $fallback, marked, with both reasons in the tooltip.
     */
    private function _fallback(array $cell, array $fallback, string $standIn): array
    {
        if ($cell['tier'] !== null || $fallback['tier'] === null) {
            return $cell;
        }

        return ['flag' => true, 'title' => $cell['title'] . $standIn . ' ' . $fallback['title']] + $fallback;
    }

    private function _emptyCells(): array
    {
        return array_map(fn () => $this->_cell(null, null, self::UNIT_PCT, 'Not in the categorization.'),
            self::approaches());
    }

    private function _cell(?float $value, ?string $tier, string $unit, string $title, bool $flag = false): array
    {
        return [
            'value' => $value !== null ? round($value, 2) : null,
            'tier'  => $tier,
            'unit'  => $unit,
            'title' => $title,
            'flag'  => $flag,
        ];
    }

    private function _absolute(?float $value, string $label): array
    {
        if ($value === null) {
            return $this->_cell(null, null, self::UNIT_PCT, 'No ' . $label . ' available.');
        }
        if (!TierClassifier::marketUsable($value)) {
            return $this->_cell($value, null, self::UNIT_PCT,
                'Below the trusted floor, so treated as a data artifact (usually a missed split): no tier.', true);
        }
        if (TierClassifier::marketUnusual($value)) {
            return $this->_cell($value, $this->_tiers->getTier($value), self::UNIT_PCT,
                $label . '; an unusual move, check it is a real rally and not a data error.');
        }

        return $this->_cell($value, $this->_tiers->getTier($value), self::UNIT_PCT, $label . '.');
    }

    /**
     * $value minus $reference in points, tiered with the reference on the Gold line. The benchmark is
     * pinned to Gold (it cannot beat itself).
     */
    private function _relative(string $symbol, ?float $value, ?float $reference, bool $short): array
    {
        if ($value === null || $reference === null || !TierClassifier::marketUsable($value)) {
            return $this->_cell(null, null, self::UNIT_PTS, 'Not available for this symbol.');
        }
        $gap  = $value - $reference;
        $tier = $symbol === self::BENCHMARK
            ? TierCalculationService::GOLD
            : $this->_tiers->getTier($gap + self::INDEX_ANCHOR_PCT);
        $title = 'Gap to VUSA.AS in points' . ($short ? '; held under a year, so a raw difference.' : '.');

        return $this->_cell($gap, $tier, self::UNIT_PTS, $title, $short);
    }

    /**
     * The settled value of a daily series (the reading at the consistency percentile), shown minus
     * $offset (the Gold anchor for a relative series), with the rule in use's deterioration: two tiers
     * below it for long enough and the tier follows today. Empty with too short a history.
     *
     * @param array $today The approach's today cell; its tier drives the deterioration (not a stand-in).
     */
    private function _settled(string $symbol, array $series, string $day, float $offset, array $today): array
    {
        $unit      = $today['unit'];
        $todayTier = $today['flag'] ? null : $today['tier'];
        // On the series' scale (a relative series is stored with the Gold anchor added).
        $todayVal  = $today['flag'] || $today['value'] === null ? null : $today['value'] + $offset;
        $settled   = $this->_resolver->resolve($symbol, $todayTier, $series, false, $day, null, $todayVal);
        if ($settled->settledTier === null) {
            return $this->_cell(null, null, $unit, $settled->explanation);
        }
        if ($settled->status === SettledTierResolver::STATUS_DETERIORATING) {
            return $this->_cell($today['value'], $settled->settledTier, $unit,
                $settled->explanation . ' Today\'s reading shown.', true);
        }
        if ($settled->settledValue === null) {
            return $this->_cell(null, $settled->settledTier, $unit, $settled->explanation);
        }

        $value = $settled->settledValue - $offset;
        $title = $this->_settledTitle($settled, $value, $today);
        if ($symbol === self::BENCHMARK) {
            // Pinned to Gold whatever the reading: the Gold line is anchored to it.
            $title = $settled->explanation . ' ' . $title;
        }

        return $this->_cell($value, $settled->settledTier, $unit, $title);
    }

    /**
     * The median next to today's reading, and the time in each tier behind it.
     */
    private function _settledTitle(SettledTier $settled, float $value, array $today): string
    {
        $shares = [];
        foreach ($settled->shares as $tier => $pct) {
            if ($pct > 0) {
                $shares[] = TierCalculationService::tierInitial($tier) . self::NBSP
                    . MoneyFormat::get_formatted_number_plain($pct, 0) . '%';
            }
        }

        return 'Median of ' . $settled->days . ' daily readings (' . $settled->firstDate . ' to '
            . $settled->lastDate . '): ' . self::formatValue($value, $today['unit']) . '. Today\'s reading: '
            . self::formatValue($today['value'], $today['unit']) . '. Time in tier: ' . implode(' · ', $shares)
            . '.';
    }

    /**
     * Compact signed value for the watchlist's "Today" line: one decimal (none from 100), the unit joined by
     * non-breaking spaces ("+ 21.8 %", "- 3.1 pts"); "n/a" without one.
     */
    public static function formatShort(?float $value, string $unit): string
    {
        if ($value === null) {
            return 'n/a';
        }
        $sign = $value > 0 ? '+' . self::NBSP : ($value < 0 ? '-' . self::NBSP : '');

        return $sign . MoneyFormat::get_formatted_number_plain(abs($value), abs($value) >= 100 ? 0 : 1)
            . self::NBSP . $unit;
    }

    /**
     * Signed value with its unit ("+ 21.75 %", "- 3.10 pts", joined by non-breaking spaces), "n/a" without
     * one.
     */
    public static function formatValue(?float $value, string $unit): string
    {
        if ($value === null) {
            return 'n/a';
        }
        $sign = $value > 0 ? '+' . self::NBSP : ($value < 0 ? '-' . self::NBSP : '');

        return $sign . MoneyFormat::get_formatted_pct(abs($value)) . self::NBSP . $unit;
    }

    /**
     * @return array<string, float> date => stored 1Y market return
     */
    private function _marketSeries(array $rows): array
    {
        $series = [];
        foreach ($rows as $date => $row) {
            if (($row['m'] ?? null) !== null) {
                $series[$date] = (float) $row['m'];
            }
        }

        return $series;
    }

    /**
     * @return array<string, float> date => 1Y market return minus the index's, on the absolute scale
     *         (index on the Gold line), for the days both are stored
     */
    private function _relativeSeries(array $rows, array $indexRows): array
    {
        $series = [];
        foreach ($this->_marketSeries($rows) as $date => $value) {
            $index = $indexRows[$date]['m'] ?? null;
            if ($index !== null) {
                $series[$date] = $value - (float) $index + self::INDEX_ANCHOR_PCT;
            }
        }

        return $series;
    }

    /**
     * Each approach's name, description, tradeoffs and allocation verdict, in display order.
     */
    public static function approaches(): array
    {
        return TierApproachCatalog::approaches();
    }
}
