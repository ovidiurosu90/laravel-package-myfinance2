<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

class DrawdownService
{
    private const CACHE_TTL                = 7200; // 2 hours
    private const CACHE_KEY_PREFIX         = 'drawdown_v1_u';
    private const BENCHMARK_SYMBOL         = 'VUSA.AS';
    private const MIN_HISTORY_DAYS         = 30;
    private const EXIT_ZONE_THRESHOLD      = 0.85; // within 15% of peak = in exit zone
    private const EXIT_ZONE_LOOKBACK_YEARS = 2;

    // Lookback window for watchlist-only symbols (no purchase date).
    public const WATCHLIST_LOOKBACK_YEARS = 2;

    private array $_eurRates = [];

    // symbol => native trade currency iso (e.g. 'USD', 'GBp'), captured while loading prices so the
    // EUR-converted peak can be shown back in the symbol's own trading currency.
    private array $_symbolCurrencies = [];

    public function handle(int $userId): array
    {
        // Drawdown/momentum/exit-zones are pure historical (no live-price dependence), so the 2h
        // snapshot is safe to cache; it barely moves intraday. CategorizationService reads this and
        // the cron pre-warms it. NOTE: clear this cache (or run the refresh-symbol-performance cron)
        // after changing any drawdown/exit-zone formula.
        return Cache::remember(
            self::CACHE_KEY_PREFIX . $userId,
            self::CACHE_TTL,
            fn () => $this->_compute($userId)
        );
    }

    public static function clearCache(int $userId): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $userId);
    }

    private function _compute(int $userId): array
    {
        // Load trades and build the per-symbol windows once. Owned start dates, exited
        // symbols, and the same-window benchmark all derive from this single window set
        // instead of each re-querying the trades table. The windows also feed the VUSA
        // same-window benchmark, so its span matches the dates each symbol was held.
        $windowsMap = $this->_buildWindowsMap($userId);

        $ownedStartDates      = $this->_getOwnedSymbolStartDates($windowsMap);
        $watchlistOnlySymbols = $this->_getWatchlistOnlySymbols($userId, array_keys($ownedStartDates));
        $exitedSymbols        = $this->_getExitedSymbolsFromTrades(
            $windowsMap,
            array_merge(array_keys($ownedStartDates), $watchlistOnlySymbols)
        );

        $this->_loadEurRates();

        $earliestDate = $this->_resolveEarliestDate(
            $ownedStartDates,
            array_merge($watchlistOnlySymbols, $exitedSymbols)
        );
        if ($earliestDate === null) {
            return [];
        }

        $allSymbols = array_values(array_unique(array_merge(
            array_keys($ownedStartDates),
            $watchlistOnlySymbols,
            $exitedSymbols,
            [self::BENCHMARK_SYMBOL]
        )));

        $historicalPrices = $this->_loadHistoricalPrices($allSymbols, $earliestDate);
        $vusaPrices       = $historicalPrices[self::BENCHMARK_SYMBOL] ?? [];

        $result = [];

        foreach ($ownedStartDates as $symbol => $startDate) {
            $symPrices = $historicalPrices[$symbol] ?? [];
            $data      = $this->_computeSymbolData(
                $symPrices, $vusaPrices, $startDate, $this->_symbolCurrencies[$symbol] ?? null
            );

            // For the benchmark itself, relative drawdown is 1.0 by definition.
            if ($symbol === self::BENCHMARK_SYMBOL && $data['max_drawdown'] !== null) {
                $data['relative_drawdown'] = 1.0;
            }

            // Momenta lets the quadrant chart period buttons work for owned symbols too.
            $data['momenta'] = $this->_computeAllMomenta($symPrices);

            // Benchmark (VUSA.AS) CAGR over the SAME dates you were involved in this symbol
            // (first purchase through today), so the alpha answers "what would VUSA have returned
            // over the period I held this". For a single continuous holding this lines up exactly
            // with the position's own CAGR.
            $data['vusa_same_window_pct'] = $this->_benchmarkSpanCagr(
                $vusaPrices, $windowsMap[$symbol] ?? []
            );
            $data['vusa_same_window_raw_pct'] = $this->_benchmarkSpanRaw(
                $vusaPrices, $windowsMap[$symbol] ?? []
            );

            $result[$symbol] = $data;
        }

        $watchlistStartDate = Carbon::today()
            ->subYears(self::WATCHLIST_LOOKBACK_YEARS)
            ->format('Y-m-d');

        foreach ($watchlistOnlySymbols as $symbol) {
            if (isset($result[$symbol])) {
                continue;
            }
            $symPrices                             = $historicalPrices[$symbol] ?? [];
            $symbolData                            = $this->_computeSymbolData(
                $symPrices, $vusaPrices, $watchlistStartDate, $this->_symbolCurrencies[$symbol] ?? null
            );
            $momenta                               = $this->_computeAllMomenta($symPrices);
            $symbolData['momenta']                 = $momenta;
            $symbolData['momentum_annualized_pct'] = $momenta['1y'];
            // Watchlist-only symbols are never held, so "same window" falls back to the
            // standard lookback span used for their drawdown/momentum.
            $symbolData['vusa_same_window_pct']    = $this->benchmarkCagrBetween(
                $vusaPrices, $watchlistStartDate
            );
            $symbolData['vusa_same_window_raw_pct'] = $this->benchmarkRawBetween(
                $vusaPrices, $watchlistStartDate
            );
            $result[$symbol]                       = $symbolData;
        }

        foreach ($exitedSymbols as $symbol) {
            if (isset($result[$symbol])) {
                continue;
            }
            $symPrices                             = $historicalPrices[$symbol] ?? [];
            $symbolData                            = $this->_computeSymbolData(
                $symPrices, $vusaPrices, $watchlistStartDate, $this->_symbolCurrencies[$symbol] ?? null
            );
            $momenta                               = $this->_computeAllMomenta($symPrices);
            $symbolData['momenta']                 = $momenta;
            $symbolData['momentum_annualized_pct'] = $momenta['1y'];
            // Exited positions: VUSA over the dates you were involved (first buy to last sell),
            // so the alpha reflects the index over the same span you actually held, not a fixed
            // lookback.
            $symbolData['vusa_same_window_pct']    = $this->_benchmarkSpanCagr(
                $vusaPrices, $windowsMap[$symbol] ?? []
            );
            $symbolData['vusa_same_window_raw_pct'] = $this->_benchmarkSpanRaw(
                $vusaPrices, $windowsMap[$symbol] ?? []
            );
            $symbolData['is_exited']               = true;
            $result[$symbol]                       = $symbolData;
        }

        return $result;
    }

    private function _getOwnedSymbolStartDates(array $windowsMap): array
    {
        $startDates = [];

        foreach ($windowsMap as $symbol => $symWindows) {
            $openWindows = array_filter($symWindows, fn($w) => $w['is_open']);
            if (empty($openWindows)) {
                continue;
            }
            $earliest = null;
            foreach ($openWindows as $w) {
                $date = $w['start_date'];
                if ($earliest === null || $date < $earliest) {
                    $earliest = $date;
                }
            }
            if ($earliest !== null) {
                $daysSinceEntry = (int) Carbon::today()->diffInDays(Carbon::parse($earliest));
                if ($daysSinceEntry < self::MIN_HISTORY_DAYS) {
                    $startDates[$symbol] = Carbon::today()
                        ->subYears(self::WATCHLIST_LOOKBACK_YEARS)
                        ->format('Y-m-d');
                } else {
                    $startDates[$symbol] = $earliest->format('Y-m-d');
                }
            }
        }

        return $startDates;
    }

    private function _getExitedSymbolsFromTrades(array $windowsMap, array $excludeSymbols): array
    {
        $exited = [];

        foreach ($windowsMap as $symbol => $symWindows) {
            if (in_array($symbol, $excludeSymbols, true)) {
                continue;
            }
            $openWindows = array_filter($symWindows, fn($w) => $w['is_open']);
            if (empty($openWindows)) {
                $exited[] = $symbol;
            }
        }

        return $exited;
    }

    private function _getWatchlistOnlySymbols(int $userId, array $ownedSymbols): array
    {
        return WatchlistSymbol::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->whereNotIn('symbol', $ownedSymbols)
            ->pluck('symbol')
            ->toArray();
    }

    private function _resolveEarliestDate(array $ownedStartDates, array $watchlistSymbols): ?string
    {
        $watchlistDate = !empty($watchlistSymbols)
            ? Carbon::today()->subYears(self::WATCHLIST_LOOKBACK_YEARS)->format('Y-m-d')
            : null;

        $ownedEarliest = !empty($ownedStartDates) ? min(array_values($ownedStartDates)) : null;

        if ($ownedEarliest === null && $watchlistDate === null) {
            return null;
        }
        if ($ownedEarliest === null) {
            return $watchlistDate;
        }
        if ($watchlistDate === null) {
            return $ownedEarliest;
        }
        return min($ownedEarliest, $watchlistDate);
    }

    private function _loadEurRates(): void
    {
        $this->_eurRates = ['EUR' => 1.0];

        $stats = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', 'LIKE', 'EUR%=X')
            ->orderBy('date', 'desc')
            ->get()
            ->unique('symbol');

        foreach ($stats as $stat) {
            $currency = substr($stat->symbol, 3, 3);
            if (strlen($currency) === 3 && $currency !== 'EUR') {
                $this->_eurRates[$currency] = ($stat->unit_price > 0)
                    ? 1.0 / (float) $stat->unit_price
                    : 1.0;
            }
        }

        if (!isset($this->_eurRates['GBP'])) {
            $gbpStat = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
                ->where('symbol', 'EURGBP=X')
                ->orderBy('date', 'desc')
                ->first();
            $this->_eurRates['GBP'] = ($gbpStat && $gbpStat->unit_price > 0)
                ? 1.0 / (float) $gbpStat->unit_price
                : 1.0;
        }
        $this->_eurRates['GBp'] = $this->_eurRates['GBP'] / 100.0;
        $this->_eurRates['GBX'] = $this->_eurRates['GBP'] / 100.0;
    }

    /**
     * @return array symbol => (dateStr => price_eur)
     */
    private function _loadHistoricalPrices(array $symbols, string $fromDate): array
    {
        $allStats = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->where('date', '>=', $fromDate)
            ->select('symbol', 'date', 'unit_price', 'currency_iso_code')
            ->orderBy('date')
            ->get();

        $grouped = [];
        foreach ($allStats as $stat) {
            $sym     = $stat->symbol;
            $dateStr = is_string($stat->date)
                ? substr($stat->date, 0, 10)
                : $stat->date->format('Y-m-d');
            $eurRate                       = $this->_eurRates[$stat->currency_iso_code] ?? 1.0;
            $grouped[$sym][$dateStr]       = (float) $stat->unit_price * $eurRate;
            $this->_symbolCurrencies[$sym] = $stat->currency_iso_code;
        }

        $this->_fillGapsFromApiCache($grouped, $symbols, $fromDate);

        return $grouped;
    }

    /**
     * For symbols whose earliest DB date is later than $fromDate, fetch the
     * missing price history from the Yahoo Finance API (results are cached for
     * 7 days) and merge it in.  Nothing is written to stats_historical.
     */
    private function _fillGapsFromApiCache(
        array &$grouped,
        array $symbols,
        string $fromDate
    ): void
    {
        $cache = new HistoricalPriceCache();

        foreach ($symbols as $symbol) {
            $dbEarliest = !empty($grouped[$symbol])
                ? min(array_keys($grouped[$symbol]))
                : null;

            if ($dbEarliest !== null && $dbEarliest <= $fromDate) {
                continue; // DB covers the full range
            }

            // Gap end = day before the first DB row (or today if no DB data at all)
            $gapTo = $dbEarliest
                ? Carbon::parse($dbEarliest)->subDay()->format('Y-m-d')
                : Carbon::today()->format('Y-m-d');

            if ($gapTo < $fromDate) {
                continue;
            }

            $fetched = $cache->fetch($symbol, $fromDate, $gapTo);

            if (empty($fetched['prices']) || empty($fetched['currency'])) {
                continue;
            }

            $eurRate = $this->_eurRates[$fetched['currency']] ?? 1.0;
            $this->_symbolCurrencies[$symbol] ??= $fetched['currency'];

            foreach ($fetched['prices'] as $dateStr => $price) {
                if ($dateStr >= $fromDate && $dateStr <= $gapTo) {
                    $grouped[$symbol][$dateStr] = $price * $eurRate;
                }
            }
        }
    }

    /**
     * @param array  $symPrices   dateStr => price_eur (full history from earliestDate)
     * @param array  $vusaPrices  dateStr => price_eur
     * @param string $startDate   date from which to measure drawdown
     */
    private function _computeSymbolData(
        array $symPrices,
        array $vusaPrices,
        string $startDate,
        ?string $currency = null
    ): array
    {
        $exitZones = !empty($symPrices) ? $this->_computeAllExitZones($symPrices, $currency) : [];

        // Latest stored close (EUR) so a live-price overlay can rescale the latest-price-dependent
        // figures (momenta, exit-zone proximity) without re-running the historical compute.
        $latestPriceEur = !empty($symPrices) ? $symPrices[max(array_keys($symPrices))] : null;

        $base = [
            'start_date'              => $startDate,
            'max_drawdown'            => null,
            'relative_drawdown'       => null,
            'relative_drawdowns'      => $this->_computeAllRelativeDrawdowns($symPrices, $vusaPrices),
            'vusa_max_drawdown'       => null,
            'exit_zone'               => $exitZones['2y'] ?? null,
            'exit_zones'              => $exitZones,
            'momentum_annualized_pct' => null,
            'latest_price_eur'        => $latestPriceEur,
            'vusa_same_window_pct'    => null,
            'vusa_same_window_raw_pct' => null,
        ];

        $filteredSym  = array_filter($symPrices,  fn($_, $d) => $d >= $startDate, ARRAY_FILTER_USE_BOTH);
        $filteredVusa = array_filter($vusaPrices, fn($_, $d) => $d >= $startDate, ARRAY_FILTER_USE_BOTH);

        if (count($filteredSym) < self::MIN_HISTORY_DAYS) {
            return $base;
        }

        $symDrawdown  = $this->_maxDrawdown($filteredSym);
        $vusaDrawdown = $this->_maxDrawdown($filteredVusa);

        $base['max_drawdown']      = $symDrawdown;
        $base['vusa_max_drawdown'] = $vusaDrawdown;

        if ($vusaDrawdown > 0.0001) {
            $base['relative_drawdown'] = $symDrawdown / $vusaDrawdown;
        } else {
            // VUSA.AS had no meaningful drawdown over this period (all-time rise): treat as low-risk
            Log::warning(
                "DrawdownService: VUSA.AS zero drawdown for symbol from {$startDate}; "
                . 'treating relative drawdown as null'
            );
        }

        return $base;
    }

    /**
     * Max drawdown: largest peak-to-trough decline over any sub-period.
     * Scans forward tracking the running peak; the maximum drawdown at any point
     * is the true max drawdown. Returns 0 if the series never declined.
     */
    private function _maxDrawdown(array $prices): float
    {
        if (empty($prices)) {
            return 0.0;
        }

        $maxDrawdown = 0.0;
        $peak        = -INF;

        foreach ($prices as $price) {
            if ($price > $peak) {
                $peak = $price;
            }
            if ($peak > 0.0) {
                $drawdown = ($peak - $price) / $peak;
                if ($drawdown > $maxDrawdown) {
                    $maxDrawdown = $drawdown;
                }
            }
        }

        return $maxDrawdown;
    }

    /**
     * Computes exit-zone data for each time horizon (3m, 6m, 1y, 2y).
     * Exit zone: current price within EXIT_ZONE_THRESHOLD of the peak in the window.
     * Returns keyed array; individual entries are null when insufficient data.
     */
    private function _computeAllExitZones(array $allSymPrices, ?string $currency = null): array
    {
        $windows = ['3m' => 91, '6m' => 182, '1y' => 365, '2y' => 730];
        $result  = [];

        // Rate used to convert the stored EUR price back to the symbol's native trade currency
        // (peak_price_eur was unit_price * this rate, so dividing recovers the original quote).
        $eurRate = $currency !== null ? (float) ($this->_eurRates[$currency] ?? 1.0) : 1.0;

        $latestDate   = max(array_keys($allSymPrices));
        $currentPrice = $allSymPrices[$latestDate];

        if ($currentPrice <= 0.0) {
            return array_fill_keys(array_keys($windows), null);
        }

        foreach ($windows as $label => $days) {
            $cutoff       = Carbon::today()->subDays($days)->format('Y-m-d');
            $recentPrices = array_filter(
                $allSymPrices,
                fn($_, $d) => $d >= $cutoff,
                ARRAY_FILTER_USE_BOTH
            );

            if (empty($recentPrices)) {
                $result[$label] = null;
                continue;
            }

            $peakPrice = -INF;
            $peakDate  = null;
            foreach ($recentPrices as $date => $price) {
                if ($price > $peakPrice) {
                    $peakPrice = $price;
                    $peakDate  = $date;
                }
            }

            $result[$label] = $peakPrice > 0.0 ? [
                'peak_price_eur'    => round($peakPrice, 4),
                'peak_price_native' => $eurRate > 0.0 ? round($peakPrice / $eurRate, 4) : null,
                'peak_currency'     => $currency,
                'peak_price_date'   => $peakDate,
                'proximity_pct'     => round(($currentPrice / $peakPrice - 1.0) * 100.0, 2),
                'in_zone'           => $currentPrice >= self::EXIT_ZONE_THRESHOLD * $peakPrice,
            ] : null;
        }

        return $result;
    }

    /**
     * Overlay the latest live price (pre/post-market aware) onto a single symbol's cached
     * drawdown entry, so the quadrant's per-window market return ("Gain") and peak proximity
     * ("From peak") reflect the current price instead of the last stored regular close.
     *
     * The cached series is pure-historical and ends on the regular close, so only the
     * latest-price-dependent figures are patched; the historical peaks (until exceeded) and
     * drawdowns are left intact. Momenta are rescaled by the live/stored price ratio (the stored
     * momentum already encodes latest/start, so no per-window start price is needed). Peak
     * proximity is recomputed in the native trade currency where available (FX-free, matching the
     * peak label shown in the UI), and a live price above a window peak becomes the new peak.
     *
     * @param array      $entry      A DrawdownService result entry (momenta, exit_zones, latest_price_eur).
     * @param float      $liveEur    Latest live price in EUR.
     * @param float|null $liveNative Latest live price in the symbol's native trade currency.
     */
    public static function overlayLivePrice(array $entry, float $liveEur, ?float $liveNative = null): array
    {
        if ($liveEur <= 0.0) {
            return $entry;
        }

        // Per-window market return ("Gain"): the stored momentum encodes latest/start, so scaling
        // that ratio by the live/stored move gives the live return without the start price. Needs
        // the stored close, so this is skipped for older cache entries that predate latest_price_eur.
        $latestEur = $entry['latest_price_eur'] ?? null;
        if ($latestEur !== null && $latestEur > 0.0 && !empty($entry['momenta'])) {
            $scale = $liveEur / $latestEur;
            if (abs($scale - 1.0) >= 1e-9) {
                $windowDays = ['3m' => 91, '6m' => 182, '1y' => 365, '2y' => 730];
                foreach ($entry['momenta'] as $p => $pct) {
                    if ($pct === null || !isset($windowDays[$p])) {
                        continue;
                    }
                    $days        = $windowDays[$p];
                    $storedRatio = $days < 365
                        ? 1.0 + $pct / 100.0                       // raw return
                        : pow(1.0 + $pct / 100.0, $days / 365.0);  // CAGR -> total ratio
                    $liveRatio = $storedRatio * $scale;
                    if ($liveRatio <= 0.0) {
                        continue;
                    }
                    $entry['momenta'][$p] = $days < 365
                        ? ($liveRatio - 1.0) * 100.0
                        : (pow($liveRatio, 365.0 / $days) - 1.0) * 100.0;
                }
                $entry['momentum_annualized_pct'] = $entry['momenta']['1y'] ?? ($entry['momentum_annualized_pct'] ?? null);
            }
        }

        // Per-window peak proximity ("From peak"): only needs the live price and the stored peak,
        // so it runs independently of latest_price_eur (works even on an older cached entry).
        if (!empty($entry['exit_zones'])) {
            foreach ($entry['exit_zones'] as $p => $zone) {
                if (!is_array($zone)) {
                    continue;
                }
                $entry['exit_zones'][$p] = self::_overlayExitZone($zone, $liveEur, $liveNative);
            }
            $entry['exit_zone'] = $entry['exit_zones']['2y'] ?? ($entry['exit_zone'] ?? null);
        }

        return $entry;
    }

    /**
     * Recompute one exit-zone entry against the live price. Prefers the native trade currency
     * (FX-free, matches the displayed peak); a live price above the window peak becomes the new
     * peak. Returns the zone unchanged when it has no usable peak.
     */
    private static function _overlayExitZone(array $zone, float $liveEur, ?float $liveNative): array
    {
        $peakNative = $zone['peak_price_native'] ?? null;
        if ($liveNative !== null && !empty($peakNative) && $peakNative > 0.0) {
            $live = $liveNative;
            $peak = (float) $peakNative;
        } elseif (!empty($zone['peak_price_eur']) && $zone['peak_price_eur'] > 0.0) {
            $live = $liveEur;
            $peak = (float) $zone['peak_price_eur'];
        } else {
            return $zone;
        }

        if ($live > $peak) {
            if ($liveNative !== null) {
                $zone['peak_price_native'] = round($liveNative, 4);
            }
            $zone['peak_price_eur']  = round($liveEur, 4);
            $zone['peak_price_date'] = Carbon::today()->format('Y-m-d');
            $zone['proximity_pct']   = 0.0;
            $zone['in_zone']         = true;
        } else {
            $zone['proximity_pct'] = round(($live / $peak - 1.0) * 100.0, 2);
            $zone['in_zone']       = $live >= self::EXIT_ZONE_THRESHOLD * $peak;
        }

        return $zone;
    }

    /**
     * Computes relative drawdown (symbol / VUSA.AS) for each time horizon (3m, 6m, 1y, 2y).
     * Returns keyed array; individual entries are null when insufficient data.
     */
    private function _computeAllRelativeDrawdowns(array $symPrices, array $vusaPrices): array
    {
        $windows = ['3m' => 91, '6m' => 182, '1y' => 365, '2y' => 730];
        $result  = [];

        foreach ($windows as $label => $days) {
            $cutoff = Carbon::today()->subDays($days)->format('Y-m-d');
            $fSym   = array_filter($symPrices,  fn($_, $d) => $d >= $cutoff, ARRAY_FILTER_USE_BOTH);
            $fVusa  = array_filter($vusaPrices, fn($_, $d) => $d >= $cutoff, ARRAY_FILTER_USE_BOTH);

            if (count($fSym) < self::MIN_HISTORY_DAYS) {
                $result[$label] = null;
                continue;
            }

            $symDd  = $this->_maxDrawdown($fSym);
            $vusaDd = $this->_maxDrawdown($fVusa);

            $result[$label] = $vusaDd > 0.0001 ? round($symDd / $vusaDd, 4) : null;
        }

        return $result;
    }

    /**
     * symbol => [window, ...] for every traded symbol, built once so the same-window benchmark
     * can measure the VUSA span over the exact dates the position was held. Uses the same window
     * builder as SymbolPerformanceService, so the windows (and therefore the benchmark span)
     * line up with the position's own CAGR.
     */
    private function _buildWindowsMap(int $userId): array
    {
        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->orderBy('timestamp')
            ->get(['symbol', 'action', 'quantity', 'status', 'timestamp']);

        return $trades->isEmpty()
            ? []
            : (new SymbolPerformanceWindowBuilder())->build($trades);
    }

    /**
     * VUSA.AS CAGR over the full span a position was involved with: the earliest window start
     * through today (if still held) or the latest window end (if fully exited). This is the
     * "what would the index have returned over the dates I held this" benchmark; for a single
     * continuous holding it matches the position's own CAGR span exactly. Returns null when there
     * are no windows or the span is under a year.
     *
     * @param array $vusaPrices  dateStr => price_eur
     * @param array $windows     symbol windows (each with start_date, end_date, is_open)
     */
    private function _benchmarkSpanCagr(array $vusaPrices, array $windows): ?float
    {
        $span = $this->_windowSpan($windows);
        return $span === null
            ? null
            : $this->benchmarkCagrBetween($vusaPrices, $span['start'], $span['end']);
    }

    /**
     * Raw (non-annualized) VUSA.AS return over the same span as _benchmarkSpanCagr, for the
     * short-period alpha shown on positions held under a year.
     */
    private function _benchmarkSpanRaw(array $vusaPrices, array $windows): ?float
    {
        $span = $this->_windowSpan($windows);
        return $span === null
            ? null
            : $this->benchmarkRawBetween($vusaPrices, $span['start'], $span['end']);
    }

    /**
     * The date span a position was involved with: earliest window start through today (if still
     * held, null end) or the latest window end (if fully exited). Null when there are no windows.
     *
     * @return array{start: string, end: ?string}|null
     */
    private function _windowSpan(array $windows): ?array
    {
        if (empty($windows)) {
            return null;
        }

        $minStart = null;
        $maxEnd   = null;
        $hasOpen  = false;
        foreach ($windows as $window) {
            $start = $window['start_date']->format('Y-m-d');
            if ($minStart === null || $start < $minStart) {
                $minStart = $start;
            }
            if (!empty($window['is_open']) || empty($window['end_date'])) {
                $hasOpen = true;
            } else {
                $end = $window['end_date']->format('Y-m-d');
                if ($maxEnd === null || $end > $maxEnd) {
                    $maxEnd = $end;
                }
            }
        }

        // Still holding => measure through the latest available price (null lets the helper
        // default to today); otherwise measure to the last sell date.
        return ['start' => $minStart, 'end' => $hasOpen ? null : $maxEnd];
    }

    /**
     * CAGR (geometric annualized return) of a price series over a single calendar window. Used by
     * _benchmarkSpanCagr for held positions and directly for watchlist-only symbols (measured over
     * the standard lookback). Both sides of the alpha are CAGRs, keeping the "better or worse than
     * the S&P 500" comparison apples-to-apples.
     *
     * Returns the percentage per year, or null when the series has no usable start/end price or
     * the window is shorter than a year (sub-1Y is never annualized, matching the position side).
     *
     * @param array       $prices  dateStr => price_eur
     * @param string      $start   inclusive start date (Y-m-d); first available price >= this is used
     * @param string|null $end     inclusive end date (Y-m-d); last available price <= this is used,
     *                             defaulting to the latest available price (today) when null
     */
    public function benchmarkCagrBetween(array $prices, string $start, ?string $end = null): ?float
    {
        $span = $this->_spanEndpoints($prices, $start, $end);
        if ($span === null || $span['days'] < 365) {
            return null;
        }

        $years = $span['days'] / 365.0;
        $ratio = $span['endPrice'] / $span['startPrice'];
        return (pow($ratio, 1.0 / $years) - 1.0) * 100.0;
    }

    /**
     * Raw (non-annualized) return of a price series over a single window, used as the short-period
     * alpha basis for positions held under a year, where annualizing would inflate the figure. No
     * minimum-duration guard; returns null only when the window has no usable start/end price.
     *
     * @param array       $prices  dateStr => price_eur
     * @param string      $start   inclusive start date (Y-m-d)
     * @param string|null $end     inclusive end date (Y-m-d); defaults to the latest price
     */
    public function benchmarkRawBetween(array $prices, string $start, ?string $end = null): ?float
    {
        $span = $this->_spanEndpoints($prices, $start, $end);
        if ($span === null) {
            return null;
        }

        return ($span['endPrice'] - $span['startPrice']) / $span['startPrice'] * 100.0;
    }

    /**
     * Resolve the first usable start price (first trading day on/after $start) and last usable end
     * price (last trading day on/before $end) of a price series, plus the day count between them.
     * Shared by the CAGR and raw benchmark helpers so the endpoint logic lives in one place.
     *
     * @return array{startPrice: float, endPrice: float, days: int}|null
     */
    private function _spanEndpoints(array $prices, string $start, ?string $end = null): ?array
    {
        if (empty($prices)) {
            return null;
        }

        ksort($prices);
        $end = $end ?? max(array_keys($prices));

        // First trading day on or after the start date.
        $startPrice = null;
        $startDate  = null;
        foreach ($prices as $date => $price) {
            if ($date >= $start) {
                $startDate  = $date;
                $startPrice = $price;
                break;
            }
        }

        // Last trading day on or before the end date.
        $endPrice = null;
        $endDate  = null;
        foreach ($prices as $date => $price) {
            if ($date > $end) {
                break;
            }
            $endDate  = $date;
            $endPrice = $price;
        }

        if ($startPrice === null || $endPrice === null
            || $startPrice <= 0.0 || $startDate === null || $startDate >= $endDate
        ) {
            return null;
        }

        $days = (int) Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        return ['startPrice' => $startPrice, 'endPrice' => $endPrice, 'days' => $days];
    }

    private function _computeAllMomenta(array $symPrices): array
    {
        return [
            '3m' => $this->_computeMomentumForPeriod($symPrices, 91),
            '6m' => $this->_computeMomentumForPeriod($symPrices, 182),
            '1y' => $this->_computeMomentumForPeriod($symPrices, 365),
            '2y' => $this->_computeMomentumForPeriod($symPrices, 730),
        ];
    }

    private function _computeMomentumForPeriod(array $symPrices, int $days): ?float
    {
        if (count($symPrices) < self::MIN_HISTORY_DAYS) {
            return null;
        }

        $cutoff      = Carbon::today()->subDays($days)->format('Y-m-d');
        $latestDate  = max(array_keys($symPrices));
        $latestPrice = $symPrices[$latestDate];

        $priceAtCutoff = null;
        $bestDate      = null;
        foreach ($symPrices as $date => $price) {
            if ($date < $cutoff) {
                continue;
            }
            if ($bestDate === null || $date < $bestDate) {
                $bestDate      = $date;
                $priceAtCutoff = $price;
            }
        }

        if ($priceAtCutoff === null || $priceAtCutoff <= 0.0) {
            return null;
        }

        // <1Y: raw return (no annualization — extrapolating short windows inflates numbers).
        // >=1Y: CAGR so multi-year periods are comparable to the 1Y baseline.
        if ($days < 365) {
            return ($latestPrice - $priceAtCutoff) / $priceAtCutoff * 100.0;
        }

        $ratio = $latestPrice / $priceAtCutoff;
        return (pow($ratio, 365.0 / $days) - 1.0) * 100.0;
    }
}
