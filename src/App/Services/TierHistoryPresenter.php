<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * Display decisions for the daily tier history, so the blades and the chart script only echo:
 *
 * - forRow(): the "Tier History 12M" line on /watchlist-symbols: the per-day ribbon (one segment per run of
 *   consecutive days in one tier), its tooltip with the time in each tier, the split-year note and the median.
 * - forChart(): the tier history modal (daily basis values and tiers, the tier lines, runs and
 *   per-tier totals for the whole stored history and for the last 12 months).
 */
final class TierHistoryPresenter
{
    private TierCalculationService $_tiers;

    /**
     * @param float $_splitSharePct The "split year" note shows when the settled tier held on fewer than this %
     *                              of the days (myfinance2.watchlistsymbols.tier_history.split_share_pct).
     */
    public function __construct(private readonly float $_splitSharePct = 15.0)
    {
        $this->_tiers = new TierCalculationService();
    }

    /**
     * @param array $lookback date => ['v' => value, 'b' => basis, 'o' => owned] (TierHistoryRepository)
     */
    public function forRow(string $symbol, array $lookback, SettledTier $settled, Carbon $day): ?array
    {
        if ($settled->days === 0) {
            return null;
        }

        $segments = [];
        $parts    = [];
        foreach ($settled->shares as $tier => $pct) {
            if ($pct <= 0.0) {
                continue;
            }
            $segments[] = [
                'tier'  => $tier,
                'pct'   => round($pct, 2),
                'color' => TierCalculationService::tierColor($tier),
                'title' => TierCalculationService::tierLabel($tier) . ': ' . $this->_pct($pct) . ' of days',
            ];
            $parts[] = TierCalculationService::tierInitial($tier) . ' ' . $this->_pct($pct);
        }

        $runs = $this->runs($this->_tierByDate($symbol, $settled->series));

        $period = $this->_periodLabel($settled->firstDate, $day);

        return [
            'days'        => $settled->days,
            'from'        => $settled->firstDate,
            'to'          => $settled->lastDate,
            'period'      => $period,
            // The median reading behind the settled tier; null when today's tier is used instead (too short
            // a history, or a lasting drop).
            'settled_value' => $settled->status === SettledTierResolver::STATUS_OK ? $settled->settledValue : null,
            'split'       => $this->_splitYear($symbol, $settled),
            // Shown under each ribbon segment's own line in the ribbon tooltip.
            'ribbon_note' => ($period !== '' ? 'History ' . $period . ': ' : 'Last 12 months: ')
                . implode(' · ', $parts) . '. Click for the chart.',
            'measure'     => $settled->measure === SettledTierResolver::MEASURE_OWN ? 'your return'
                : ($settled->measure === SettledTierResolver::MEASURE_MARKET ? 'market 1Y' : ''),
            'shares_text' => implode(' · ', $parts),
            'segments'    => $segments,
            'tooltip'     => $this->_rowTooltip($settled),
            'ribbon'      => $this->_ribbon($runs, $settled->days),
        ];
    }

    /**
     * @param array      $history  date => ['v' => value, 'm', 'w', 'b' => basis, 'o' => owned], the whole
     *                             stored history
     * @param array      $quotes   ['prices' => date => close (native, ascending, reaching a year before the
     *                             history), 'currency' => display symbol of those closes]
     * @param array      $holdings ['trades' => your trades, oldest first ('date', 'action', 'quantity',
     *                             'price', 'iso', 'code'), 'windows' => your holding periods, 'lifetime_pct']
     *                             (hover lines, B / S markers and the "Your holdings" chart)
     */
    public function forChart(
        string $symbol,
        array $history,
        array $quotes,
        Carbon $day,
        int $lookbackDays,
        array $holdings = []
    ): array
    {
        $trades      = $holdings['trades'] ?? [];
        $isBenchmark = $symbol === TierCalculationService::BENCHMARK_SYMBOL;
        $prices      = $quotes['prices'] ?? [];
        $currency    = (string) ($quotes['currency'] ?? '');
        $points      = [];
        foreach ($history as $date => $row) {
            if ($row['v'] === null) {
                continue;
            }
            $tier     = $isBenchmark ? TierCalculationService::GOLD : $this->_tiers->getTier($row['v']);
            $points[] = [
                'time'  => $date,
                'value' => round($row['v'], 2),
                'tier'  => $tier,
                'label' => TierCalculationService::tierLabel($tier),
                'color' => TierCalculationService::tierColor($tier),
                'basis' => $this->_basisLabel($row['b']),
                'owned' => $row['o'],
                // The two closes the day's 1Y market return is measured between, for the hover label.
                'window' => $this->_windowLabels($prices, $date, $currency),
                // Both candidate numbers of the day (null when unavailable), drawn as thin lines.
                'own'    => $this->_round2($row['w'] ?? null),
                'market' => $this->_round2($row['m'] ?? null),
            ];
        }

        $this->_attachTrades($points, $trades);
        $tierByDate   = array_column($points, 'tier', 'time');
        $lookbackFrom = $day->copy()->subDays($lookbackDays)->format('Y-m-d');
        $recent       = array_filter($tierByDate, fn ($d) => $d >= $lookbackFrom, ARRAY_FILTER_USE_KEY);

        return [
            'symbol'        => $symbol,
            'points'        => $points,
            'lookback_from' => $lookbackFrom,
            'tiers'         => $this->_legend(),
            'lines'         => [0.0, 5.0, TierCalculationService::GOLD_THRESHOLD_PCT, 15.0],
            'totals_all'    => $this->_totals($tierByDate),
            'totals_recent' => $this->_totals($recent),
            'position'      => (new TierPositionSeries())->build($history, $holdings),
        ];
    }

    /**
     * Consecutive stored days in the same tier, oldest first.
     *
     * @param array<string, ?string> $tierByDate date (ascending) => tier
     *
     * @return array<int, array{tier: ?string, from: string, to: string, days: int}>
     */
    public function runs(array $tierByDate): array
    {
        $runs = [];
        foreach ($tierByDate as $date => $tier) {
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last]['tier'] === $tier) {
                $runs[$last]['to'] = $date;
                $runs[$last]['days']++;
                continue;
            }
            $runs[] = ['tier' => $tier, 'from' => $date, 'to' => $date, 'days' => 1];
        }

        return $runs;
    }

    /**
     * @param array<string, float> $series date => value of the measure the shares were computed on
     */
    private function _tierByDate(string $symbol, array $series): array
    {
        $isBenchmark = $symbol === TierCalculationService::BENCHMARK_SYMBOL;

        return array_map(
            fn ($v) => $isBenchmark ? TierCalculationService::GOLD : $this->_tiers->getTier($v),
            $series
        );
    }

    private function _ribbon(array $runs, int $days): array
    {
        $segments = [];
        foreach ($this->_labelRuns($runs) as $run) {
            $segments[] = $run + ['width_pct' => round($run['days'] / max(1, $days) * 100.0, 3)];
        }

        return $segments;
    }

    private function _labelRuns(array $runs): array
    {
        return array_map(fn ($run) => $run + [
            'label' => TierCalculationService::tierLabel($run['tier']),
            'color' => TierCalculationService::tierColor($run['tier']),
            'title' => TierCalculationService::tierLabel($run['tier']) . ': ' . $this->_date($run['from'])
                . ($run['from'] !== $run['to'] ? ' to ' . $this->_date($run['to']) : '')
                . ' (' . $run['days'] . ($run['days'] === 1 ? ' trading day)' : ' trading days)'),
            'from_label' => $this->_date($run['from']),
            'to_label'   => $this->_date($run['to']),
        ], $runs);
    }

    /**
     * @return array<int, array{tier: string, label: string, color: string, days: int, pct: string}>
     */
    private function _totals(array $tierByDate): array
    {
        $n      = count($tierByDate);
        $counts = array_count_values(array_map(fn ($t) => (string) $t, $tierByDate));
        $totals = [];
        foreach (TierCalculationService::TIERS as $tier) {
            $days = $counts[$tier] ?? 0;
            if ($days === 0) {
                continue;
            }
            $totals[] = [
                'tier'  => $tier,
                'label' => TierCalculationService::tierLabel($tier),
                'color' => TierCalculationService::tierColor($tier),
                'days'  => $days,
                'pct'   => $this->_pct($days / $n * 100.0),
            ];
        }

        return $totals;
    }

    private function _legend(): array
    {
        return array_map(fn ($tier) => [
            'tier'  => $tier,
            'label' => TierCalculationService::tierLabel($tier),
            'color' => TierCalculationService::tierColor($tier),
        ], TierCalculationService::TIERS);
    }

    /**
     * A split year: the settled tier is the median of a year spent mostly above and below it, so the symbol
     * rarely held that tier itself (under split_share_pct of the days). Null otherwise.
     *
     * @return ?array{label: string, title: string}
     */
    private function _splitYear(string $symbol, SettledTier $settled): ?array
    {
        $tier = $settled->settledTier;
        if ($tier === null || $settled->status !== SettledTierResolver::STATUS_OK
            || $symbol === TierCalculationService::BENCHMARK_SYMBOL
            || ($settled->shares[$tier] ?? 0.0) >= $this->_splitSharePct
        ) {
            return null;
        }
        $rank  = TierCalculationService::tierRank($tier);
        $above = $this->_largestShare($settled->shares, fn ($r) => $r > $rank);
        $below = $this->_largestShare($settled->shares, fn ($r) => $r < $rank);
        if ($above === null || $below === null) {
            return null;
        }
        $label = TierCalculationService::tierLabel($tier);

        return [
            'label' => 'split year',
            'title' => $label . ' is the middle of a split year: ' . TierCalculationService::tierLabel($above)
                . ' on ' . $this->_pct($settled->shares[$above]) . ' of the days, '
                . TierCalculationService::tierLabel($below) . ' on ' . $this->_pct($settled->shares[$below])
                . '; ' . $label . ' itself on only ' . $this->_pct($settled->shares[$tier]) . '.',
        ];
    }

    private function _largestShare(array $shares, callable $rankFilter): ?string
    {
        $best = null;
        foreach ($shares as $tier => $pct) {
            if ($pct > 0.0 && $rankFilter(TierCalculationService::tierRank($tier))
                && ($best === null || $pct > $shares[$best])
            ) {
                $best = $tier;
            }
        }

        return $best;
    }

    private function _rowTooltip(SettledTier $settled): string
    {
        $text = 'Based on ' . $settled->days . ' trading days from ' . $this->_date($settled->firstDate)
            . ' to ' . $this->_date($settled->lastDate) . ', ' . $this->_measureLabel($settled) . '.';
        if ($settled->ownedSince !== null) {
            $text .= ' Owned since ' . $this->_date($settled->ownedSince) . '.';
        }

        return $text;
    }

    /**
     * Which number the shares measure: the same one that decides today's tier.
     */
    private function _measureLabel(SettledTier $settled): string
    {
        return match (true) {
            $settled->measureFallback => 'each day measured on the 1Y market return (your own return is too'
                . ' recent to compare yet)',
            $settled->measure === SettledTierResolver::MEASURE_OWN => 'each day measured on your own overall'
                . ' return, the same number that decides today\'s tier',
            $settled->measure === SettledTierResolver::MEASURE_MARKET => 'each day measured on the 1Y market'
                . ' return, the same number that decides today\'s tier',
            default => 'each day measured on the number that decided its tier that day',
        };
    }

    /**
     * Empty for a full 12 months of history; "(since 2025-01-01, 8M)" when it is shorter.
     */
    private function _periodLabel(?string $first, Carbon $day): string
    {
        if ($first === null) {
            return '';
        }
        $days = (int) Carbon::parse($first)->diffInDays($day);
        if ($days >= 330) {
            return '';
        }

        return '(since ' . $first . ', ' . max(1, (int) round($days / 30.44)) . 'M)';
    }

    private function _basisLabel(string $basis): string
    {
        return match ($basis) {
            TierDecision::BASIS_ANNUALIZED_RETURN => 'overall return (annualized)',
            TierDecision::BASIS_RAW_RETURN        => 'overall return',
            TierDecision::BASIS_MARKET_MOMENTUM   => '1Y market return',
            default                               => 'unrated',
        };
    }

    /**
     * Adds to each point the trades of that day as ready-to-show lines ("Buy 25 @ 168.04$"). A trade on a
     * day without a point (weekend, holiday) goes to the previous point, where its B / S marker sits too.
     */
    private function _attachTrades(array &$points, array $trades): void
    {
        $dates = array_column($points, 'time');
        foreach ($points as &$point) {
            $point['trades'] = [];
        }
        unset($point);
        foreach ($trades as $trade) {
            $index = $this->_lastIndexOnOrBefore($dates, $trade['date']);
            if ($index === null || !in_array($trade['action'], ['BUY', 'SELL'], true)) {
                continue;
            }
            $points[$index]['trades'][] = [
                'buy'  => $trade['action'] === 'BUY',
                'text' => ucfirst(strtolower($trade['action'])) . ' '
                    . MoneyFormat::get_formatted_quantity_plain($trade['quantity']) . ' @ '
                    . MoneyFormat::get_formatted_price_plain($trade['price']) . $trade['code'],
            ];
        }
    }

    private function _lastIndexOnOrBefore(array $dates, string $date): ?int
    {
        $found = null;
        foreach ($dates as $i => $d) {
            if ($d > $date) {
                break;
            }
            $found = $i;
        }

        return $found;
    }

    /**
     * @return array{ago: string, now: string}|null "1Y ago" and "now" lines, each "date: price currency"
     */
    private function _windowLabels(array $prices, string $date, string $currency): ?array
    {
        $window = DrawdownService::momentumWindowAsOf($prices, 365, $date);
        if ($window === null) {
            return null;
        }
        // Price followed directly by the currency symbol, as elsewhere in the app (e.g. "217.28$").
        $label = fn (string $d, float $p) => $this->_date($d) . ': '
            . MoneyFormat::get_formatted_price_plain($p) . $currency;

        return [
            'ago' => $label($window['start_date'], $window['start_price_eur']),
            'now' => $label($window['end_date'], $window['end_price_eur']),
        ];
    }

    private function _round2(?float $value): ?float
    {
        return $value !== null ? round($value, 2) : null;
    }

    private function _pct(float $pct): string
    {
        if ($pct > 0.0 && $pct < 1.0) {
            return '<1%';
        }

        return MoneyFormat::get_formatted_number_plain($pct, 0) . '%';
    }

    private function _date(?string $date): string
    {
        return $date !== null ? Carbon::parse($date)->format('d M Y') : 'n/a';
    }
}
