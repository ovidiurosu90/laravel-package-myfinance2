<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Pure (no DB) rules that turn a symbol's daily tier history into a settled tier and a tier signal.
 *
 * - Time in tier: the share of lookback days in each tier, re-derived from the stored basis values
 *   with the current thresholds (so a threshold change applies to the whole history).
 * - Settled tier: sort the lookback values in descending order and take the one at 1-based position
 *   ceil(n * consistency / 100). The symbol was at or above that tier on at least consistency % of
 *   days (the median with 50). It reflects how long a level lasted, not how extreme a spike was.
 * - Signals, in priority order (at most one):
 *   DETERIORATING: today is 5 points or more under the lower line of the tier held before the drop (two tiers
 *     below; for Bronze, at or under -5 %), for deterioratePersistDays trading days in a row (today included).
 *     The settled tier then follows today's tier until today recovers. The drop is measured against the settled
 *     tier of the days before it, not against today's median, which the drop itself pulls down (_dropRun()).
 *   RARE_HIGH: today is above the settled tier and the symbol was at or above today's tier on fewer
 *     than rareShare % of days. SELL when settled is Silver or lower, TRIM when settled is Gold.
 *     Owned only; on a watchlist-only symbol it is a chip without an action.
 *   RARE_LOW: today is below the settled tier, at or below today's tier on fewer than rareShare % of
 *     days, and the settled tier is one of buySettledTiers. ADD when owned, BUY otherwise.
 *
 * Like with like: each stored day carries both candidate numbers (the 1Y market return and your own
 * return). The settled tier uses the same measure that decides today's tier. When today's tier is on your
 * own return but fewer than minHistoryDays days of it exist (a recent purchase), the market history is
 * used instead and no signal is raised, since today's number and the history would differ in kind.
 *
 * The benchmark is pinned: settled Gold, never a signal. A manual override is not an input here; it
 * only changes the effective tier, so the signals stay visible.
 *
 * Backtest (phase 4, 2026-10-13): DETERIORATING was followed by trailing the index (weakly with consistency 50, clearly
 * with 60); RARE_HIGH showed no edge and RARE_LOW went the wrong way (momentum, not reversal). Figures and what to
 * change on a revisit: TierSignalChip.
 */
final class SettledTierResolver
{
    public const STATUS_OK            = 'ok';
    public const STATUS_INSUFFICIENT  = 'insufficient_history';
    public const STATUS_DETERIORATING = 'deteriorating';

    public const SIGNAL_DETERIORATING = 'DETERIORATING';
    public const SIGNAL_RARE_HIGH     = 'RARE_HIGH';
    public const SIGNAL_RARE_LOW      = 'RARE_LOW';

    public const ACTION_SELL   = 'SELL';
    public const ACTION_TRIM   = 'TRIM';
    public const ACTION_BUY    = 'BUY';
    public const ACTION_ADD    = 'ADD';
    public const ACTION_REVIEW = 'REVIEW';

    public const MEASURE_OWN    = 'own';
    public const MEASURE_MARKET = 'market';

    // Rust has no floor, so "two tiers below Bronze" is a step one tier width (5 points) under the Bronze line.
    // Readings at or under it rank one below Rust for the drop rule only.
    private const DEEP_RUST_PCT = -5.0;

    private const TIER_COUNT_WORDS = [3 => 'three', 4 => 'four'];

    private TierCalculationService $_tiers;

    public function __construct(
        private readonly float $_consistencyPct = 50.0,
        private readonly int $_minHistoryDays = 60,
        private readonly float $_rareSharePct = 20.0,
        private readonly int $_deterioratePersistDays = 20,
        private readonly array $_buySettledTiers = [TierCalculationService::GOLD, TierCalculationService::PLATINUM]
    )
    {
        $this->_tiers = new TierCalculationService();
    }

    public static function fromConfig(): self
    {
        $cfg = config('watchlistsymbols.tier_history', []);

        return new self(
            (float) ($cfg['consistency_pct'] ?? 50),
            (int) ($cfg['min_history_days'] ?? 60),
            (float) ($cfg['rare_share_pct'] ?? 20),
            (int) ($cfg['deteriorate_persist_days'] ?? 20),
            (array) ($cfg['buy_settled_tiers'] ?? [TierCalculationService::GOLD, TierCalculationService::PLATINUM])
        );
    }

    /**
     * @param ?string $todayTier The tier shown today (the classifier's computed tier).
     * @param array   $lookback  date (Y-m-d) => basis value, or => ['v' => value, 'o' => owned]
     *                           (TierHistoryRepository rows). Unsorted input is fine.
     * @param ?string $day       The day being evaluated; rows on or after it are ignored.
     * @param ?string $measure   MEASURE_OWN / MEASURE_MARKET: the measure deciding today's tier, so the
     *                           history uses the same one ('w' / 'm' row values). Null: each day's
     *                           deciding value ('v').
     * @param ?float  $todayValue Today's reading on that measure; lets a settled Bronze follow a deep drop.
     */
    public function resolve(
        string $symbol,
        ?string $todayTier,
        array $lookback,
        bool $isOwned,
        ?string $day = null,
        ?string $measure = null,
        ?float $todayValue = null
    ): SettledTier
    {
        [$rows, $ownedSince]         = $this->_normalize($lookback, $day);
        [$values, $used, $fallback] = $this->_select($rows, $measure);
        $isBenchmark = $symbol === TierCalculationService::BENCHMARK_SYMBOL;
        $tiers  = array_map(fn ($v) => $this->_tierOf($v, $isBenchmark), $values);
        $n      = count($values);
        $shares = $this->_shares($tiers);
        $dates  = array_keys($values);
        $base   = [
            'days' => $n, 'first' => $dates[0] ?? null, 'last' => $dates[$n - 1] ?? null,
            'shares' => $shares, 'ownedSince' => $ownedSince,
            'measure' => $used, 'fallback' => $fallback, 'series' => $values,
        ];

        if ($isBenchmark) {
            // The tier is pinned (the Gold line is anchored to it); the median is still reported.
            return $this->_make($base, TierCalculationService::GOLD,
                $n >= $this->_minHistoryDays ? $this->_valueAtConsistency($values) : null, self::STATUS_OK,
                'Benchmark: pinned to Gold.');
        }
        if ($n < $this->_minHistoryDays) {
            return $this->_make($base, null, null, self::STATUS_INSUFFICIENT,
                'Tier from today only; history too short (' . $n . ' days).');
        }

        $settledValue = $this->_valueAtConsistency($values);
        $settled      = $this->_tiers->getTier($settledValue);

        return $this->_withSignal($base, $tiers, $settled, $settledValue, $todayTier, $isOwned, $todayValue);
    }

    /**
     * The reading at 1-based position ceil(n * consistency / 100) of the values sorted high to low (the
     * median with 50). $values must not be empty.
     */
    private function _valueAtConsistency(array $values): float
    {
        $sorted = array_values($values);
        rsort($sorted);
        $n        = count($sorted);
        $position = max(1, (int) ceil($n * $this->_consistencyPct / 100.0));

        return $sorted[min($position, $n) - 1];
    }

    private function _withSignal(
        array $base,
        array $tiers,
        string $settled,
        float $settledValue,
        ?string $todayTier,
        bool $isOwned,
        ?float $todayValue = null
    ): SettledTier
    {
        $explanation = $this->_explain($settled, $settledValue, $tiers, $base);
        $todayRank   = TierCalculationService::tierRank($todayTier);
        if ($todayRank === null || $base['fallback']) {
            return $this->_make($base, $settled, $settledValue, self::STATUS_OK, $explanation);
        }

        $settledRank = TierCalculationService::tierRank($settled);
        $above       = $this->_sharePct($tiers, fn ($r) => $r >= $todayRank);
        $below       = $this->_sharePct($tiers, fn ($r) => $r <= $todayRank);
        $base       += ['above' => $above, 'below' => $below];

        [$deterioratingDays, $fromRank] = $this->_dropRun(
            $base['series'], $settledRank, $this->_dropRank($todayRank, $todayValue)
        );
        $base['deteriorating'] = $deterioratingDays;

        if ($deterioratingDays >= $this->_deterioratePersistDays) {
            $dropFrom = $this->_tierOfRank($fromRank);
            return $this->_make($base + ['dropFrom' => $dropFrom], $todayTier, null, self::STATUS_DETERIORATING,
                self::_dropText($dropFrom, $fromRank - $todayRank, $deterioratingDays),
                self::SIGNAL_DETERIORATING, $isOwned ? self::ACTION_REVIEW : null, $isOwned);
        }
        if ($todayRank > $settledRank && $above < $this->_rareSharePct) {
            $action = $settledRank <= TierCalculationService::tierRank(TierCalculationService::SILVER)
                ? self::ACTION_SELL : self::ACTION_TRIM;
            return $this->_make($base, $settled, $settledValue, self::STATUS_OK, $explanation,
                self::SIGNAL_RARE_HIGH, $isOwned ? $action : null, $isOwned);
        }
        if ($todayRank < $settledRank && $below < $this->_rareSharePct
            && in_array($settled, $this->_buySettledTiers, true)
        ) {
            return $this->_make($base, $settled, $settledValue, self::STATUS_OK, $explanation,
                self::SIGNAL_RARE_LOW, $isOwned ? self::ACTION_ADD : self::ACTION_BUY, true);
        }

        return $this->_make($base, $settled, $settledValue, self::STATUS_OK, $explanation);
    }

    /**
     * @return array{0: array<string, array{v: ?float, m: ?float, w: ?float}>, 1: ?string} rows before
     *         $day (ascending) and the first owned date when ownership started inside the window.
     */
    private function _normalize(array $lookback, ?string $day): array
    {
        ksort($lookback);
        $rows       = [];
        $ownedSince = null;
        $prevOwned  = null;
        foreach ($lookback as $date => $row) {
            if ($day !== null && $date >= $day) {
                continue;
            }
            $owned = is_array($row) ? !empty($row['o']) : false;
            if ($owned && $prevOwned === false && $ownedSince === null) {
                $ownedSince = (string) $date;
            }
            $prevOwned = $owned;
            $rows[(string) $date] = is_array($row)
                ? ['v' => $row['v'] ?? null, 'm' => $row['m'] ?? null, 'w' => $row['w'] ?? null]
                : ['v' => $row, 'm' => null, 'w' => null];
        }

        return [$rows, $ownedSince];
    }

    /**
     * @return array{0: array<string, float>, 1: ?string, 2: bool} date => value of the chosen measure,
     *         the measure actually used, and whether the own measure fell back to the market history.
     */
    private function _select(array $rows, ?string $measure): array
    {
        if ($measure === self::MEASURE_OWN) {
            $own = $this->_column($rows, 'w');
            if (count($own) >= $this->_minHistoryDays) {
                return [$own, self::MEASURE_OWN, false];
            }
            return [$this->_column($rows, 'm'), self::MEASURE_MARKET, true];
        }
        if ($measure === self::MEASURE_MARKET) {
            return [$this->_column($rows, 'm'), self::MEASURE_MARKET, false];
        }

        return [$this->_column($rows, 'v'), null, false];
    }

    private function _column(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $date => $row) {
            if ($row[$key] !== null) {
                $values[$date] = (float) $row[$key];
            }
        }

        return $values;
    }

    private function _tierOf(float $value, bool $isBenchmark): int
    {
        return TierCalculationService::tierRank(
            $isBenchmark ? TierCalculationService::GOLD : $this->_tiers->getTier($value)
        );
    }

    /**
     * @param int[] $ranks date => tier rank
     *
     * @return array<string, float> tier => % of days, Platinum first
     */
    private function _shares(array $ranks): array
    {
        $shares = [];
        foreach (TierCalculationService::TIERS as $tier) {
            $rank          = TierCalculationService::tierRank($tier);
            $shares[$tier] = $this->_sharePct($ranks, fn ($r) => $r === $rank);
        }

        return $shares;
    }

    private function _sharePct(array $ranks, callable $match): float
    {
        if (empty($ranks)) {
            return 0.0;
        }

        return count(array_filter($ranks, $match)) / count($ranks) * 100.0;
    }

    /**
     * The deterioration's sentence: "Two tiers below its settled Silver tier for 36 trading days; ...", or when
     * today is further down "Four tiers below its settled Platinum tier today (two or more for 52 trading days);
     * ...", since the day count is of the days at least two below.
     */
    private static function _dropText(string $settled, int $tiersDown, int $days): string
    {
        $tail = '; the tier now follows today.';
        if ($settled === TierCalculationService::BRONZE) {
            return 'At or under ' . MoneyFormat::get_formatted_number_plain(self::DEEP_RUST_PCT, 0) . '%, two steps'
                . ' below its settled Bronze tier, for ' . $days . ' trading days' . $tail;
        }
        $label = TierCalculationService::tierLabel($settled);
        if ($tiersDown <= 2) {
            return 'Two tiers below its settled ' . $label . ' tier for ' . $days . ' trading days' . $tail;
        }

        return ucfirst(self::TIER_COUNT_WORDS[$tiersDown] ?? (string) $tiersDown) . ' tiers below its settled '
            . $label . ' tier today (two or more for ' . $days . ' trading days)' . $tail;
    }

    /**
     * Tier rank for the drop rule: the tier's rank, one below Rust at or under DEEP_RUST_PCT. Today passes its
     * rank and its value; a history day passes its value only.
     */
    private function _dropRank(?int $rank, ?float $value): int
    {
        $rank ??= TierCalculationService::tierRank($this->_tiers->getTier($value));
        $rust   = TierCalculationService::tierRank(TierCalculationService::RUST);

        return $rank === $rust && $value !== null && $value <= self::DEEP_RUST_PCT ? $rust - 1 : $rank;
    }

    /**
     * The current drop: trading days (today included; 0 when today is not two tiers down) that the readings have
     * stayed two tiers or more under the tier before the drop, and that tier's rank.
     *
     * The tier before the drop is the highest tier T such that the days before the run (the trailing days two tiers
     * or more under T) settled at T or better, with at least min_history_days of them; otherwise today's settled
     * tier. Measuring against today's median alone would end a drop while the stock keeps falling: the drop's own
     * days pull the median down a tier, the line falls with it and the run's first days no longer count (a collapse
     * from Platinum would read Gold again for days at -25 %). A drop ends when today recovers, or when it has lasted
     * so long that too few days before it are left in the lookback (it is the norm then).
     *
     * @param array<string, float> $series     date => value of the chosen measure, ascending
     * @param int                  $todayRank  today's rank for the drop rule (_dropRank())
     *
     * @return array{0: int, 1: int} [days, rank of the tier before the drop]
     */
    private function _dropRun(array $series, int $settledRank, int $todayRank): array
    {
        $values    = array_values($series);
        $dropRanks = array_map(fn ($v) => $this->_dropRank(null, $v), $values);
        $reference = $settledRank;
        $run       = $this->_trailingRun($dropRanks, fn ($r) => $r <= $settledRank - 2);
        $top       = TierCalculationService::tierRank(TierCalculationService::PLATINUM);
        for ($rank = $top; $rank > $settledRank; $rank--) {
            $candidateRun = $this->_trailingRun($dropRanks, fn ($r) => $r <= $rank - 2);
            $before       = array_slice($values, 0, count($values) - $candidateRun);
            if ($candidateRun === 0 || count($before) < $this->_minHistoryDays) {
                continue;
            }
            $beforeTier = $this->_tiers->getTier($this->_valueAtConsistency($before));
            if (TierCalculationService::tierRank($beforeTier) >= $rank) {
                $reference = $rank;
                $run       = $candidateRun;
                break;
            }
        }

        return $todayRank <= $reference - 2 ? [1 + $run, $reference] : [0, $reference];
    }

    private function _tierOfRank(int $rank): string
    {
        foreach (TierCalculationService::TIERS as $tier) {
            if (TierCalculationService::tierRank($tier) === $rank) {
                return $tier;
            }
        }

        return TierCalculationService::RUST;
    }

    /**
     * Number of most recent rows (counting back from the last) that satisfy $match.
     */
    private function _trailingRun(array $ranks, callable $match): int
    {
        $count = 0;
        foreach (array_reverse(array_values($ranks)) as $rank) {
            if (!$match($rank)) {
                break;
            }
            $count++;
        }

        return $count;
    }

    private function _explain(string $settled, float $settledValue, array $ranks, array $base): string
    {
        $first = (string) $base['first'];
        $last  = (string) $base['last'];
        $label   = TierCalculationService::tierLabel($settled);
        $rank    = TierCalculationService::tierRank($settled);
        $share   = $this->_sharePct($ranks, fn ($r) => $r >= $rank);
        $orBetter = $settled === TierCalculationService::PLATINUM ? '' : ' or better';
        $point    = $this->_consistencyPct === 50.0
            ? 'median'
            : MoneyFormat::get_formatted_number_plain($this->_consistencyPct, 0) . 'th percentile';

        // Under ~11 months of rows (history started inside the lookback) the span is named explicitly.
        $span = (strtotime($last) - strtotime($first)) / 86400 >= 330
            ? 'the last 12 months'
            : 'the days since ' . $first;

        return 'Settled ' . $label . ': ' . $label . $orBetter . ' on '
            . MoneyFormat::get_formatted_number_plain($share, 0) . '% of ' . $span . $this->_measureText($base)
            . ' (' . $point . ' daily reading ' . MoneyFormat::get_formatted_pct($settledValue) . '%).';
    }

    private function _measureText(array $base): string
    {
        return match (true) {
            $base['fallback'] => ', on the 1Y market return (too few days of your own return yet; no signals'
                . ' until there are)',
            $base['measure'] === self::MEASURE_OWN    => ', on your own return',
            $base['measure'] === self::MEASURE_MARKET => ', on the 1Y market return',
            default                                   => '',
        };
    }

    private function _make(
        array $base,
        ?string $settled,
        ?float $settledValue,
        string $status,
        string $explanation,
        ?string $signal = null,
        ?string $action = null,
        bool $alertable = false
    ): SettledTier
    {
        return new SettledTier(
            settledTier:       $settled,
            settledValue:      $settledValue,
            status:            $status,
            days:              $base['days'],
            firstDate:         $base['first'],
            lastDate:          $base['last'],
            shares:            $base['shares'],
            atOrAboveTodayPct: $base['above'] ?? null,
            atOrBelowTodayPct: $base['below'] ?? null,
            signal:            $signal,
            action:            $action,
            alertable:         $alertable,
            deterioratingDays: $base['deteriorating'] ?? 0,
            ownedSince:        $base['ownedSince'],
            explanation:       $explanation,
            measure:           $base['measure'],
            measureFallback:   $base['fallback'],
            series:            $base['series'],
            dropFrom:          $base['dropFrom'] ?? null
        );
    }
}
