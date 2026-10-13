<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;

/**
 * Signal backtest (phase 4): replays the tier signals (SettledTierResolver: rare high, rare low, deteriorating) on
 * the stored daily tier history and measures what the price did next. A single-path sanity check, not proof of an
 * edge.
 *
 * - No look-ahead: each weekday D is resolved against the rows dated [D - lookback, D - 1] only, today's tier being
 *   the tier of D's own stored reading (re-derived from its value with the current lines, as the settled tier does).
 * - Episodes: consecutive days with the same signal and action; a signal is judged from its first day.
 * - Forward return: the symbol's close N trading days after the episode's first day (its own price dates) against
 *   VUSA.AS over the same calendar span, in percentage points. A sell (rare high) or a review (deteriorating)
 *   "worked" when the symbol then trailed VUSA.AS; a buy (rare low) when it beat it.
 * - Baseline: the same forward comparison for every evaluated symbol-day, signal or not, so a hit rate can be read
 *   against how often any symbol trailed or beat the index anyway.
 * - With a PositionReadingAsOf, the range-bound (TRIM / ADD / BUY) and TAKE PROFIT readings of each day are replayed
 *   too, as their own episodes (a symbol can carry one of them and a tier signal at once): TRIM and TAKE PROFIT
 *   worked when the symbol then trailed, ADD and BUY when it beat the index.
 *
 * Pure: no database, no config; the history, the prices and the resolver are passed in.
 */
final class TierSignalBacktest
{
    public const HORIZONS = [21, 63, 126];

    public const GROUP_ALL   = 'all';
    public const GROUP_HELD  = 'held';
    public const GROUP_WATCH = 'watch';

    /** Signals judged as a buy (worked when the symbol then beat the index); every other one as a sell. */
    public const BUY_SIGNALS = [
        SettledTierResolver::SIGNAL_RARE_LOW,
        PeakProximityAlertEvent::SIGNAL_RANGE_LOW,
        PeakProximityAlertEvent::SIGNAL_RANGE_BUY,
    ];

    /** Each signal as the report names it, in the report's order: the tier signals, then the position readings. */
    public const SIGNAL_NAMES = [
        SettledTierResolver::SIGNAL_RARE_HIGH       => 'Rare high · SELL / TRIM',
        SettledTierResolver::SIGNAL_RARE_LOW        => 'Rare low · ADD / BUY',
        SettledTierResolver::SIGNAL_DETERIORATING   => 'Deteriorating · REVIEW',
        PeakProximityAlertEvent::SIGNAL_RANGE_HIGH  => 'Range-bound · TRIM',
        PeakProximityAlertEvent::SIGNAL_RANGE_LOW   => 'Range-bound · ADD',
        PeakProximityAlertEvent::SIGNAL_RANGE_BUY   => 'Range-bound · BUY',
        PeakProximityAlertEvent::SIGNAL_TAKE_PROFIT => 'Weak tier in profit · TAKE PROFIT',
    ];

    private const GROUP_ORDER = [self::GROUP_ALL, self::GROUP_HELD, self::GROUP_WATCH];

    private TierCalculationService $_tiers;

    public function __construct(
        private readonly SettledTierResolver $_resolver,
        private readonly array $_horizons = self::HORIZONS,
        private readonly int $_lookbackDays = 365,
        private readonly ?PositionReadingAsOf $_positions = null
    )
    {
        $this->_tiers = new TierCalculationService();
    }

    /**
     * @param array       $history   symbol => date => stored row (TierHistoryRepository: v, m, w, b, o), ascending
     * @param array       $prices    symbol => date => close, ascending
     * @param array       $benchmark date => VUSA.AS close, ascending
     * @param string|null $from      first day evaluated (earlier rows still serve as lookback)
     *
     * @return array{episodes: list<array>, summary: array, baseline: array, days: int}
     */
    public function run(array $history, array $prices, array $benchmark, ?string $from = null): array
    {
        $benchDates = array_keys($benchmark);
        $episodes   = [];
        $baseline   = [];
        $days       = 0;
        foreach ($history as $symbol => $rows) {
            if ($symbol === TierCalculationService::BENCHMARK_SYMBOL || empty($prices[$symbol])) {
                continue;
            }
            $forward = $this->_forwardFn($prices[$symbol], $benchmark, $benchDates);
            [$signals, $evaluated, $readings] = $this->_dailySignals((string) $symbol, $rows, $prices[$symbol], $from);
            $days += count($evaluated);
            foreach ($evaluated as $day) {
                foreach ($this->_horizons as $h) {
                    $rel = $forward($day, $h);
                    if ($rel !== null) {
                        $baseline[$h][] = $rel;
                    }
                }
            }
            $symbolEpisodes = array_merge(
                $this->_episodes((string) $symbol, $signals),
                $this->_episodes((string) $symbol, $readings)
            );
            foreach ($symbolEpisodes as $episode) {
                foreach ($this->_horizons as $h) {
                    $episode['forward'][$h] = $forward($episode['start'], $h);
                }
                $episodes[] = $episode;
            }
        }

        return [
            'episodes' => $episodes,
            'summary'  => $this->_summary($episodes),
            'baseline' => $this->_baseline($baseline),
            'days'     => $days,
        ];
    }

    /**
     * An episode in words: a tier signal with its own action ("Rare high · SELL", "Rare high" without one), a
     * position reading by its name.
     */
    public static function episodeLabel(array $episode): string
    {
        $tier = TierSignalChip::LABELS[$episode['signal']] ?? null;
        if ($tier !== null) {
            return $tier . (!empty($episode['action']) ? ' · ' . $episode['action'] : '');
        }

        return self::SIGNAL_NAMES[$episode['signal']] ?? (string) $episode['signal'];
    }

    /**
     * Whether a signal's forward relative return counts as a hit: a sell or a review when the symbol trailed the
     * index, a buy when it beat it.
     */
    public static function isHit(string $signal, float $relative): bool
    {
        return in_array($signal, self::BUY_SIGNALS, true) ? $relative > 0 : $relative < 0;
    }

    /**
     * Each evaluated day's tier signal and position reading (null when none), resolved against the rows before it.
     *
     * @param array $closes the symbol's closes, date => close ascending (the 52-week range of the position readings)
     *
     * @return array{0: array<string, ?array{signal: string, action: ?string, owned: bool}>, 1: string[],
     *     2: array<string, ?array{signal: string, action: string, owned: bool}>}
     */
    private function _dailySignals(string $symbol, array $rows, array $closes, ?string $from): array
    {
        $dates     = array_keys($rows);
        $start     = 0;
        $signals   = [];
        $readings  = [];
        $evaluated = [];
        $traded    = false;
        $range     = $this->_rangeWindow($closes);
        foreach ($dates as $i => $day) {
            $windowFrom = date('Y-m-d', strtotime($day . ' -' . $this->_lookbackDays . ' days'));
            while ($start < $i && $dates[$start] < $windowFrom) {
                $start++;
            }
            $row     = $rows[$day];
            $traded  = $traded || !empty($row['o']) || ($row['w'] ?? null) !== null;
            if (($from !== null && $day < $from) || $row['v'] === null) {
                continue;
            }
            $lookback = array_slice($rows, $start, $i - $start, true);
            $settled  = $this->_resolver->resolve(
                $symbol, $this->_tiers->getTier((float) $row['v']), $lookback, (bool) $row['o'], $day,
                CategorizationTierHistory::measureOf((string) $row['b']), (float) $row['v']
            );
            if ($settled->status === SettledTierResolver::STATUS_INSUFFICIENT) {
                continue;
            }
            $evaluated[]   = $day;
            $signals[$day] = $settled->signal === null ? null : [
                'signal' => $settled->signal,
                'action' => $settled->action,
                'owned'  => (bool) $row['o'],
            ];
            if ($this->_positions !== null) {
                [$window, $price] = $range($day);
                $readings[$day]   = $window === null ? null : $this->_positions->read(
                    $row, $settled->settledTier, $settled->settledTier, $window, $price, $traded
                );
            }
        }

        return [$signals, $evaluated, $readings];
    }

    /**
     * fn (day) => [the closes of the 52 weeks up to and including day, day's close], or [null, null] when the
     * closes do not reach a year back (a partial range would misread the swing). Days must come in ascending order.
     */
    private function _rangeWindow(array $closes): callable
    {
        $dates = array_keys($closes);
        $first = $dates[0] ?? null;
        $lo    = 0;
        $hi    = -1;

        return function (string $day) use ($closes, $dates, $first, &$lo, &$hi): array {
            $from = date('Y-m-d', strtotime($day . ' -365 days'));
            if ($first === null || $first > date('Y-m-d', strtotime($from . ' +7 days'))) {
                return [null, null];
            }
            while (isset($dates[$hi + 1]) && $dates[$hi + 1] <= $day) {
                $hi++;
            }
            while ($lo < $hi && $dates[$lo] <= $from) {
                $lo++;
            }
            if ($hi < $lo) {
                return [null, null];
            }
            $window = array_slice($closes, $lo, $hi - $lo + 1);

            return [$window, $closes[$dates[$hi]]];
        };
    }

    /**
     * Consecutive evaluated days with the same signal and action, as episodes (first day, last day, length).
     *
     * @return list<array{symbol: string, signal: string, action: ?string, owned: bool, start: string, end: string,
     *     days: int, forward: array}>
     */
    private function _episodes(string $symbol, array $signals): array
    {
        $episodes = [];
        $current  = null;
        foreach ($signals as $day => $signal) {
            $same = $current !== null && $signal !== null
                && $signal['signal'] === $current['signal'] && $signal['action'] === $current['action'];
            if ($same) {
                $current['end'] = $day;
                $current['days']++;
                continue;
            }
            if ($current !== null) {
                $episodes[] = $current;
            }
            $current = $signal === null ? null : $signal + [
                'symbol' => $symbol, 'start' => $day, 'end' => $day, 'days' => 1, 'forward' => [],
            ];
        }
        if ($current !== null) {
            $episodes[] = $current;
        }

        return $episodes;
    }

    /**
     * fn (day, h) => the symbol's return from its close on or before day to its close h trading days later, less
     * VUSA.AS's over the same dates, in percentage points; null without h later closes or a benchmark price.
     */
    private function _forwardFn(array $prices, array $benchmark, array $benchDates): callable
    {
        $dates = array_keys($prices);
        $index = array_flip($dates);

        return function (string $day, int $h) use ($prices, $dates, $index, $benchmark, $benchDates): ?float {
            $k = $index[$day] ?? $this->_positionOnOrBefore($dates, $day);
            if ($k === null || !isset($dates[$k + $h])) {
                return null;
            }
            $startDay = $dates[$k];
            $endDay   = $dates[$k + $h];
            $b0 = StoredCloses::onOrBefore($benchmark, $benchDates, $startDay);
            $b1 = StoredCloses::onOrBefore($benchmark, $benchDates, $endDay);
            if ($b0 === null || $b1 === null || $b0 <= 0 || $prices[$startDay] <= 0) {
                return null;
            }

            return ($prices[$endDay] / $prices[$startDay] - $b1 / $b0) * 100.0;
        };
    }

    private function _positionOnOrBefore(array $dates, string $day): ?int
    {
        $lo   = 0;
        $hi   = count($dates) - 1;
        $best = null;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($dates[$mid] <= $day) {
                $best = $mid;
                $lo   = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $best;
    }

    /**
     * Per signal, all and split held / watch-only, per horizon: episodes with a forward return, hit rate, average
     * and median relative return; plus the episode count and median length.
     */
    private function _summary(array $episodes): array
    {
        $summary = [];
        foreach ($episodes as $episode) {
            $groups = [self::GROUP_ALL, $episode['owned'] ? self::GROUP_HELD : self::GROUP_WATCH];
            foreach ($groups as $group) {
                $key = $episode['signal'] . '|' . $group;
                $summary[$key]['signal'] ??= $episode['signal'];
                $summary[$key]['group']  ??= $group;
                $summary[$key]['lengths'][] = $episode['days'];
                foreach ($this->_horizons as $h) {
                    if ($episode['forward'][$h] !== null) {
                        $summary[$key]['rel'][$h][] = $episode['forward'][$h];
                    }
                }
            }
        }

        foreach ($summary as $key => $row) {
            $stats = [];
            foreach ($this->_horizons as $h) {
                $rel   = $row['rel'][$h] ?? [];
                $hits  = count(array_filter($rel, fn ($r) => self::isHit($row['signal'], $r)));
                $stats[$h] = [
                    'n'          => count($rel),
                    'hit_rate'   => $rel ? $hits / count($rel) * 100.0 : null,
                    'avg_rel'    => $rel ? array_sum($rel) / count($rel) : null,
                    'median_rel' => $rel ? self::median($rel) : null,
                ];
            }
            $summary[$key] = [
                'signal'      => $row['signal'],
                'group'       => $row['group'],
                'episodes'    => count($row['lengths']),
                'median_days' => self::median($row['lengths']),
                'horizons'    => $stats,
            ];
        }
        $signalOrder = array_flip(array_keys(self::SIGNAL_NAMES));
        $groupOrder  = array_flip(self::GROUP_ORDER);
        uasort($summary, fn ($a, $b) => [$signalOrder[$a['signal']] ?? 99, $groupOrder[$a['group']] ?? 9]
            <=> [$signalOrder[$b['signal']] ?? 99, $groupOrder[$b['group']] ?? 9]);

        return $summary;
    }

    private function _baseline(array $baseline): array
    {
        $result = [];
        foreach ($this->_horizons as $h) {
            $rel = $baseline[$h] ?? [];
            $result[$h] = [
                'n'          => count($rel),
                'under_rate' => $rel ? count(array_filter($rel, fn ($r) => $r < 0)) / count($rel) * 100.0 : null,
                'avg_rel'    => $rel ? array_sum($rel) / count($rel) : null,
                'median_rel' => $rel ? self::median($rel) : null,
            ];
        }

        return $result;
    }

    public static function median(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }
        sort($values);
        $n   = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2.0;
    }
}
