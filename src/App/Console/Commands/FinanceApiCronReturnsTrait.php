<?php

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\Returns\Returns;
use ovidiuro\myfinance2\App\Services\Returns\ReturnsConstants;

/**
 * Trait for returns refresh operations
 */
trait FinanceApiCronReturnsTrait
{
    /**
     * Refresh returns data for all years
     *
     * Iterates through all years from MIN_YEAR to current year and refreshes returns data.
     * For past years: checks if cache exists, if yes skips, if no executes the flow
     * For current year: clears cache and executes the flow
     * With --force flag: clears all cache markers and refreshes all years
     */
    public function refreshReturns(): void
    {
        $force = $this->option('force');
        Log::info(
            'START app:finance-api-cron refreshReturns()'
            . ($force ? ' (--force mode)' : '')
        );

        $currentYear = (int) date('Y');
        $startYear = ReturnsConstants::MIN_YEAR;

        // If --force flag is set, clear all year cache markers and underlying cache data
        if ($force) {
            Log::info('Force mode enabled: clearing all returns cache for all years');
            for ($year = $startYear; $year <= $currentYear; $year++) {
                $this->_clearReturnsCacheForYear($year);
                $this->_clearYearCache($year);
            }
        }

        $totalYearsProcessed = 0;
        $totalYearsSkipped = 0;

        for ($year = $startYear; $year <= $currentYear; $year++) {
            $isCachedMarker = $this->_getYearCacheMarker($year);

            if ($year === $currentYear) {
                // Current year: Clear cache and refresh to force fresh API calls
                Log::info("Processing year $year (current year)");
                $this->_clearReturnsCacheForYear($year);
                $this->_clearYearCache($year);
                $success = $this->_executeReturnsFlow($year);
                if ($success) {
                    $this->_setYearCacheMarker($year);
                    $totalYearsProcessed++;
                } else {
                    Log::warning("Failed to process year $year, not setting cache marker");
                }
            } else {
                // Past year: Check if cache exists
                if ($isCachedMarker) {
                    // Cache exists and is valid, skip
                    Log::info("Cache is still valid for year $year, not refreshing it");
                    $totalYearsSkipped++;
                } else {
                    // Cache doesn't exist or expired, execute flow
                    Log::info("Cache not found for year $year, executing flow");
                    $success = $this->_executeReturnsFlow($year);
                    if ($success) {
                        $this->_setYearCacheMarker($year);
                        $totalYearsProcessed++;
                    } else {
                        Log::warning("Failed to process year $year, not setting cache marker");
                    }
                }
            }
        }

        Log::info(
            "END app:finance-api-cron refreshReturns() => "
            . "$totalYearsProcessed years processed, $totalYearsSkipped years skipped"
        );
    }

    /**
     * Execute the returns flow for a specific year
     *
     * @param int $year The year to process
     * @return bool True if successful, false if failed
     */
    private function _executeReturnsFlow(int $year): bool
    {
        try {
            $service = new Returns();

            // Execute the returns calculation - this will auto-cache the data
            $returnsData = $service->handle($year);

            $excludedKeys = [
                'totalReturnEUR',
                'totalReturnUSD',
                'totalReturnEURFormatted',
                'totalReturnUSDFormatted',
            ];
            $accountCount = count(array_filter(
                array_keys($returnsData),
                fn($key) => !in_array($key, $excludedKeys)
            ));

            Log::info(
                "Returns flow executed for year $year => $accountCount accounts processed"
            );
            return true;
        } catch (\Exception $e) {
            Log::error(
                "Failed to execute returns flow for year $year: "
                . $e->getMessage() . " | " . $e->getFile() . ":" . $e->getLine()
                . " | Stack trace: " . $e->getTraceAsString()
            );
            return false;
        }
    }

    /**
     * Get cache marker indicating if a year has been cached
     *
     * @param int $year The year to check
     * @return bool True if cached, false otherwise
     */
    private function _getYearCacheMarker(int $year): bool
    {
        $cacheKey = 'returns_year_' . $year . '_complete';
        return Cache::has($cacheKey);
    }

    /**
     * Set cache marker to indicate a year has been cached
     *
     * @param int $year The year to mark as cached
     */
    private function _setYearCacheMarker(int $year): void
    {
        $cacheKey = 'returns_year_' . $year . '_complete';

        // Use the same TTL as the returns data
        $currentYear = (int) date('Y');
        $ttl = ($year < $currentYear)
            ? ReturnsConstants::QUOTE_CACHE_TTL_PAST_YEARS
            : ReturnsConstants::QUOTE_CACHE_TTL_CURRENT_YEAR;

        Cache::put($cacheKey, true, $ttl);
    }

    /**
     * Clear the cache marker for a specific year
     *
     * @param int $year The year to clear cache marker for
     */
    private function _clearYearCache(int $year): void
    {
        $cacheKey = 'returns_year_' . $year . '_complete';
        Cache::forget($cacheKey);
    }

    /**
     * Clear returns cache for a specific year
     * Clears quote and exchange rate cache entries for all symbols and dates in the year
     *
     * @param int $year The year to clear cache for
     */
    private function _clearReturnsCacheForYear(int $year): void
    {
        Log::info("Clearing returns cache for year $year");

        // Get all used symbols
        $symbols = $this->getAllUsedSymbols();

        // Get all currency pairs for exchange rates
        $connection = config('myfinance2.db_connection');
        $result = \DB::connection($connection)->select("
            SELECT ac.iso_code AS account_currency_iso_code,
                tc.iso_code AS trade_currency_iso_code
            FROM trades t
            LEFT OUTER JOIN accounts a ON t.account_id = a.id
            LEFT OUTER JOIN currencies ac ON a.currency_id = ac.id
            LEFT OUTER JOIN currencies tc ON t.trade_currency_id = tc.id
            WHERE ac.iso_code <> tc.iso_code
            GROUP BY 1, 2
        ");

        $currenciesReverseMapping = config('general.currencies_reverse_mapping');
        $currencyPairs = [];
        foreach ($result as $resultItem) {
            $pair = [
                $resultItem->account_currency_iso_code,
                $resultItem->trade_currency_iso_code,
            ];
            if (!empty($currenciesReverseMapping[$pair[0]])) {
                $pair[0] = $currenciesReverseMapping[$pair[0]];
            }
            if (!empty($currenciesReverseMapping[$pair[1]])) {
                $pair[1] = $currenciesReverseMapping[$pair[1]];
            }
            $currencyPairs[] = $pair[0] . $pair[1] . '=X';
        }

        // Add common EUR/USD pair
        $currencyPairs[] = 'EURUSD=X';
        $currencyPairs = array_unique($currencyPairs);

        // Clear cache for all dates in the year
        $start = new \DateTime("$year-01-01");
        $end = new \DateTime("$year-12-31");
        $clearedKeys = 0;

        while ($start <= $end) {
            $dateStr = $start->format('Y-m-d');

            // Clear quote cache for all symbols
            foreach ($symbols as $symbol) {
                $cacheKey = 'returns_stat_' . $symbol . '_' . $dateStr;
                if (Cache::has($cacheKey)) {
                    Cache::forget($cacheKey);
                    $clearedKeys++;
                }
            }

            // Clear exchange rate cache for all currency pairs
            foreach ($currencyPairs as $pair) {
                $cacheKey = 'returns_exchange_rate_' . $pair . '_' . $dateStr;
                if (Cache::has($cacheKey)) {
                    Cache::forget($cacheKey);
                    $clearedKeys++;
                }
            }

            $start->modify('+1 day');
        }

        Log::info("Cleared $clearedKeys cache entries for year $year");
    }
}

