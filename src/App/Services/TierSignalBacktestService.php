<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Loads what the signal backtest (TierSignalBacktest) needs for one user and runs it: the stored daily tier history,
 * each symbol's daily closes (StoredCloses: stats_historical, gaps from HistoricalPriceCache) and the benchmark's;
 * the tier signals and the range-bound / TAKE PROFIT readings (PositionReadingAsOf) are replayed.
 * One parameter cell for the page (cached for an hour), the whole grid for the command
 * (finance:tier-signal-backtest). Read-only.
 */
final class TierSignalBacktestService
{
    /** The grid the plan asks for: rare share % x consistency %. */
    public const GRID_RARE_SHARE  = [10, 20, 30];
    public const GRID_CONSISTENCY = [50, 60];

    private const CACHE_TTL = 3600;

    private ?array $_data = null;

    /**
     * One cell: the summary, the baseline and the episodes, with the parameters used.
     *
     * @return array{from: string, to: ?string, rare_share: float, consistency: float, horizons: int[],
     *     days: int, symbols: int, missing_prices: string[], summary: array, baseline: array, episodes: list<array>}
     */
    public function report(
        int $userId,
        ?string $from = null,
        ?float $rareShare = null,
        ?float $consistency = null,
        bool $refresh = false
    ): array
    {
        $from        = $from ?: $this->_defaultFrom();
        $rareShare   = $rareShare ?? (float) config('watchlistsymbols.tier_history.rare_share_pct', 20);
        $consistency = $consistency ?? (float) config('watchlistsymbols.tier_history.consistency_pct', 50);
        $key = 'myfinance2.tier_signal_backtest.' . $userId . '.' . $from . '.' . $rareShare . '.' . $consistency;
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_TTL, fn () => $this->_run($userId, $from, $rareShare, $consistency));
    }

    /**
     * Every grid cell (rare share x consistency), the history and prices loaded once.
     *
     * @return list<array> reports as report() returns them
     */
    public function grid(int $userId, ?string $from = null): array
    {
        $from    = $from ?: $this->_defaultFrom();
        $reports = [];
        foreach (self::GRID_CONSISTENCY as $consistency) {
            foreach (self::GRID_RARE_SHARE as $rareShare) {
                $reports[] = $this->_run($userId, $from, (float) $rareShare, (float) $consistency);
            }
        }

        return $reports;
    }

    private function _run(int $userId, string $from, float $rareShare, float $consistency): array
    {
        $data = $this->_load($userId);
        $cfg  = config('watchlistsymbols.tier_history', []);
        $resolver = new SettledTierResolver(
            $consistency,
            (int) ($cfg['min_history_days'] ?? 60),
            $rareShare,
            (int) ($cfg['deteriorate_persist_days'] ?? 20),
            (array) ($cfg['buy_settled_tiers'] ?? [TierCalculationService::GOLD, TierCalculationService::PLATINUM])
        );
        $engine = new TierSignalBacktest(
            $resolver, TierSignalBacktest::HORIZONS, (int) ($cfg['lookback_days'] ?? 365),
            new PositionReadingAsOf(RangeBoundPattern::fromConfig(), WeakTierProfit::fromConfig())
        );
        $result = $engine->run($data['history'], $data['prices'], $data['benchmark'], $from);
        usort($result['episodes'], fn ($a, $b) => [$b['start'], $a['symbol']] <=> [$a['start'], $b['symbol']]);

        return [
            'from'           => $from,
            'to'             => $data['to'],
            'rare_share'     => $rareShare,
            'consistency'    => $consistency,
            'horizons'       => TierSignalBacktest::HORIZONS,
            'days'           => $result['days'],
            'symbols'        => count($data['history']),
            'missing_prices' => $data['missing'],
            'summary'        => $result['summary'],
            'baseline'       => $result['baseline'],
            'episodes'       => $result['episodes'],
        ];
    }

    /**
     * The user's tier history and the closes of each of its symbols and of the benchmark, loaded once.
     */
    private function _load(int $userId): array
    {
        if ($this->_data !== null && $this->_data['user'] === $userId) {
            return $this->_data;
        }
        $history = (new TierHistoryRepository())->loadAll($userId);
        // Closes are needed from a year before the first day a signal can be read (min_history_days stored days
        // behind it): the forward returns start there and the range-bound reading needs its 52-week range. The
        // missing span before stats_historical comes from HistoricalPriceCache, under the same cache entries the
        // watchlist's 2-year windows keep warm (keyed by the start's year and the gap's last month).
        $minDays = (int) config('watchlistsymbols.tier_history.min_history_days', 60);
        $first   = null;
        foreach ($history as $rows) {
            $date  = array_keys($rows)[$minDays] ?? null;
            $first = $date !== null && ($first === null || $date < $first) ? $date : $first;
        }
        $first = $first !== null
            ? Carbon::parse($first)->subDays(366)->toDateString()
            : Carbon::today()->toDateString();

        $prices  = [];
        $missing = [];
        $to      = null;
        foreach (array_keys($history) as $symbol) {
            $closes = StoredCloses::load((string) $symbol, $first)['prices'];
            if (empty($closes)) {
                $missing[] = (string) $symbol;
                continue;
            }
            $prices[$symbol] = $closes;
            $last = array_key_last($closes);
            $to   = $to === null || $last > $to ? $last : $to;
        }
        $benchmark = $prices[TierCalculationService::BENCHMARK_SYMBOL]
            ?? StoredCloses::load(TierCalculationService::BENCHMARK_SYMBOL, $first)['prices'];

        return $this->_data = [
            'user'      => $userId,
            'history'   => $history,
            'prices'    => $prices,
            'benchmark' => $benchmark,
            'missing'   => $missing,
            'to'        => $to,
        ];
    }

    private function _defaultFrom(): string
    {
        return (string) config('watchlistsymbols.tier_history.start_date', '2025-01-01');
    }
}
