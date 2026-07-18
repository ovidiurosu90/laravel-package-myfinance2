<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\StatToday;
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
    private const TOP_N = 5;

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
                    'quantity'            => 0,
                    'trade_currency'      => $trade->tradeCurrencyModel->iso_code,
                    'earliest_trade_date' => null,
                    'total_buy_cost'      => 0.0,
                    'total_buy_qty'       => 0.0,
                ];
            }
            $positions[$symbol]['quantity'] += ($trade->action === 'BUY' ? $qty : -$qty);

            $tradeDate = $trade->timestamp ? $trade->timestamp->format('Y-m-d') : null;
            if ($tradeDate !== null) {
                if ($positions[$symbol]['earliest_trade_date'] === null
                    || $tradeDate < $positions[$symbol]['earliest_trade_date']) {
                    $positions[$symbol]['earliest_trade_date'] = $tradeDate;
                }
            }

            if ($trade->action === 'BUY') {
                $feeInTradeCurrency = (float) $trade->fee * (float) $trade->exchange_rate;
                $positions[$symbol]['total_buy_cost'] += $qty * (float) $trade->unit_price + $feeInTradeCurrency;
                $positions[$symbol]['total_buy_qty'] += $qty;
            }
        }

        $positions = array_filter($positions, fn($p) => abs($p['quantity']) > 0.0001);

        foreach ($positions as &$pos) {
            $pos['avg_cost_in_trade_cur'] = $pos['total_buy_qty'] > 0.0001
                ? $pos['total_buy_cost'] / $pos['total_buy_qty']
                : null;
        }
        unset($pos);

        return $positions;
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
     * Get the most recent historical price on or before $date for a single symbol.
     * Returns: ['unit_price' => float, 'date' => string] or null.
     */
    private function _getHistoricalPrice(string $symbol, \DateTimeInterface $date): ?array
    {
        $result = $this->_batchGetHistoricalPrices([$symbol], $date);
        return $result[$symbol] ?? null;
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
     * Check if a day-over-day price ratio matches a common stock split ratio (forward or reverse).
     */
    private function _looksLikeSplitRatio(float $ratio): bool
    {
        return SplitDetectionService::looksLikeSplitRatio($ratio);
    }

    /**
     * Scan stats_historical for day-over-day price jumps that match common split ratios.
     * Returns: symbol => ['unit_price' => float, 'date' => string] pointing to the first entry
     *          after the LAST detected split, or null if no split was found for that symbol.
     * The caller should use the returned entry as the reference price instead of the period start.
     */
    private function _detectSplitsInPeriod(array $symbols, \DateTimeInterface $referenceDate): array
    {
        if (empty($symbols)) {
            return [];
        }

        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->where('date', '>=', $referenceDate->format('Y-m-d'))
            ->orderBy('symbol')
            ->orderBy('date', 'ASC')
            ->get(['symbol', 'unit_price', 'date']);

        $bySymbol = [];
        foreach ($rows as $row) {
            $bySymbol[$row->symbol][] = ['date' => $row->date, 'unit_price' => (float) $row->unit_price];
        }

        $result = [];
        foreach ($bySymbol as $symbol => $prices) {
            $lastSplitIdx = null;
            for ($i = 0; $i < count($prices) - 1; $i++) {
                if ($prices[$i]['unit_price'] <= 0) {
                    continue;
                }
                $ratio = $prices[$i + 1]['unit_price'] / $prices[$i]['unit_price'];
                if ($this->_looksLikeSplitRatio($ratio)) {
                    $lastSplitIdx = $i + 1;
                }
            }
            $result[$symbol] = $lastSplitIdx !== null ? $prices[$lastSplitIdx] : null;
        }

        return $result;
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

        $row = null;
        if ($date->format('Y-m-d') === date('Y-m-d')) {
            $row = StatToday::withoutGlobalScope(AssignedToUserScope::class)
                ->where('symbol', $cfg['symbol'])
                ->orderBy('timestamp', 'DESC')
                ->first();
        }
        if (empty($row) || (float) $row->unit_price <= 0) {
            $row = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
                ->where('symbol', $cfg['symbol'])
                ->where('date', '<=', $date->format('Y-m-d'))
                ->orderBy('date', 'DESC')
                ->first();
        }

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
     * Format a date range for period labels (e.g. "Mar 8 – Mar 15" or "Mar 15, 2025 – Mar 15, 2026").
     * Year is included on both sides only when the two dates span different calendar years.
     */
    private function _formatPeriodRange(\DateTimeInterface $from, \DateTimeInterface $to): string
    {
        $sameYear = $from->format('Y') === $to->format('Y');
        $fromStr = $sameYear ? $from->format('M j') : $from->format("M 'y");
        $toStr = $sameYear ? $to->format('M j') : $to->format("M 'y");
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
                // Root cause is reported once up front in refreshAllMovers(); keep the
                // per-computation trace at DEBUG so one missing symbol does not emit
                // a WARNING from every mover calculation.
                Log::debug("MoversService: no quote price for {$symbol}, excluded from portfolio total");
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
     * Determine the calendar date (Europe/Amsterdam) that a quote's day_change actually belongs to.
     *
     * Pre-market is the opening move of a fresh session, so it is dated by its own timestamp.
     * Post-market is an extension of the regular session that just closed, so it belongs to that
     * regular session's date, NOT the post-market timestamp: US after-hours runs until ~02:00
     * Europe/Amsterdam, so the post-market timestamp lands on the next calendar day and would
     * otherwise make yesterday's close + after-hours look like today's mover before today's
     * market (pre-market or open) has produced any move. Returns 'Y-m-d' or null when no
     * timestamp is available.
     */
    private function _effectiveQuoteDate(array $quote): ?string
    {
        if (!empty($quote['pre_market_day_change']) && !empty($quote['pre_market_timestamp'])) {
            return $quote['pre_market_timestamp']->format('Y-m-d');
        }
        if (!empty($quote['post_market_day_change'])) {
            if (!empty($quote['regular_market_timestamp'])) {
                return $quote['regular_market_timestamp']->format('Y-m-d');
            }
            if (!empty($quote['post_market_timestamp'])) {
                return $quote['post_market_timestamp']->format('Y-m-d');
            }
        }
        if (!empty($quote['regular_market_timestamp'])) {
            return $quote['regular_market_timestamp']->format('Y-m-d');
        }
        return null;
    }

    /**
     * Pick the session date the "Today" card should report.
     *
     * Yahoo's regularMarketChange keeps showing the previous session's move until a market reopens,
     * so before the user's markets open (e.g. early morning, weekends, holidays) a position's
     * day_change still reflects yesterday. To avoid labelling yesterday's move as "today":
     *   - if any non-crypto held symbol already has a session dated today, the card is "today";
     *   - otherwise it falls back to the most recent completed session (e.g. yesterday).
     * Crypto trades 24/7 so it is ignored here (it would otherwise always force "today").
     *
     * @param array $effDates symbol => ['date' => ?string, 'is_crypto' => bool]
     */
    private function _resolveSessionDate(array $effDates, string $today): string
    {
        $candidateDates = [];
        foreach ($effDates as $info) {
            if ($info['is_crypto'] || $info['date'] === null) {
                continue;
            }
            if ($info['date'] === $today) {
                return $today;
            }
            $candidateDates[] = $info['date'];
        }

        return empty($candidateDates) ? $today : max($candidateDates);
    }

    /**
     * Compute today's movers from live day_change data (already in $quotes).
     *
     * Only positions whose day_change belongs to the resolved session date are included; a symbol
     * whose market has not opened yet (still carrying yesterday's regularMarketChange) is skipped
     * while another market already trades today. Crypto is always included since it trades 24/7.
     */
    private function _computeTodayMovers(
        array $positions,
        array $quotes,
        \DateTimeInterface $currentDate,
        float $totalPortfolioEur
    ): array
    {
        $today = $currentDate->format('Y-m-d');

        $effDates = [];
        foreach ($positions as $symbol => $position) {
            if (!isset($quotes[$symbol])) {
                continue;
            }
            $effDates[$symbol] = [
                'date'      => $this->_effectiveQuoteDate($quotes[$symbol]),
                'is_crypto' => FinanceAPI::isCryptoSymbol($symbol),
            ];
        }

        $sessionDate = $this->_resolveSessionDate($effDates, $today);

        $gains = [];

        foreach ($positions as $symbol => $position) {
            if (!isset($quotes[$symbol])) {
                Log::debug("MoversService: no quote for {$symbol} in today movers, skipping");
                continue;
            }

            // Skip stale positions: a known session date that is not the card's session date means
            // this market has not opened today yet (its day_change is a prior session's move).
            // A null date (no timestamp) cannot be proven stale, so it is kept; crypto always stays.
            $info = $effDates[$symbol];
            if (!$info['is_crypto'] && $info['date'] !== null && $info['date'] !== $sessionDate) {
                continue;
            }

            $dayChange = $quotes[$symbol]['day_change'] ?? 0;
            if (abs($dayChange) < 0.0001) {
                continue;
            }

            // Guard: Yahoo pre-market split artifacts cause day_change to look like a massive move.
            $currentPrice = (float) $quotes[$symbol]['price'];
            $impliedPrevClose = $currentPrice - $dayChange;
            if ($currentPrice > 0 && $impliedPrevClose > 0
                && SplitDetectionService::isPriceSplitAnomaly($currentPrice, $impliedPrevClose)) {
                Log::warning("MoversService: {$symbol} day_change={$dayChange} looks like a"
                    . " split artifact (current={$currentPrice},"
                    . " implied_prev={$impliedPrevClose}), skipping today movers");
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

        $ranked = $this->_rankGains($gains);
        $portfolioTotalEur = array_sum(array_column($gains, 'gain_eur'));
        $ranked['portfolio_total_eur'] = $portfolioTotalEur;
        $ranked['portfolio_total_pct'] = $totalPortfolioEur > 0.005
            ? ($portfolioTotalEur / $totalPortfolioEur) * 100
            : 0;
        $ranked['date_label'] = (new \DateTime($sessionDate))->format('M j');
        $ranked['date_weekday'] = (new \DateTime($sessionDate))->format('l');
        return $ranked;
    }

    /**
     * Compute historical period movers using live price from $quotes as current price
     * and pre-fetched $batchRefPrices for the reference price.
     * If a split was detected for a symbol within the period ($splitAdjustedRefs), the first
     * post-split entry is used as reference instead, preventing pre/post-split price comparisons.
     * Falls back to inception date when no reference price exists for the full period.
     */
    private function _computeHistoricalPeriodMovers(
        array $positions,
        array $quotes,
        array $batchRefPrices,
        array $splitAdjustedRefs,
        \DateTimeInterface $currentDate,
        float $totalPortfolioEur,
        \DateTimeInterface $referenceDate
    ): array
    {
        $delistedSymbols = config('trades.delisted_symbols', []);
        $apiPriceIssueSymbols = config('trades.api_price_issue_symbols', []);
        $refDateStr = $referenceDate->format('Y-m-d');
        $gains = [];

        foreach ($positions as $symbol => $position) {
            if (in_array($symbol, $delistedSymbols, true)) {
                continue;
            }
            $earliestTradeDate = $position['earliest_trade_date'] ?? null;
            $isLateOpen = ($earliestTradeDate !== null && $earliestTradeDate > $refDateStr);
            if (empty($quotes[$symbol]['price'])) {
                Log::debug("MoversService: no quote price for {$symbol} in historical movers, skipping");
                continue;
            }

            $currentPrice = (float) $quotes[$symbol]['price'];
            $inceptionLabel = null;
            $inceptionTooltip = null;

            if ($isLateOpen) {
                // Position was opened after the period start — use avg cost (same as all-time),
                // so the value matches the Gain column in Open Positions.
                $avgCost = $position['avg_cost_in_trade_cur'] ?? null;
                if ($avgCost === null || $avgCost <= 0) {
                    Log::warning("MoversService: {$symbol} opened after period start"
                        . " but has no avg cost, skipping");
                    continue;
                }
                $refPriceStat = ['unit_price' => $avgCost];
                $refDate = new \DateTime($earliestTradeDate);
                $inceptionLabel = 'since ' . $refDate->format("M 'y");
                $openDate = $refDate->format('M j, Y');
                $inceptionTooltip = 'This position was opened on ' . $openDate . ','
                    . ' after the start of this period. Value matches All-time.';
            } elseif (!empty($splitAdjustedRefs[$symbol])) {
                // A split was detected within the period — use the first post-split entry as
                // reference to avoid comparing pre-split and post-split prices.
                $refPriceStat = $splitAdjustedRefs[$symbol];
                $refDate = new \DateTime($refPriceStat['date']);
                $inceptionLabel = 'since ' . $refDate->format("M 'y");
                $inceptionTooltip = 'Stock split detected within this period.'
                    . ' Comparison starts from ' . $refDate->format('M j, Y')
                    . ' (first available post-split price) instead of the period start.';
            } else {
                $refPriceStat = $batchRefPrices[$symbol] ?? null;
                if (empty($refPriceStat)) {
                    // For symbols with known API price issues (e.g. stock splits not in
                    // stats_historical), skip the inception fallback — the oldest stored price
                    // may be pre-split and produce wrong numbers.
                    if (in_array($symbol, $apiPriceIssueSymbols, true)) {
                        continue;
                    }
                    $refPriceStat = $this->_getInceptionPrice($symbol);
                    if (empty($refPriceStat)) {
                        Log::warning("MoversService: no historical or inception price for"
                            . " {$symbol}, skipping");
                        continue;
                    }
                    $refDate = new \DateTime($refPriceStat['date']);
                    $inceptionLabel = 'since ' . $refDate->format("M 'y");
                }
            }

            $eurRateToday = $this->_getEurRate($position['trade_currency'], $currentDate, $symbol);
            if ($eurRateToday === null) {
                continue;
            }
            // Unlisted symbols have manually-set FMV prices that rarely change; applying a
            // different EUR rate at the reference date would produce phantom gains/losses from
            // currency movement alone, not from any actual price change.
            if (UnlistedSymbol::isUnlisted($symbol)) {
                $eurRateRef = $eurRateToday;
            } else {
                $fxRefDate = $isLateOpen
                    ? new \DateTime($earliestTradeDate)
                    : new \DateTime($refPriceStat['date']);
                $eurRateRef = $this->_getEurRate($position['trade_currency'], $fxRefDate, $symbol)
                    ?? $eurRateToday;
            }
            $qty = $position['quantity'];
            $gainEur = ($currentPrice * $eurRateToday - $refPriceStat['unit_price'] * $eurRateRef) * $qty;

            if (abs($gainEur) < 0.005) {
                continue;
            }

            $gains[$symbol] = [
                'symbol'            => $symbol,
                'gain_eur'          => $gainEur,
                'gain_percentage'   => $totalPortfolioEur > 0.005
                    ? ($gainEur / $totalPortfolioEur) * 100
                    : 0,
                'inception_label'   => $inceptionLabel,
                'inception_tooltip' => $inceptionTooltip,
            ];
        }

        $ranked = $this->_rankGains($gains);
        $portfolioTotalEur = array_sum(array_column($gains, 'gain_eur'));
        $ranked['portfolio_total_eur'] = $portfolioTotalEur;
        $ranked['portfolio_total_pct'] = $totalPortfolioEur > 0.005
            ? ($portfolioTotalEur / $totalPortfolioEur) * 100
            : 0;
        return $ranked;
    }

    /**
     * Compute all-time movers using each position's weighted average cost as the reference price.
     * This matches the "Gain" column in Open Positions and correctly handles DCA positions
     * (where using the earliest historical market price would overstate gains/losses).
     * Every entry shows "since MMM 'YY" (the earliest trade date) as its label.
     */
    private function _computeAlltimeMovers(
        array $positions,
        array $quotes,
        \DateTimeInterface $currentDate,
        float $totalPortfolioEur
    ): array
    {
        $delistedSymbols = config('trades.delisted_symbols', []);
        $gains = [];

        foreach ($positions as $symbol => $position) {
            if (in_array($symbol, $delistedSymbols, true)) {
                continue;
            }
            if (empty($quotes[$symbol]['price'])) {
                Log::debug("MoversService: no quote price for {$symbol} in all-time movers, skipping");
                continue;
            }
            $earliestTradeDate = $position['earliest_trade_date'] ?? null;
            $avgCost = $position['avg_cost_in_trade_cur'] ?? null;
            if ($earliestTradeDate === null || $avgCost === null || $avgCost <= 0) {
                Log::warning("MoversService: {$symbol} missing earliest_trade_date or avg_cost, skipping");
                continue;
            }

            $eurRate = $this->_getEurRate($position['trade_currency'], $currentDate, $symbol);
            if ($eurRate === null) {
                continue;
            }

            $currentPrice = (float) $quotes[$symbol]['price'];
            $gainInTradeCurrency = ($currentPrice - $avgCost) * $position['quantity'];
            $gainEur = $gainInTradeCurrency * $eurRate;

            if (abs($gainEur) < 0.005) {
                continue;
            }

            $refDate = new \DateTime($earliestTradeDate);
            $gains[$symbol] = [
                'symbol'            => $symbol,
                'gain_eur'          => $gainEur,
                'gain_percentage'   => $totalPortfolioEur > 0.005
                    ? ($gainEur / $totalPortfolioEur) * 100
                    : 0,
                'inception_label'   => 'since ' . $refDate->format("M 'y"),
                'inception_tooltip' => null,
            ];
        }

        $ranked = $this->_rankGains($gains);
        $portfolioTotalEur = array_sum(array_column($gains, 'gain_eur'));
        $ranked['portfolio_total_eur'] = $portfolioTotalEur;
        $ranked['portfolio_total_pct'] = $totalPortfolioEur > 0.005
            ? ($portfolioTotalEur / $totalPortfolioEur) * 100
            : 0;
        return $ranked;
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

        // Inject FMV prices for unlisted symbols (not returned by FinanceAPI).
        foreach ($positions as $symbol => $position) {
            if (!UnlistedSymbol::isUnlisted($symbol) || isset($quotes[$symbol])) {
                continue;
            }
            $price = UnlistedSymbol::getPrice($symbol, $currentDate);
            if ($price !== null) {
                $quotes[$symbol] = ['price' => $price, 'day_change' => 0];
            } else {
                Log::warning("MoversService: unlisted symbol {$symbol} has no FMV config, skipping");
            }
        }

        // Report symbols with no current quote once, up front. Each mover computation
        // below independently skips these (at DEBUG), so warning per-computation would
        // turn a single missing symbol into six or seven WARNING lines. Delisted and
        // unlisted symbols are expected to lack a live quote and are excluded here.
        $delistedSymbols = config('trades.delisted_symbols', []);
        $missingQuoteSymbols = [];
        foreach ($positions as $symbol => $position) {
            if (UnlistedSymbol::isUnlisted($symbol)
                || in_array($symbol, $delistedSymbols, true)
            ) {
                continue;
            }
            if (empty($quotes[$symbol]['price'])) {
                $missingQuoteSymbols[] = $symbol;
            }
        }
        if (!empty($missingQuoteSymbols)) {
            Log::warning('MoversService: no current quote for '
                . implode(', ', $missingQuoteSymbols)
                . '; excluded from movers and portfolio total');
        }

        $totalPortfolioEur = $this->_computeTotalPortfolioValueEur($positions, $quotes, $currentDate);

        // _computeTodayMovers resolves the session date itself (today, or the last completed session
        // when the user's markets have not opened yet) and sets date_label accordingly.
        $todayMovers = $this->_computeTodayMovers($positions, $quotes, $currentDate, $totalPortfolioEur);
        $todayMovers['total_portfolio_eur'] = $totalPortfolioEur;
        Cache::put($this->_getCacheKey($userId, 'today'), $todayMovers, self::CACHE_TTL_TODAY);

        $symbols = array_keys($positions);
        $referenceDates = [
            'weekly'  => (clone $currentDate)->modify('-7 days'),
            'monthly' => (clone $currentDate)->modify('-1 month'),
            'yearly'  => (clone $currentDate)->modify('-1 year'),
        ];

        foreach ($referenceDates as $period => $referenceDate) {
            $batchRefPrices = $this->_batchGetHistoricalPrices($symbols, $referenceDate);

            // Inject FMV reference prices for unlisted symbols.
            foreach ($positions as $symbol => $position) {
                if (!UnlistedSymbol::isUnlisted($symbol)) {
                    continue;
                }
                $refPrice = UnlistedSymbol::getPrice($symbol, $referenceDate);
                if ($refPrice !== null) {
                    $batchRefPrices[$symbol] = [
                        'unit_price' => $refPrice,
                        'date'       => $referenceDate->format('Y-m-d'),
                    ];
                } else {
                    Log::warning("MoversService: unlisted symbol {$symbol} has no FMV"
                        . " for reference date {$referenceDate->format('Y-m-d')}"
                        . " (period: {$period}), skipping");
                }
            }

            $splitAdjustedRefs = $this->_detectSplitsInPeriod($symbols, $referenceDate);
            $movers = $this->_computeHistoricalPeriodMovers(
                $positions, $quotes, $batchRefPrices, $splitAdjustedRefs,
                $currentDate, $totalPortfolioEur, $referenceDate
            );
            $movers['period_label'] = $this->_formatPeriodRange($referenceDate, $currentDate);
            $movers['total_portfolio_eur'] = $totalPortfolioEur;
            Cache::put($this->_getCacheKey($userId, $period), $movers, self::CACHE_TTL_HISTORICAL);
        }

        $alltimeMovers = $this->_computeAlltimeMovers($positions, $quotes, $currentDate, $totalPortfolioEur);
        $alltimeMovers['total_portfolio_eur'] = $totalPortfolioEur;
        Cache::put($this->_getCacheKey($userId, 'alltime'), $alltimeMovers, self::CACHE_TTL_HISTORICAL);
    }

    /**
     * Read movers for a user from cache for all five periods.
     * Returns: ['today' => ..., 'weekly' => ..., 'monthly' => ..., 'yearly' => ..., 'alltime' => ...]
     * Each value is ['losers' => [...], 'gainers' => [...]] or null when not yet cached.
     */
    public function getMovers(int $userId): array
    {
        return [
            'today'   => Cache::get($this->_getCacheKey($userId, 'today')),
            'weekly'  => Cache::get($this->_getCacheKey($userId, 'weekly')),
            'monthly' => Cache::get($this->_getCacheKey($userId, 'monthly')),
            'yearly'  => Cache::get($this->_getCacheKey($userId, 'yearly')),
            'alltime' => Cache::get($this->_getCacheKey($userId, 'alltime')),
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
