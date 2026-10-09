<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Resolves the target of a RELATIVE price alert ("5% below the 52W high") from the rolling
 * closing high / low of its window.
 *
 * Shared by the evaluation engine, the form preview endpoint and the save-time check, so the
 * preview and the engine always agree. Reference = highest / lowest daily CLOSE in the window,
 * in the symbol's native currency, read from stats_historical (today excluded, so the history is
 * stable for the day and cacheable), then bumped by the live price when it is a new extreme, the
 * same rule as the watchlist closing range (WatchlistTableMetaBuilder::_closingRange).
 *
 * DB only: never calls the network, so it is safe inside the 5-minute alert loop.
 */
class AlertReferenceResolver
{
    /**
     * Minimum closes inside a window, same as the 52W closing range
     * (DrawdownService::MIN_HISTORY_DAYS, FinanceUtils::MIN_CLOSING_HISTORY_DAYS).
     */
    public const MIN_CLOSES = 30;

    /**
     * The earliest stored close may start this many days after the window start (weekends,
     * holidays) and the window still counts as covered.
     */
    public const COVERAGE_GRACE_DAYS = 10;

    private const CACHE_PREFIX = 'price_alert_ref_v1_';

    /**
     * The per-day history is cached for an hour (never past midnight), so a close that lands in
     * stats_historical later in the day (e.g. yesterday's, written by the first cron pass after
     * midnight) is picked up within the hour instead of the next day.
     */
    private const CACHE_TTL_SECONDS = 3600;

    /** @var array<string, array<string, array|null>> symbol => window => stats */
    private array $_windows = [];

    /**
     * Load (and cache) the per-window highs / lows of the given symbols with one batched query
     * over the longest window for the symbols that are not cached yet.
     *
     * @param string[] $symbols
     */
    public function load(array $symbols): void
    {
        $today   = Carbon::today()->format('Y-m-d');
        $missing = [];

        foreach (array_unique($symbols) as $symbol) {
            if (isset($this->_windows[$symbol])) {
                continue;
            }
            $cached = Cache::get($this->_cacheKey($symbol, $today));
            if (is_array($cached)) {
                $this->_windows[$symbol] = $cached;
                continue;
            }
            $missing[] = $symbol;
        }

        if (empty($missing)) {
            return;
        }

        $closesBySymbol = $this->_loadCloses($missing, $today);
        $expiresAt      = min(now()->addSeconds(self::CACHE_TTL_SECONDS), Carbon::today()->endOfDay());

        foreach ($missing as $symbol) {
            $windows = self::computeWindows($closesBySymbol[$symbol] ?? [], $today);
            $this->_windows[$symbol] = $windows;
            Cache::put($this->_cacheKey($symbol, $today), $windows, $expiresAt);
        }
    }

    /**
     * Stats of one window: high / low with their dates, close count, first close and coverage.
     * Null when the symbol has no stored close in the window.
     */
    public function windowStats(string $symbol, string $window): ?array
    {
        $this->load([$symbol]);

        return $this->_windows[$symbol][$window] ?? null;
    }

    /**
     * Reference high / low after the live bump: ['price' => float, 'date' => 'Y-m-d'], or null
     * when the window is not covered by enough stored history.
     */
    public function reference(string $symbol, string $type, string $window, ?float $livePrice): ?array
    {
        $stats = $this->windowStats($symbol, $window);
        if ($stats === null || empty($stats['covered'])) {
            return null;
        }

        return self::applyLiveBump($type, $stats, $livePrice, Carbon::today()->format('Y-m-d'));
    }

    /**
     * Resolved target of a RELATIVE alert:
     * ['target' => float, 'reference_price' => float, 'reference_date' => 'Y-m-d'], or null when
     * the alert is not relative or its window is not covered.
     */
    public function target(PriceAlert $alert, ?float $livePrice): ?array
    {
        if (!$alert->isRelative() || empty($alert->reference_type) || empty($alert->reference_window)) {
            return null;
        }

        $reference = $this->reference(
            $alert->symbol,
            (string) $alert->reference_type,
            (string) $alert->reference_window,
            $livePrice
        );
        if ($reference === null) {
            return null;
        }

        return [
            'target'          => self::computeTarget(
                (string) $alert->reference_type,
                $reference['price'],
                (float) $alert->offset_pct
            ),
            'reference_price' => round($reference['price'], 6),
            'reference_date'  => $reference['date'],
        ];
    }

    /**
     * Resolve a RELATIVE alert and copy the result onto it. When $persist is set, the row is
     * written only when a value changed or the last resolution is from an earlier day, without
     * bumping updated_at (a refresh is not a user edit).
     *
     * @return bool False when the reference cannot be resolved (alert left untouched).
     */
    public function refresh(PriceAlert $alert, ?float $livePrice, bool $persist = true): bool
    {
        $resolved = $this->target($alert, $livePrice);
        if ($resolved === null) {
            return false;
        }

        $resolvedAt = $alert->reference_resolved_at;
        $changed    = $resolvedAt === null
            || $resolvedAt->lt(Carbon::today())
            || abs((float) $alert->target_price - $resolved['target']) > 0.0000005
            || abs((float) $alert->reference_price - $resolved['reference_price']) > 0.0000005
            || $alert->reference_date?->format('Y-m-d') !== $resolved['reference_date'];

        if (!$changed) {
            return true;
        }

        $alert->target_price          = $resolved['target'];
        $alert->reference_price       = $resolved['reference_price'];
        $alert->reference_date        = $resolved['reference_date'];
        $alert->reference_resolved_at = now();

        if ($persist && $alert->exists) {
            $alert->timestamps = false;
            $alert->save();
            $alert->timestamps = true;
        }

        return true;
    }

    /**
     * All eight references of a symbol for the form preview, keyed "TYPE:window" like
     * PriceAlert::referenceOptions(): price and date after the live bump, plus the coverage flag.
     *
     * @return array<string, array{type:string, window:string, label:string, reference_label:string,
     *                             price:float|null, price_label:string|null, date:string|null,
     *                             date_label:string|null, covered:bool}>
     */
    public function describe(string $symbol, ?float $livePrice): array
    {
        $this->load([$symbol]);

        return self::describeWindows($this->_windows[$symbol] ?? [], $livePrice, Carbon::today()->format('Y-m-d'));
    }

    /**
     * Same shape as describe() for closes that are not stored (e.g. a fallback fetch), so the
     * preview can still show figures for a symbol whose history gets backfilled on save.
     *
     * @param array<string, float> $closes date (Y-m-d) => close
     */
    public static function describeCloses(array $closes, ?float $livePrice, string $today): array
    {
        return self::describeWindows(self::computeWindows($closes, $today), $livePrice, $today);
    }

    /**
     * Calendar days of stored history in the longest window (0 when none), for the "only N days
     * of price history" validation message.
     */
    public function historyDays(string $symbol): int
    {
        $stats = $this->windowStats($symbol, '2y');
        if ($stats === null || empty($stats['first_date'])) {
            return 0;
        }

        return (int) Carbon::parse($stats['first_date'])->diffInDays(Carbon::today());
    }

    /**
     * Drop today's cached history of a symbol (after a backfill, or a split applied / reverted)
     * so the next resolution re-reads stats_historical.
     */
    public function forget(string $symbol): void
    {
        unset($this->_windows[$symbol]);
        Cache::forget($this->_cacheKey($symbol, Carbon::today()->format('Y-m-d')));
    }

    /**
     * Per-window highs / lows of a date => close series, considering closes strictly before today.
     * A window is covered when it has at least MIN_CLOSES closes and the first one is no later than
     * the window start + COVERAGE_GRACE_DAYS. Pure, so it is unit-testable.
     *
     * @param array<string, float|string> $closes date (Y-m-d) => close
     *
     * @return array<string, array|null> window => stats, or null when the window has no close
     */
    public static function computeWindows(array $closes, string $today): array
    {
        ksort($closes);
        $todayDate = Carbon::parse($today);
        $result    = [];

        foreach (PriceAlert::REFERENCE_WINDOW_DAYS as $window => $days) {
            $start = $todayDate->copy()->subDays($days)->format('Y-m-d');
            $stats = null;

            foreach ($closes as $date => $close) {
                $close = (float) $close;
                if ($date < $start || $date >= $today || $close <= 0.0) {
                    continue;
                }
                if ($stats === null) {
                    $stats = [
                        'high' => $close, 'high_date' => $date,
                        'low'  => $close, 'low_date'  => $date,
                        'count' => 0, 'first_date' => $date,
                    ];
                }
                $stats['count']++;
                if ($close > $stats['high']) {
                    $stats['high']      = $close;
                    $stats['high_date'] = $date;
                }
                if ($close < $stats['low']) {
                    $stats['low']      = $close;
                    $stats['low_date'] = $date;
                }
            }

            if ($stats !== null) {
                $graceEnd         = Carbon::parse($start)->addDays(self::COVERAGE_GRACE_DAYS)->format('Y-m-d');
                $stats['covered'] = $stats['count'] >= self::MIN_CLOSES && $stats['first_date'] <= $graceEnd;
            }
            $result[$window] = $stats;
        }

        return $result;
    }

    /**
     * Reference of one type from window stats, bumped by the live price when it is a new extreme
     * (the date becomes today).
     *
     * @return array{price: float, date: string}
     */
    public static function applyLiveBump(string $type, array $stats, ?float $livePrice, string $today): array
    {
        $isHigh = $type === 'HIGH';
        $price  = (float) ($isHigh ? $stats['high'] : $stats['low']);
        $date   = (string) ($isHigh ? $stats['high_date'] : $stats['low_date']);

        if ($livePrice !== null && $livePrice > 0.0
            && (($isHigh && $livePrice > $price) || (!$isHigh && $livePrice < $price))) {
            return ['price' => $livePrice, 'date' => $today];
        }

        return ['price' => $price, 'date' => $date];
    }

    /**
     * Target from a reference: HIGH goes below it (x (1 - offset%)), LOW goes above it
     * (x (1 + offset%)). Rounded to the 6 decimals of the target_price column.
     */
    public static function computeTarget(string $type, float $referencePrice, float $offsetPct): float
    {
        $factor = $type === 'HIGH'
            ? 1.0 - $offsetPct / 100.0
            : 1.0 + $offsetPct / 100.0;

        return round($referencePrice * $factor, 6);
    }

    /**
     * @param array<string, array|null> $windows
     */
    private static function describeWindows(array $windows, ?float $livePrice, string $today): array
    {
        $result = [];
        foreach (PriceAlert::referenceOptions() as $key => $label) {
            [$type, $window] = explode(':', $key);
            $stats     = $windows[$window] ?? null;
            $reference = $stats !== null ? self::applyLiveBump($type, $stats, $livePrice, $today) : null;

            $result[$key] = [
                'type'            => $type,
                'window'          => $window,
                'label'           => $label,
                'reference_label' => PriceAlert::REFERENCE_WINDOW_LABELS[$window] . ' closing '
                    . ($type === 'HIGH' ? 'high' : 'low'),
                'price'           => $reference !== null ? round($reference['price'], 6) : null,
                'price_label'     => $reference !== null
                    ? MoneyFormat::get_formatted_price($reference['price'], true)
                    : null,
                'date'            => $reference['date'] ?? null,
                'date_label'      => $reference !== null
                    ? Carbon::parse($reference['date'])->format('d M Y')
                    : null,
                'covered'         => !empty($stats['covered']),
            ];
        }

        return $result;
    }

    /**
     * Stored closes of the symbols over the longest window, before today.
     *
     * @param string[] $symbols
     *
     * @return array<string, array<string, float>> symbol => date => close
     */
    private function _loadCloses(array $symbols, string $today): array
    {
        $start = Carbon::parse($today)->subDays(max(PriceAlert::REFERENCE_WINDOW_DAYS))->format('Y-m-d');

        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->where('date', '>=', $start)
            ->where('date', '<', $today)
            ->toBase()
            ->get(['date', 'symbol', 'unit_price']);

        $closes = [];
        foreach ($rows as $row) {
            $closes[$row->symbol][substr((string) $row->date, 0, 10)] = (float) $row->unit_price;
        }

        return $closes;
    }

    private function _cacheKey(string $symbol, string $today): string
    {
        return self::CACHE_PREFIX . $symbol . '_' . $today;
    }
}
