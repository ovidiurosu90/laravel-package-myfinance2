<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Today's tier reading of every symbol of the signed-in user, from the /watchlist-symbols pipeline
 * (WatchlistSymbolsDashboard::handle(), so the order pages show exactly what the watchlist row shows), reduced to
 * what the order pages need: per symbol the reading (TierReadingBuilder), the warnings' facts and the position
 * figures behind the share of the portfolio; per user the stale markets.
 *
 * The pipeline takes about as long as a /watchlist-symbols load, so the result is kept per user for a few minutes
 * (watchlistsymbols.order_warnings.live_cache_seconds): the banner, the save and the re-check then read the same
 * figures. Cleared when a trade, dividend or split changes (InvalidatesSymbolPerformanceCache) and when a tier
 * override is saved.
 *
 * Resolved from the container, so feature tests can bind a fake instead of running the pipeline.
 */
class LiveTierReadings
{
    private const CACHE_PREFIX = 'myfinance2.live_tier_readings.u';

    /**
     * @return array{taken_at: string, as_of: string, stale: array<string, string>, symbols: array<string, array{
     *     reading: array, facts: array, position: array}>}
     */
    public function forUser(int $userId): array
    {
        $seconds = (int) config('watchlistsymbols.order_warnings.live_cache_seconds', 180);
        if ($seconds <= 0) {
            return $this->build();
        }

        return Cache::remember(self::CACHE_PREFIX . $userId, $seconds, fn () => $this->build());
    }

    /**
     * Whether a symbol's live quote is stale (its market open but the price frozen): how old, or null.
     */
    public function staleAge(int $userId, string $symbol): ?string
    {
        return $this->forUser($userId)['stale'][$symbol] ?? null;
    }

    public static function clearCache(int $userId): void
    {
        Cache::forget(self::CACHE_PREFIX . $userId);
    }

    /**
     * Runs the watchlist pipeline for the signed-in user.
     */
    protected function build(): array
    {
        $dashboard = (new WatchlistSymbolsDashboard())->handle();

        return self::fromDashboard($dashboard, NearPeakReading::fromConfig());
    }

    /**
     * The per-symbol readings out of a WatchlistSymbolsDashboard::handle() result.
     */
    public static function fromDashboard(array $dashboard, NearPeakReading $nearPeak): array
    {
        $health   = $dashboard['health_score'] ?? null;
        $eurRates = $dashboard['eurRates'] ?? [];
        $index    = [];
        foreach (['platinum_gold_symbols', 'silver_symbols', 'bronze_rust_symbols', 'unrated_symbols'] as $group) {
            foreach ($health[$group] ?? [] as $row) {
                $index[$row['symbol']] = $row;
            }
        }

        $symbols = [];
        foreach ($dashboard['items'] ?? [] as $symbol => $item) {
            $cat = $item['categorization'] ?? null;
            if ($cat === null) {
                continue;
            }
            $symbol  = (string) $symbol;
            $reading = TierReadingBuilder::fromCategorization(
                $symbol, $cat, $index[$symbol] ?? null, $health['mvalue_color'] ?? null
            );
            $currency = $item['currency'] ?? null;
            $symbols[$symbol] = [
                'reading'  => $reading,
                'facts'    => TierReadingBuilder::facts($symbol, $cat, $reading['cells'], $health, $nearPeak),
                'position' => [
                    'mvalue_eur'       => (float) ($index[$symbol]['mvalue_eur'] ?? 0.0),
                    'total_mvalue_eur' => isset($health['total_mvalue_eur']) ? (float) $health['total_mvalue_eur']
                        : null,
                    'price_native'     => isset($item['price']) ? (float) $item['price'] : null,
                    'currency'         => $currency,
                    'eur_rate'         => $currency !== null && isset($eurRates[$currency])
                        ? (float) $eurRates[$currency] : null,
                ],
            ];
        }

        $stale = [];
        foreach ($dashboard['staleQuotes'] ?? [] as $market) {
            foreach ($market['symbols'] ?? [] as $symbol) {
                $stale[$symbol] = (string) ($market['age_human'] ?? '');
            }
        }

        return [
            'taken_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'as_of'    => Carbon::today()->format('Y-m-d'),
            'stale'    => $stale,
            'symbols'  => $symbols,
        ];
    }
}
