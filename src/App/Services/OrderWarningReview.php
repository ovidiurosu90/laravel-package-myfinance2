<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Calibration of the order warnings (finance:order-warning-review): for every trade since the tier history start
 * (and every filled order with no trade of its own), the tier reading of its day is rebuilt (TierReadingAsOf), the
 * warnings evaluated (OrderTierWarnings; the codes that need live data cannot fire) and the decision measured against
 * VUSA.AS 21 / 63 / 126 trading days later (TierSignalBacktest::forwardFn()).
 *
 * A decision's excess is in points against the index and signed so that positive means the decision was right: a BUY
 * that then beat the index, a SELL of a symbol that then trailed it. Per action and per code: how often the code
 * fired, the share of the action's decisions, the hit rate and the median excess, next to the baseline of every
 * decision of that action; and the level the decision rule suggests.
 *
 * Decision rule: a code is serious only if its decisions did clearly worse than the baseline at the longest horizon
 * (hit rate at least 10 points lower, or median excess at least 5 points lower), it has at least 10 measured decisions
 * and it fires on under 30 % of the action's decisions. Otherwise soft. The live-only codes keep their level.
 *
 * Read-only.
 */
final class OrderWarningReview
{
    public const HORIZONS = TierSignalBacktest::HORIZONS;

    public const MIN_DECISIONS     = 10;
    public const MAX_SHARE_PCT     = 30.0;
    public const HIT_GAP_POINTS    = 10.0;
    public const MEDIAN_GAP_POINTS = 5.0;

    /** Extra rows splitting BUY_WEAK_TIER by the Index Gap Settled softening. */
    public const WEAK_TIER_HARD = 'BUY_WEAK_TIER (not softened)';
    public const WEAK_TIER_SOFT = 'BUY_WEAK_TIER (softened)';

    /**
     * @return array{from: string, decisions: list<array>, none: int, baseline: array, codes: array, suggested: array}
     */
    public function run(int $userId, ?string $from = null): array
    {
        $from      = $from ?: (string) config('watchlistsymbols.tier_history.start_date', '2025-01-01');
        $decisions = $this->_decisions($userId, $from);
        $history   = (new TierHistoryRepository())->loadAll($userId);
        $benchmark = TierCalculationService::BENCHMARK_SYMBOL;
        $closesFrom = Carbon::parse($from)->subDays(740)->format('Y-m-d');
        $index     = StoredCloses::load($benchmark, $closesFrom)['prices'];
        $asOf      = TierReadingAsOf::fromConfig();
        // Every code at serious, so each fires whatever its configured level; a soft BUY_WEAK_TIER is a softened one.
        // Softening always on here, so both weak-tier groups are measured whether or not it is configured.
        $warnings  = new OrderTierWarnings(
            array_fill_keys(array_keys(OrderTierWarnings::DEFAULT_LEVELS), OrderTierWarnings::LEVEL_SERIOUS),
            (float) config('watchlistsymbols.tier_history.max_position_pct', 10),
            true
        );

        $closes  = [];
        $forward = [];
        $rows    = [];
        $none    = 0;
        foreach ($decisions as $decision) {
            $symbol = $decision['symbol'];
            $closes[$symbol] ??= $symbol === $benchmark ? $index : StoredCloses::load($symbol, $closesFrom)['prices'];
            $forward[$symbol] ??= TierSignalBacktest::forwardFn($closes[$symbol], $index);
            $read = $asOf->read($symbol, $decision['date'], $history[$symbol] ?? [], $history[$benchmark] ?? [],
                $closes[$symbol]);
            if ($read['source'] === TierReadingAsOf::SOURCE_NONE) {
                $none++;
                continue;
            }
            $sign    = $decision['action'] === 'SELL' ? -1.0 : 1.0;
            $excess  = [];
            foreach (self::HORIZONS as $h) {
                $rel        = ($forward[$symbol])($decision['date'], $h);
                $excess[$h] = $rel === null ? null : $sign * $rel;
            }
            $rows[] = $decision + [
                'tier'     => $read['reading']['effective_tier'] ?? null,
                'warnings' => $warnings->evaluate($decision['action'], $read['reading']['facts']),
                'excess'   => $excess,
            ];
        }

        $baseline = [];
        foreach (['BUY', 'SELL'] as $action) {
            $baseline[$action] = self::stats(array_filter($rows, fn ($r) => $r['action'] === $action));
        }
        $codes = $this->_codeStats($rows);

        return [
            'from'      => $from,
            'decisions' => $rows,
            'none'      => $none,
            'baseline'  => $baseline,
            'codes'     => $codes,
            'suggested' => $this->suggest($codes, $baseline),
        ];
    }

    /**
     * Per horizon: measured decisions, hit rate (% with a positive excess) and median excess; plus the count.
     */
    public static function stats(array $rows): array
    {
        $stats = ['count' => count($rows)];
        foreach (self::HORIZONS as $h) {
            $values = array_values(array_filter(array_map(fn ($r) => $r['excess'][$h], $rows),
                fn ($v) => $v !== null));
            $stats[$h] = [
                'n'        => count($values),
                'hit_rate' => $values ? count(array_filter($values, fn ($v) => $v > 0)) / count($values) * 100.0
                    : null,
                'median'   => TierSignalBacktest::median($values),
            ];
        }

        return $stats;
    }

    /**
     * The level the decision rule gives each code (live-only codes: their configured level, no market evidence).
     *
     * @return array<string, array{level: string, reason: string}>
     */
    public function suggest(array $codes, array $baseline): array
    {
        $levels    = OrderTierWarnings::fromConfig();
        $softens   = (bool) config('watchlistsymbols.order_warnings.soften_weak_tier_on_index_gap', false);
        $h         = max(self::HORIZONS);
        $suggested = [];
        foreach (array_keys(OrderTierWarnings::DEFAULT_LEVELS) as $code) {
            if (in_array($code, OrderTierWarnings::LIVE_ONLY, true)) {
                $suggested[$code] = ['level' => $levels->level($code), 'reason' => 'needs live data: kept'];
                continue;
            }
            // With the softening configured, a softened weak-tier BUY is soft anyway: the rule judges the ones that
            // stay serious. Without it, every weak-tier BUY is serious and judged.
            $row  = $code === OrderTierWarnings::BUY_WEAK_TIER && $softens && isset($codes[self::WEAK_TIER_HARD])
                ? $codes[self::WEAK_TIER_HARD] : ($codes[$code] ?? null);
            $base = $baseline[$row['action'] ?? 'BUY'][$h] ?? null;
            $s    = $row[$h] ?? null;
            if ($row === null || $s === null || $s['n'] < self::MIN_DECISIONS) {
                $suggested[$code] = ['level' => OrderTierWarnings::LEVEL_SOFT, 'reason' => 'under '
                    . self::MIN_DECISIONS . ' measured decisions'];
                continue;
            }
            if ($row['share_pct'] >= self::MAX_SHARE_PCT) {
                $suggested[$code] = ['level' => OrderTierWarnings::LEVEL_SOFT, 'reason' => 'fires on '
                    . MoneyFormat::get_formatted_number_plain($row['share_pct'], 0) . '% of decisions'];
                continue;
            }
            $hitGap    = ($base['hit_rate'] ?? 0.0) - ($s['hit_rate'] ?? 0.0);
            $medianGap = ($base['median'] ?? 0.0) - ($s['median'] ?? 0.0);
            $worse     = $hitGap >= self::HIT_GAP_POINTS || $medianGap >= self::MEDIAN_GAP_POINTS;
            $suggested[$code] = [
                'level'  => $worse ? OrderTierWarnings::LEVEL_SERIOUS : OrderTierWarnings::LEVEL_SOFT,
                'reason' => sprintf('hit %s pts, median %s pts vs baseline', self::_signed(-$hitGap),
                    self::_signed(-$medianGap)),
            ];
        }

        return $suggested;
    }

    /**
     * Per code (and the two BUY_WEAK_TIER splits): its action, fires, share of the action's decisions and stats().
     */
    private function _codeStats(array $rows): array
    {
        $byCode = [];
        foreach ($rows as $row) {
            foreach ($row['warnings'] as $warning) {
                $byCode[$warning['code']][] = $row;
                if ($warning['code'] === OrderTierWarnings::BUY_WEAK_TIER) {
                    $split = $warning['level'] === OrderTierWarnings::LEVEL_SOFT ? self::WEAK_TIER_SOFT
                        : self::WEAK_TIER_HARD;
                    $byCode[$split][] = $row;
                }
            }
        }
        $perAction = array_count_values(array_column($rows, 'action'));
        $order     = array_flip(array_merge(array_keys(OrderTierWarnings::DEFAULT_LEVELS),
            [self::WEAK_TIER_HARD, self::WEAK_TIER_SOFT]));
        uksort($byCode, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        $codes = [];
        foreach ($byCode as $code => $codeRows) {
            $action = $codeRows[0]['action'];
            $codes[$code] = [
                'action'    => $action,
                'share_pct' => count($codeRows) / max(1, $perAction[$action] ?? 0) * 100.0,
            ] + self::stats($codeRows);
        }

        return $codes;
    }

    /**
     * The user's trades since $from (transfers left out) and the filled orders with no trade of their own on the same
     * day, oldest first.
     *
     * @return list<array{kind: string, id: int, symbol: string, action: string, date: string}>
     */
    private function _decisions(int $userId, string $from): array
    {
        $decisions = [];
        $seen      = [];
        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->whereIn('action', ['BUY', 'SELL'])
            ->where('timestamp', '>=', $from)
            ->where(fn ($q) => $q->whereNull('is_transfer')->orWhere('is_transfer', false))
            ->orderBy('timestamp')
            ->get(['id', 'symbol', 'action', 'timestamp']);
        foreach ($trades as $trade) {
            $date = $trade->timestamp->format('Y-m-d');
            $decisions[] = ['kind' => 'trade', 'id' => (int) $trade->id, 'symbol' => $trade->symbol,
                'action' => $trade->action, 'date' => $date];
            $seen[$trade->symbol . '|' . $trade->action . '|' . $date] = true;
        }

        $orders = Order::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('status', 'FILLED')
            ->whereNull('trade_id')
            ->get(['id', 'symbol', 'action', 'placed_at', 'filled_at', 'created_at']);
        foreach ($orders as $order) {
            $date = ($order->placed_at ?? $order->created_at)?->format('Y-m-d');
            $done = $order->filled_at?->format('Y-m-d') ?? $date;
            if ($date === null || $date < $from || isset($seen[$order->symbol . '|' . $order->action . '|' . $done])) {
                continue;
            }
            $decisions[] = ['kind' => 'order', 'id' => (int) $order->id, 'symbol' => $order->symbol,
                'action' => $order->action, 'date' => $date];
        }
        usort($decisions, fn ($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);

        return $decisions;
    }

    private static function _signed(float $value): string
    {
        return ($value > 0 ? '+' : '') . MoneyFormat::get_formatted_number_plain($value, 1);
    }
}
