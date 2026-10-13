<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Everything the as-of tier engine needs for one user, loaded once and answered in memory for any
 * past day D: the trade and dividend ledgers, the daily closes of every traded and watchlist symbol
 * (plus the EUR FX series), and the watchlist. Lets the backfill replay hundreds of weekdays without
 * re-querying the database per day.
 *
 * Prices load from (first day - PRICE_PADDING_DAYS) so a 1Y market return can be measured on the
 * first day. Where stored history starts later, HistoricalPriceCache fills the gap (cached, never
 * persisted), the same way DrawdownService does; pass $fetchGaps = false to skip that.
 */
final class PerformanceDataset
{
    // A year of reference prices for the 1Y market return, plus a margin for holidays.
    public const PRICE_PADDING_DAYS = 400;

    private Collection $_trades;
    private Collection $_dividends;
    private array $_watchlist = [];
    // symbol => [dates asc], symbol => [date => ['price' => float, 'currency' => string]]
    private array $_priceDates = [];
    private array $_prices = [];
    // currency => [dates asc], currency => [date => EUR per unit of currency]
    private array $_fxDates = [];
    private array $_fx = [];
    private array $_latestEurRates = ['EUR' => 1.0];
    private array $_momentumSeries = [];
    private string $_priceFrom;
    private array $_gapFilled = [];

    public function __construct(
        private readonly int $_userId,
        Carbon $firstDay,
        bool $fetchGaps = true,
        ?string $onlySymbol = null
    )
    {
        $this->_priceFrom = $firstDay->copy()->subDays(self::PRICE_PADDING_DAYS)->format('Y-m-d');
        $this->_trades    = $this->_loadTrades($onlySymbol);
        $this->_dividends = $this->_loadDividends($onlySymbol);
        $this->_watchlist = $this->_loadWatchlist($onlySymbol);

        $this->_loadFx();
        $this->_loadPrices($this->allSymbols());
        if ($fetchGaps) {
            $this->_fillGapsFromApiCache($this->allSymbols());
        }
    }

    public function userId(): int
    {
        return $this->_userId;
    }

    /**
     * Traded symbols plus current watchlist symbols, excluding unlisted (manual fair value) ones,
     * which have no price history.
     *
     * @return string[]
     */
    public function allSymbols(): array
    {
        $symbols = array_unique(array_merge(
            $this->_trades->pluck('symbol')->unique()->values()->all(),
            $this->_watchlist
        ));

        return array_values(array_filter($symbols, fn ($s) => !UnlistedSymbol::isUnlisted((string) $s)));
    }

    /**
     * @return string[]
     */
    public function watchlistSymbols(): array
    {
        return $this->_watchlist;
    }

    /**
     * Symbols whose stored history did not reach back far enough and were filled from the API cache.
     *
     * @return string[]
     */
    public function gapFilledSymbols(): array
    {
        return array_keys($this->_gapFilled);
    }

    public function tradesUpTo(Carbon $day): Collection
    {
        $end = $day->copy()->endOfDay();

        return $this->_trades->filter(fn ($t) => $t->timestamp <= $end)->values();
    }

    public function dividendsUpTo(Carbon $day): Collection
    {
        $end = $day->copy()->endOfDay();

        return $this->_dividends->filter(fn ($d) => $d->timestamp <= $end)->values();
    }

    public function hasPrices(string $symbol): bool
    {
        return !empty($this->_priceDates[$symbol]);
    }

    /**
     * Whether the symbol has a close on exactly $day, i.e. its exchange traded that day (weekends and
     * the exchange's holidays have none).
     */
    public function hasPriceOn(string $symbol, Carbon $day): bool
    {
        return isset($this->_prices[$symbol][$day->format('Y-m-d')]);
    }

    /**
     * Last stored close on or before $day: ['price' => float, 'currency' => string] or null.
     */
    public function priceOnOrBefore(string $symbol, Carbon $day): ?array
    {
        $date = $this->_lastOnOrBefore($this->_priceDates[$symbol] ?? [], $day->format('Y-m-d'));

        return $date !== null ? $this->_prices[$symbol][$date] : null;
    }

    /**
     * EUR conversion rates (EUR per unit of currency) as they stood on $day: the latest FX close on
     * or before it, falling back to the earliest stored close when the series starts later. Pence
     * (GBp / GBX) are a hundredth of GBP, as in SymbolPerformanceService::_loadEurRates().
     */
    public function eurRatesOn(Carbon $day): array
    {
        $dayStr = $day->format('Y-m-d');
        $rates  = ['EUR' => 1.0];
        foreach ($this->_fxDates as $currency => $dates) {
            $date = $this->_lastOnOrBefore($dates, $dayStr) ?? $dates[0];
            $rates[$currency] = $this->_fx[$currency][$date];
        }
        $rates['GBP'] ??= 1.0;
        $rates['GBp'] = $rates['GBP'] / 100.0;
        $rates['GBX'] = $rates['GBP'] / 100.0;

        return $rates;
    }

    /**
     * date => EUR price, converted with the latest FX rate per row currency exactly like
     * DrawdownService::_loadHistoricalPrices(), so momentumAsOf() reproduces the live 1Y return.
     */
    public function momentumSeries(string $symbol): array
    {
        if (!isset($this->_momentumSeries[$symbol])) {
            $series = [];
            foreach ($this->_prices[$symbol] ?? [] as $date => $row) {
                $series[$date] = $row['price'] * ($this->_latestEurRates[$row['currency']] ?? 1.0);
            }
            $this->_momentumSeries[$symbol] = $series;
        }

        return $this->_momentumSeries[$symbol];
    }

    private function _loadTrades(?string $onlySymbol): Collection
    {
        return Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $this->_userId)
            ->when($onlySymbol !== null, fn ($q) => $q->where('symbol', $onlySymbol))
            ->with(['accountModel', 'accountModel.currency', 'tradeCurrencyModel'])
            ->orderBy('timestamp')
            ->get();
    }

    private function _loadDividends(?string $onlySymbol): Collection
    {
        return Dividend::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $this->_userId)
            ->when($onlySymbol !== null, fn ($q) => $q->where('symbol', $onlySymbol))
            ->with(['accountModel', 'accountModel.currency', 'dividendCurrencyModel'])
            ->orderBy('timestamp')
            ->get();
    }

    private function _loadWatchlist(?string $onlySymbol): array
    {
        return WatchlistSymbol::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $this->_userId)
            ->when($onlySymbol !== null, fn ($q) => $q->where('symbol', $onlySymbol))
            ->pluck('symbol')
            ->unique()
            ->values()
            ->all();
    }

    private function _loadFx(): void
    {
        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', 'LIKE', 'EUR%=X')
            ->select('symbol', 'date', 'unit_price')
            ->orderBy('date')
            ->get();

        foreach ($rows as $row) {
            $currency = substr($row->symbol, 3, 3);
            if (strlen($currency) !== 3 || $currency === 'EUR' || (float) $row->unit_price <= 0.0) {
                continue;
            }
            $date = $this->_dateStr($row->date);
            $rate = 1.0 / (float) $row->unit_price;
            $this->_fx[$currency][$date] = $rate;
            $this->_latestEurRates[$currency] = $rate;
        }
        foreach ($this->_fx as $currency => $byDate) {
            ksort($byDate);
            $this->_fx[$currency]      = $byDate;
            $this->_fxDates[$currency] = array_keys($byDate);
        }
        $this->_latestEurRates['GBP'] ??= 1.0;
        $this->_latestEurRates['GBp'] = $this->_latestEurRates['GBP'] / 100.0;
        $this->_latestEurRates['GBX'] = $this->_latestEurRates['GBP'] / 100.0;
    }

    private function _loadPrices(array $symbols): void
    {
        if (empty($symbols)) {
            return;
        }
        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->where('date', '>=', $this->_priceFrom)
            ->select('symbol', 'date', 'unit_price', 'currency_iso_code')
            ->orderBy('date')
            ->get();

        foreach ($rows as $row) {
            $this->_prices[$row->symbol][$this->_dateStr($row->date)] = [
                'price'    => (float) $row->unit_price,
                'currency' => $row->currency_iso_code,
            ];
        }
        foreach ($this->_prices as $symbol => $byDate) {
            $this->_priceDates[$symbol] = array_keys($byDate);
        }
    }

    /**
     * Same gap rule as DrawdownService::_fillGapsFromApiCache(): when the stored history starts after
     * the first needed day, fetch the missing span (cached for 7 days, never written to the DB).
     */
    private function _fillGapsFromApiCache(array $symbols): void
    {
        $cache = new HistoricalPriceCache();
        foreach ($symbols as $symbol) {
            $dbEarliest = $this->_priceDates[$symbol][0] ?? null;
            if ($dbEarliest !== null && $dbEarliest <= $this->_priceFrom) {
                continue;
            }
            $gapTo = $dbEarliest !== null
                ? Carbon::parse($dbEarliest)->subDay()->format('Y-m-d')
                : Carbon::today()->format('Y-m-d');
            if ($gapTo < $this->_priceFrom) {
                continue;
            }

            $fetched = $cache->fetch($symbol, $this->_priceFrom, $gapTo);
            if (empty($fetched['prices']) || empty($fetched['currency'])) {
                continue;
            }
            foreach ($fetched['prices'] as $date => $price) {
                if ($date >= $this->_priceFrom && $date <= $gapTo) {
                    $this->_prices[$symbol][$date] = ['price' => (float) $price, 'currency' => $fetched['currency']];
                }
            }
            ksort($this->_prices[$symbol]);
            $this->_priceDates[$symbol] = array_keys($this->_prices[$symbol]);
            $this->_gapFilled[$symbol]  = true;
            unset($this->_momentumSeries[$symbol]);
        }
    }

    /**
     * Binary search for the last date <= $day in an ascending list of Y-m-d strings.
     */
    private function _lastOnOrBefore(array $dates, string $day): ?string
    {
        $lo   = 0;
        $hi   = count($dates) - 1;
        $best = null;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($dates[$mid] <= $day) {
                $best = $dates[$mid];
                $lo   = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $best;
    }

    private function _dateStr(mixed $date): string
    {
        return is_string($date) ? substr($date, 0, 10) : $date->format('Y-m-d');
    }
}
