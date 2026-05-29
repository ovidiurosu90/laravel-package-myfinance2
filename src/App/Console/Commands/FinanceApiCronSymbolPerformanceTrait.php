<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Services\DrawdownService;
use ovidiuro\myfinance2\App\Services\CategorizationService;
use ovidiuro\myfinance2\App\Services\SymbolPerformanceService;
use ovidiuro\myfinance2\App\Services\TechnicalIndicatorsService;

/**
 * Trait for symbol performance pre-computation operations
 */
trait FinanceApiCronSymbolPerformanceTrait
{
    /**
     * Pre-compute and cache symbol performance data for all users.
     *
     * Clears the existing cache and rebuilds it so the watchlist-symbols page
     * loads instantly without computing gains on the fly.
     */
    public function refreshSymbolPerformance(): void
    {
        Log::info('START app:finance-api-cron refreshSymbolPerformance()');

        try {
            $userIds = DB::table('users')->pluck('id');
            $processed = 0;

            foreach ($userIds as $userId) {
                SymbolPerformanceService::clearCache((int) $userId);
                DrawdownService::clearCache((int) $userId);
                CategorizationService::clearCache((int) $userId);
                (new SymbolPerformanceService())->handle((int) $userId);
                $processed++;
                Log::info("Symbol performance built for user {$userId}");
            }

            $this->refreshSymbolSectors();
            $this->refreshCategorization();
            $this->_refreshAnalystCache();

            Log::info(
                "END app:finance-api-cron refreshSymbolPerformance() => "
                . "{$processed} users processed"
            );
        } catch (\Exception $e) {
            Log::error(
                'Failed to refresh symbol performance: ' . $e->getMessage()
                . ' | ' . $e->getFile() . ':' . $e->getLine()
            );
        }
    }

    private function _refreshAnalystCache(): void
    {
        Log::info('START _refreshAnalystCache()');

        $tradeSymbols     = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->distinct()->pluck('symbol')->toArray();
        $watchlistSymbols = WatchlistSymbol::withoutGlobalScope(AssignedToUserScope::class)
            ->distinct()->pluck('symbol')->toArray();
        $symbols = array_values(array_unique(array_merge($tradeSymbols, $watchlistSymbols)));
        $symbols = array_values(array_filter($symbols, function (string $symbol): bool {
            return !FinanceAPI::isSkippedSymbol($symbol);
        }));

        (new TechnicalIndicatorsService())->preWarmAnalystCache($symbols);

        Log::info('END _refreshAnalystCache() => ' . count($symbols) . ' symbols processed');
    }

    private const SECTOR_REFRESH_GUARD_PREFIX = 'SECTOR_DAILY_';
    private const SECTOR_REFRESH_GUARD_TTL   = 86400; // 1 day

    private function refreshSymbolSectors(): void
    {
        Log::info('START refreshSymbolSectors()');

        $tradeSymbols    = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->distinct()->pluck('symbol')->toArray();
        $watchlistSymbols = WatchlistSymbol::withoutGlobalScope(AssignedToUserScope::class)
            ->distinct()->pluck('symbol')->toArray();
        $symbols = array_values(array_unique(array_merge($tradeSymbols, $watchlistSymbols)));

        $symbols = array_values(array_filter($symbols, function (string $symbol): bool {
            return !str_ends_with($symbol, '=X') && !FinanceAPI::isSkippedSymbol($symbol);
        }));

        $financeApi = new FinanceAPI();
        $fetched = 0;
        $skipped = 0;

        foreach ($symbols as $symbol) {
            if (Cache::has(self::SECTOR_REFRESH_GUARD_PREFIX . $symbol)) {
                $skipped++;
                continue;
            }
            $sector = $financeApi->fetchAndCacheSector($symbol);
            Log::info("Sector for {$symbol}: " . ($sector ?? 'n/a'));
            if ($sector !== null) {
                Cache::put(self::SECTOR_REFRESH_GUARD_PREFIX . $symbol, true, self::SECTOR_REFRESH_GUARD_TTL);
                $fetched++;
            }
        }

        $summary = "{$fetched}/" . count($symbols) . " sectors fetched";
        if ($skipped > 0) {
            $summary .= ", {$skipped} skipped (cache fresh < 1d)";
        }
        Log::info("END refreshSymbolSectors() => {$summary}");
    }
}
