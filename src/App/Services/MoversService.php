<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Computes and caches "Biggest Movers" data per user.
 *
 * All four periods use live prices (from FinanceAPI cache, refreshed every minute by the
 * finance-api-cron) so that any intraday move is reflected across today, weekly, monthly,
 * and yearly columns simultaneously.
 *
 * Cache key structure:
 *   movers:{userId}:today    — TTL 2 min
 *   movers:{userId}:weekly   — TTL 24 h  (last-good-value if cron stops)
 *   movers:{userId}:monthly  — TTL 24 h
 *   movers:{userId}:yearly   — TTL 24 h
 *
 * Each cached value: ['losers' => [...], 'gainers' => [...]]
 * Each mover entry:  ['symbol', 'gain_eur', 'gain_percentage', 'inception_label']
 */
class MoversService
{
    private const CACHE_TTL_TODAY = 120;        // 2 minutes
    private const CACHE_TTL_HISTORICAL = 86400; // 24 hours
    private const TOP_N = 3;

    /** Per-instance EUR rate cache to avoid repeated StatHistorical lookups within one refresh. */
    private array $_eurRateCache = [];

    /** Per-instance inception price cache to avoid repeated lookups across periods. */
    private array $_inceptionCache = [];

    private function _getCacheKey(int $userId, string $period): string
    {
        return "movers:{$userId}:{$period}";
    }

    /**
     * Get aggregated open positions for a user across all accounts.
     * Returns: symbol => ['quantity' => float, 'trade_currency' => string]
     */
    private function _getOpenPositionsForUser(int $userId): array
    {
        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('status', 'OPEN')
            ->with('tradeCurrencyModel')
            ->get();

        $positions = [];
        foreach ($trades as $trade) {
            $symbol = $trade->symbol;
            $qty = (float) $trade->quantity;
            if (!isset($positions[$symbol])) {
                $positions[$symbol] = [
                    'quantity'       => 0,
                    'trade_currency' => $trade->tradeCurrencyModel->iso_code,
                ];
            }
            $positions[$symbol]['quantity'] += ($trade->action === 'BUY' ? $qty : -$qty);
        }

        return array_filter($positions, fn($p) => abs($p['quantity']) > 0.0001);
    }

    /**
     * Get the most recent historical prices on or before $date for multiple symbols in one query.
     * Returns: symbol => ['unit_price' => float, 'date' => string] (missing symbols excluded).
     */
    private function _batchGetHistoricalPrices(array $symbols, \DateTimeInterface $date): array
    {
        if (empty($symbols)) {
            return [];
        }

        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->where('date', '<=', $date->format('Y-m-d'))
            ->orderBy('date', 'DESC')
            ->get(['symbol', 'unit_price', 'date']);

        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->symbol])) {
                $result[$row->symbol] = ['unit_price' => (float) $row->unit_price, 'date' => $row->date];
            }
        }
        return $result;
    }

    /**
     * Get the earliest available historical price for a symbol (inception fallback).
     * Results are memoized within the instance lifetime.
     * Returns: ['unit_price' => float, 'date' => string] or null.
     */
    private function _getInceptionPrice(string $symbol): ?array
    {
        if (array_key_exists($symbol, $this->_inceptionCache)) {
            return $this->_inceptionCache[$symbol];
        }

        $row = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', $symbol)
            ->orderBy('date', 'ASC')
            ->first();

        $this->_inceptionCache[$symbol] = $row
            ? ['unit_price' => (float) $row->unit_price, 'date' => $row->date]
            : null;

        return $this->_inceptionCache[$symbol];
    }

    /**
     * Get EUR multiplier for converting trade-currency gains to EUR.
     * Returns a multiplier: gain_eur = gain_in_trade_currency * rate.
     *   EUR → 1.0
     *   USD → 1 / EURUSD  (e.g. 1/1.10 ≈ 0.909)
     *   GBP → 1 / EURGBP  (e.g. 1/0.85 ≈ 1.176)
     *   GBp / GBX → 1 / (EURGBP × 100)  — 100 pence = 1 GBP
     * Returns null when the currency is unsupported — callers must skip that position.
     * Results are memoized per currency+date within the instance lifetime.
     */
    private function _getEurRate(string $tradeCurrency, \DateTimeInterface $date,
        string $symbol = ''): ?float
    {
        if ($tradeCurrency === 'EUR') {
            return 1.0;
        }

        $cacheKey = $tradeCurrency . ':' . $date->format('Y-m-d');
        if (array_key_exists($cacheKey, $this->_eurRateCache)) {
            return $this->_eurRateCache[$cacheKey];
        }

        $exchangeMap = [
            'USD' => ['symbol' => 'EURUSD=X', 'scale' => 1],
            'GBP' => ['symbol' => 'EURGBP=X', 'scale' => 1],
            'GBp' => ['symbol' => 'EURGBP=X', 'scale' => 100],
            'GBX' => ['symbol' => 'EURGBP=X', 'scale' => 100], // GBX = GBp (pence)
        ];

        if (!isset($exchangeMap[$tradeCurrency])) {
            $ctx = $symbol ? " (symbol: {$symbol})" : '';
            Log::warning("MoversService: unsupported trade currency {$tradeCurrency}{$ctx}, skipping position");
            $this->_eurRateCache[$cacheKey] = null;
            return null;
        }

        $cfg = $exchangeMap[$tradeCurrency];
        $row = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', $cfg['symbol'])
            ->where('date', '<=', $date->format('Y-m-d'))
            ->orderBy('date', 'DESC')
            ->first();

        if (empty($row) || (float) $row->unit_price <= 0) {
            $ctx = $symbol ? " (symbol: {$symbol})" : '';
            Log::warning("MoversService: no exchange rate for {$tradeCurrency}{$ctx}"
                . " on {$date->format('Y-m-d')}, skipping position");
            $this->_eurRateCache[$cacheKey] = null;
            return null;
        }

        $rate = 1.0 / ((float) $row->unit_price * $cfg['scale']);
        $this->_eurRateCache[$cacheKey] = $rate;
        return $rate;
    }

    /**
     * Get a map of symbol => [accountId => true] for the user's open positions,
     * and the total number of distinct accounts.
     * Returns: ['symbol_accounts' => [...], 'account_count' => int]
     */
    private function _getSymbolAccountsMap(int $userId): array
    {
        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('status', 'OPEN')
            ->select(['symbol', 'account_id'])
            ->get();

        $symbolAccounts = [];
        $allAccountIds = [];
        foreach ($trades as $trade) {
            $symbolAccounts[$trade->symbol][$trade->account_id] = true;
            $allAccountIds[$trade->account_id] = true;
        }

        return [
            'symbol_accounts' => $symbolAccounts,
            'account_count'   => count($allAccountIds),
        ];
    }

    /**
     * Find the most recent date in stats_historical where at least $threshold distinct
     * accounts had at least one symbol with recorded data.
     * Looks back up to 30 days. Returns null if no qualifying date is found.
     */
    private function _findLastTradingDate(array $symbolAccounts, int $threshold): ?\DateTime
    {
        if (empty($symbolAccounts)) {
            return null;
        }

        $symbols = array_keys($symbolAccounts);
        $cutoffDate = (new \DateTime())->modify('-30 days')->format('Y-m-d');

        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->where('date', '>=', $cutoffDate)
            ->orderBy('date', 'DESC')
            ->select(['date', 'symbol'])
            ->get();

        $dateAccountCounts = [];
        foreach ($rows as $row) {
            $accountIds = $symbolAccounts[$row->symbol] ?? [];
            foreach (array_keys($accountIds) as $accountId) {
                $dateAccountCounts[$row->date][$accountId] = true;
            }
        }

        krsort($dateAccountCounts);
        foreach ($dateAccountCounts as $date => $accounts) {
            if (count($accounts) >= $threshold) {
                return new \DateTime($date);
            }
        }

        return null;
    }

    /**
     * Format a date range for period labels (e.g. "Mar 8 – Mar 15" or "Mar 15, 2025 – Mar 15, 2026").
     * Year is included on both sides only when the two dates span different calendar years.
     */
    private function _formatPeriodRange(\DateTimeInterface $from, \DateTimeInterface $to): string
    {
        $sameYear = $from->format('Y') === $to->format('Y');
        $fromStr = $sameYear ? $from->format('M j') : $from->format('M j, Y');
        $toStr = $sameYear ? $to->format('M j') : $to->format('M j, Y');
        return $fromStr . ' – ' . $toStr;
    }

    /**
     * Compute the total current portfolio value in EUR across all positions with valid quotes.
     */
    private function _computeTotalPortfolioValueEur(
        array $positions,
        array $quotes,
        \DateTimeInterface $currentDate
    ): float
    {
        $total = 0.0;
        foreach ($positions as $symbol => $position) {
            if (empty($quotes[$symbol]['price'])) {
                continue;
            }
            $eurRate = $this->_getEurRate($position['trade_currency'], $currentDate, $symbol);
            if ($eurRate === null) {
                continue;
            }
            $total += abs($position['quantity']) * (float) $quotes[$symbol]['price'] * $eurRate;
        }
        return $total;
    }

    /**
     * Sort gains into bottom/top TOP_N.
     * Returns: ['losers' => [...], 'gainers' => [...]]
     */
    private function _rankGains(array $gains): array
    {
        if (empty($gains)) {
            return ['losers' => [], 'gainers' => []];
        }

        uasort($gains, fn($a, $b) => $a['gain_eur'] <=> $b['gain_eur']);
        $all = array_values($gains);

        $losers = array_values(array_filter($all, fn($g) => $g['gain_eur'] < 0));
        $gainers = array_values(array_reverse(
            array_values(array_filter($all, fn($g) => $g['gain_eur'] > 0))
        ));

        return [
            'losers'  => array_slice($losers, 0, self::TOP_N),
            'gainers' => array_slice($gainers, 0, self::TOP_N),
        ];
    }

    /**
     * Compute today's movers from live day_change data (already in $quotes).
     */
    private function _computeTodayMovers(
        array $positions,
        array $quotes,
        \DateTimeInterface $currentDate,
        float $totalPortfolioEur
    ): array
    {
        $gains = [];

        foreach ($positions as $symbol => $position) {
            if (!isset($quotes[$symbol])) {
                continue;
            }
            $dayChange = $quotes[$symbol]['day_change'] ?? 0;
            if (abs($dayChange) < 0.0001) {
                continue;
            }

            $eurRate = $this->_getEurRate($position['trade_currency'], $currentDate, $symbol);
            if ($eurRate === null) {
                continue;
            }
            $gainInTradeCurrency = $dayChange * $position['quantity'];
            $gainEur = $gainInTradeCurrency * $eurRate;

            if (abs($gainEur) < 0.005) {
                continue;
            }

            $gains[$symbol] = [
                'symbol'          => $symbol,
                'gain_eur'        => $gainEur,
                'gain_percentage' => $totalPortfolioEur > 0.005
                    ? ($gainEur / $totalPortfolioEur) * 100
                    : 0,
                'inception_label' => null,
            ];
        }

        return $this->_rankGains($gains);
    }

    /**
     * Compute historical period movers using live price from $quotes as current price
     * and pre-fetched $batchRefPrices for the reference price.
     * Falls back to inception date when no reference price exists for the full period.
     */
    private function _computeHistoricalPeriodMovers(
        array $positions,
        array $quotes,
        array $batchRefPrices,
        \DateTimeInterface $currentDate,
        float $totalPortfolioEur
    ): array
    {
        $delistedSymbols = config('trades.delisted_symbols', []);
        $gains = [];

        foreach ($positions as $symbol => $position) {
            if (FinanceAPI::isUnlisted($symbol) || in_array($symbol, $delistedSymbols, true)) {
                continue;
            }
            if (empty($quotes[$symbol]['price'])) {
                continue;
            }

            $currentPrice = (float) $quotes[$symbol]['price'];
            $refPriceStat = $batchRefPrices[$symbol] ?? null;
            $inceptionLabel = null;

            if (empty($refPriceStat)) {
                $refPriceStat = $this->_getInceptionPrice($symbol);
                if (empty($refPriceStat)) {
                    continue;
                }
                $refDate = new \DateTime($refPriceStat['date']);
                $inceptionLabel = 'since ' . $refDate->format("M 'y");
            }

            $eurRate = $this->_getEurRate($position['trade_currency'], $currentDate, $symbol);
            if ($eurRate === null) {
                continue;
            }
            $qty = $position['quantity'];
            $gainInTradeCurrency = ($currentPrice - $refPriceStat['unit_price']) * $qty;
            $gainEur = $gainInTradeCurrency * $eurRate;

            if (abs($gainEur) < 0.005) {
                continue;
            }

            $gains[$symbol] = [
                'symbol'          => $symbol,
                'gain_eur'        => $gainEur,
                'gain_percentage' => $totalPortfolioEur > 0.005
                    ? ($gainEur / $totalPortfolioEur) * 100
                    : 0,
                'inception_label' => $inceptionLabel,
            ];
        }

        return $this->_rankGains($gains);
    }

    /**
     * Compute and cache all four period movers for a user in a single pass.
     * Fetches live quotes once (reuses FinanceAPI 2-min cache) and shares them
     * across all four period computations.
     * Called by the minutely finance-api-cron after refreshAccountOverview().
     */
    public function refreshAllMovers(int $userId): void
    {
        $positions = $this->_getOpenPositionsForUser($userId);
        if (empty($positions)) {
            return;
        }

        $financeUtils = new FinanceUtils();
        $quotes = $financeUtils->getQuotes(array_keys($positions), null, false);
        if (empty($quotes)) {
            return;
        }

        $currentDate = new \DateTime();
        $totalPortfolioEur = $this->_computeTotalPortfolioValueEur($positions, $quotes, $currentDate);

        // For "today" label: on weekends, find the last trading day with enough account data.
        $dayOfWeek = (int) $currentDate->format('N'); // 1 = Mon … 7 = Sun
        if ($dayOfWeek >= 6) {
            $symbolAccountsData = $this->_getSymbolAccountsMap($userId);
            $threshold = max(1, min(2, $symbolAccountsData['account_count']));
            $lastTradingDate = $this->_findLastTradingDate(
                $symbolAccountsData['symbol_accounts'], $threshold
            );
            $todayDateLabel = $lastTradingDate
                ? $lastTradingDate->format('M j')
                : $currentDate->format('M j');
        } else {
            $todayDateLabel = $currentDate->format('M j');
        }

        $todayMovers = $this->_computeTodayMovers($positions, $quotes, $currentDate, $totalPortfolioEur);
        $todayMovers['date_label'] = $todayDateLabel;
        Cache::put($this->_getCacheKey($userId, 'today'), $todayMovers, self::CACHE_TTL_TODAY);

        $symbols = array_keys($positions);
        $referenceDates = [
            'weekly'  => (clone $currentDate)->modify('-7 days'),
            'monthly' => (clone $currentDate)->modify('-1 month'),
            'yearly'  => (clone $currentDate)->modify('-1 year'),
        ];

        foreach ($referenceDates as $period => $referenceDate) {
            $batchRefPrices = $this->_batchGetHistoricalPrices($symbols, $referenceDate);
            $movers = $this->_computeHistoricalPeriodMovers(
                $positions, $quotes, $batchRefPrices, $currentDate, $totalPortfolioEur
            );
            $movers['period_label'] = $this->_formatPeriodRange($referenceDate, $currentDate);
            Cache::put($this->_getCacheKey($userId, $period), $movers, self::CACHE_TTL_HISTORICAL);
        }
    }

    /**
     * Read movers for a user from cache for all four periods.
     * Returns: ['today' => ..., 'weekly' => ..., 'monthly' => ..., 'yearly' => ...]
     * Each value is ['losers' => [...], 'gainers' => [...]] or null when not yet cached.
     */
    public function getMovers(int $userId): array
    {
        return [
            'today'   => Cache::get($this->_getCacheKey($userId, 'today')),
            'weekly'  => Cache::get($this->_getCacheKey($userId, 'weekly')),
            'monthly' => Cache::get($this->_getCacheKey($userId, 'monthly')),
            'yearly'  => Cache::get($this->_getCacheKey($userId, 'yearly')),
        ];
    }

    /**
     * Get all user IDs with open trades. Used by the cron to iterate over users.
     */
    public function getAllUserIds(): array
    {
        return Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('status', 'OPEN')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->toArray();
    }
}
