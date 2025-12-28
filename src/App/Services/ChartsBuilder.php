<?php

namespace ovidiuro\myfinance2\App\Services;

use Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChartsBuilder
{
    // Cache TTL: 120 seconds (2 minutes) - charts are regenerated every minute by cron
    // Cache is invalidated immediately when cron updates charts,
    //      so this serves as a fallback
    private const CHART_CACHE_TTL = 120;

    /**
     * Generate cache key for account chart data.
     *
     * @param int $userId User ID
     * @param int $accountId Account ID
     * @param string $metric Metric name (cost, change, mvalue, cash, changePercentage)
     * @return string Cache key
     */
    private static function _getCacheKeyForAccountChart(int $userId, int $accountId,
        string $metric): string
    {
        return "chart:account:{$userId}:{$accountId}:{$metric}";
    }

    /**
     * Generate cache key for user overview chart data.
     *
     * @param int $userId User ID
     * @param string $metric Metric name (cost, change, mvalue, cash, changePercentage)
     * @return string Cache key
     */
    private static function _getCacheKeyForUserChart(int $userId, string $metric): string
    {
        return "chart:user:{$userId}:{$metric}";
    }

    /**
     * Generate cache key for symbol chart data.
     *
     * @param string $symbol Symbol (e.g., 'EURUSD=X')
     * @return string Cache key
     */
    private static function _getCacheKeyForSymbolChart(string $symbol): string
    {
        return "chart:symbol:{$symbol}";
    }

    /**
     * Invalidate all cache entries for a specific account.
     *
     * @param int $userId User ID
     * @param int $accountId Account ID
     * @return void
     */
    private static function _invalidateAccountChartCache(int $userId, int $accountId): void
    {
        // Invalidate all metrics for this account
        foreach (array_keys(self::getAccountMetrics()) as $metric) {
            $cacheKey = self::_getCacheKeyForAccountChart($userId, $accountId, $metric);
            Cache::forget($cacheKey);
        }
        Log::debug("Invalidated account chart cache for user {$userId}, "
                   . "account {$accountId}");
    }

    /**
     * Invalidate all cache entries for user overview charts.
     *
     * @param int $userId User ID
     * @return void
     */
    private static function _invalidateUserChartCache(int $userId): void
    {
        // Invalidate all metrics for this user overview
        foreach (array_keys(self::getAccountMetrics()) as $metric) {
            $cacheKey = self::_getCacheKeyForUserChart($userId, $metric);
            Cache::forget($cacheKey);
        }
        Log::debug("Invalidated user overview chart cache for user {$userId}");
    }

    /**
     * Invalidate cache for a specific symbol.
     *
     * @param string $symbol Symbol to invalidate
     * @return void
     */
    private static function _invalidateSymbolChartCache(string $symbol): void
    {
        $cacheKey = self::_getCacheKeyForSymbolChart($symbol);
        Cache::forget($cacheKey);
        Log::debug("Invalidated symbol chart cache for {$symbol}");
    }

    /**
     * Check if current user has access to a resource.
     *
     * If an authenticated user exists (web request or test), verify they own the resource.
     * If no authenticated user (e.g., unattended cron job), skip the check.
     * Aborts with 403 if authenticated user doesn't own the resource.
     *
     * @param int $userId User ID to verify ownership
     * @param string $context Description of what's being accessed for error message
     * @return void
     */
    private static function checkUserAccess(int $userId, string $context = 'chart'): void
    {
        // If there's no authenticated user (unattended cron job), skip check
        if (!Auth::check()) {
            return;
        }

        // If authenticated user exists, verify they own this resource
        if (Auth::user()->id != $userId) {
            abort(403, "Access denied in ChartsBuilder: {$context}");
        }
    }

    private static function _checkUser(array $accountData): void
    {
        self::checkUserAccess($accountData['accountModel']->user_id, 'account chart');
    }

    private static function _checkUserForChartOverviewUser(int $userId): void
    {
        self::checkUserAccess($userId, 'user overview chart');
    }

    public static function getAccountMetrics(): array
    {
        return [
            'cash' => [
                'line_color' => 'rgba(255, 192, 0, 1)',
                'title' => 'Cash',
            ],
            'change' => [
                'line_color' => 'rgba(67, 83, 254, 1)',
                'title' => 'Change',
            ],
            'cost' => [
                'line_color' => 'rgba(38, 166, 154, 1)',
                'title' => 'Cost',
            ],
            'mvalue' => [
                'line_color' => 'rgba(239, 83, 80, 1)',
                'title' => 'Market Value',
            ],
            // changePercentage is a derived metric showing (change / cost) * 100
            // It uses a different scale (0-100% range) than the currency-based metrics,
            // so it's displayed on the left Y-axis while others use the right Y-axis.
            // This metric is calculated from cost and change data in FinanceApiCron.php
            'changePercentage' => [
                'line_color' => 'rgba(156, 39, 176, 1)',
                'title' => 'Change %',
            ],
        ];
    }

    public static function getOverviewUserMetricPath(int $userId, string $metric)
        :string
    {
        self::_checkUserForChartOverviewUser($userId);

        return 'charts' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR
               . $userId . DIRECTORY_SEPARATOR . 'overview' . DIRECTORY_SEPARATOR
               . $metric . '.json';
    }

    public static function getAccountMetricPath(array $accountData, string $metric)
        :string
    {
        self::_checkUser($accountData);
        $userId = $accountData['accountModel']->user_id;
        $accountId = $accountData['accountModel']->id;

        return 'charts' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR
               . $userId . DIRECTORY_SEPARATOR . $accountId . DIRECTORY_SEPARATOR
               . $metric . '.json';
    }

    public static function getSymbolPath(string $symbol)
        :string
    {
        return 'charts' . DIRECTORY_SEPARATOR . 'symbols' . DIRECTORY_SEPARATOR
               . $symbol . '.json';
    }

    public static function buildChartOverviewUser(int $userId,
        string $metric, array $stats)
    {
        self::_checkUserForChartOverviewUser($userId);

        $path = self::getOverviewUserMetricPath($userId, $metric);
        $contents = self::getOverviewStatsAsJsonString($stats);
        Storage::disk('local')->put($path, $contents);

        // Invalidate cache for this metric so updated data is fetched next time
        $cacheKey = self::_getCacheKeyForUserChart($userId, $metric);
        Cache::forget($cacheKey);
    }

    public static function buildChartAccount(array $accountData,
        string $metric, array $stats)
    {
        self::_checkUser($accountData);
        $path = self::getAccountMetricPath($accountData, $metric);
        $contents = self::getStatsAsJsonString($stats);
        Storage::disk('local')->put($path, $contents);

        // Invalidate cache for this metric so updated data is fetched next time
        $userId = $accountData['accountModel']->user_id;
        $accountId = $accountData['accountModel']->id;
        $cacheKey = self::_getCacheKeyForAccountChart($userId, $accountId, $metric);
        Cache::forget($cacheKey);
    }

    public static function buildChartSymbol(string $symbol, ?array $stats)
    {
        $path = self::getSymbolPath($symbol);
        $contents = self::getStatsAsJsonString($stats);
        Storage::disk('local')->put($path, $contents);

        // Invalidate cache for this symbol so updated data is fetched next time
        $cacheKey = self::_getCacheKeyForSymbolChart($symbol);
        Cache::forget($cacheKey);
    }

    /**
     * Generic batch write for chart files with cache invalidation.
     *
     * Reduces I/O by writing multiple metrics at once. Handles path generation,
     * content formatting, and cache invalidation via callables.
     *
     * @param array $metricsData Metrics to write: ['metric_name' => [...stats...], ...]
     * @param callable $pathGetter Gets path for a metric: fn($metric) => $path
     * @param callable $formatter Formats stats to JSON: fn($stats) => $json
     * @param callable $cacheInvalidator Invalidates cache: fn($metric) => void
     * @param string $logContext Context for debug logging
     * @param array $logData Additional data for logging
     * @return void
     */
    private static function buildChartBatch(
        array $metricsData,
        callable $pathGetter,
        callable $formatter,
        callable $cacheInvalidator,
        string $logContext,
        array $logData = []
    ): void {
        $disk = Storage::disk('local');
        $filesToWrite = [];

        foreach ($metricsData as $metric => $stats) {
            $path = $pathGetter($metric);
            $contents = $formatter($stats);
            $filesToWrite[$path] = $contents;
        }

        // Write all files at once
        foreach ($filesToWrite as $path => $contents) {
            $disk->put($path, $contents);
        }

        // Invalidate cache for all metrics
        foreach (array_keys($metricsData) as $metric) {
            $cacheInvalidator($metric);
        }

        Log::debug("{$logContext} charts batch written", array_merge([
            'metrics_count' => count($metricsData),
            'files_written' => count($filesToWrite),
        ], $logData));
    }

    /**
     * Batch write multiple metric charts for an account.
     *
     * @param array $accountData Account data with user_id and accountModel
     * @param array $metricsData Metrics: ['metric_name' => [...stats...], ...]
     * @return void
     */
    public static function buildChartAccountBatch(array $accountData, array $metricsData)
        : void
    {
        self::_checkUser($accountData);

        $userId = $accountData['accountModel']->user_id;
        $accountId = $accountData['accountModel']->id;

        self::buildChartBatch(
            $metricsData,
            fn($metric) => self::getAccountMetricPath($accountData, $metric),
            fn($stats)  => self::getStatsAsJsonString($stats),
            fn($metric) => Cache::forget(
                self::_getCacheKeyForAccountChart($userId, $accountId, $metric)),
            'Account',
            ['account_id' => $accountId]
        );
    }

    /**
     * Batch write multiple metric charts for a user overview.
     *
     * @param int $userId User ID
     * @param array $metricsData Metrics: ['metric_name' => [...stats...], ...]
     * @return void
     */
    public static function buildChartOverviewUserBatch(int $userId, array $metricsData)
        : void
    {
        self::_checkUserForChartOverviewUser($userId);

        self::buildChartBatch(
            $metricsData,
            fn($metric) => self::getOverviewUserMetricPath($userId, $metric),
            fn($stats) => self::getOverviewStatsAsJsonString($stats),
            fn($metric) => Cache::forget(self::_getCacheKeyForUserChart($userId, $metric)),
            'User overview',
            ['user_id' => $userId]
        );
    }

    /**
     * Retrieve chart data as JSON string with caching.
     *
     * Generic getter that reads from disk with in-memory caching.
     * Falls back to empty array if file doesn't exist.
     *
     * @param string $cacheKey Cache key for this chart
     * @param callable $pathGetter Callable that returns the file path
     * @return string JSON array or empty array if file missing
     */
    private static function getChartDataCached(string $cacheKey, callable $pathGetter)
        : string
    {
        return Cache::remember($cacheKey, self::CHART_CACHE_TTL,
            function () use ($pathGetter)
        {
            $path = $pathGetter();
            return Storage::disk('local')->exists($path)
                ? Storage::disk('local')->get($path)
                : '[]';
        });
    }

    public static function getChartOverviewUserAsJsonString(int $userId,
        string $metric): string
    {
        self::_checkUserForChartOverviewUser($userId);
        $cacheKey = self::_getCacheKeyForUserChart($userId, $metric);
        return self::getChartDataCached($cacheKey,
            fn() => self::getOverviewUserMetricPath($userId, $metric));
    }

    public static function getChartAccountAsJsonString(array $accountData,
        string $metric): string
    {
        self::_checkUser($accountData);
        $userId = $accountData['accountModel']->user_id;
        $accountId = $accountData['accountModel']->id;
        $cacheKey = self::_getCacheKeyForAccountChart($userId, $accountId, $metric);
        return self::getChartDataCached($cacheKey,
            fn() => self::getAccountMetricPath($accountData, $metric));
    }

    public static function getChartSymbolAsJsonString(string $symbol): string
    {
        $cacheKey = self::_getCacheKeyForSymbolChart($symbol);
        return self::getChartDataCached($cacheKey,
            fn() => self::getSymbolPath($symbol));
    }

    /**
     * Get the opposite currency for a given currency.
     * Handles EUR ↔ USD, plus GBX → EUR mapping.
     *
     * @param string $currency Source currency code
     * @return string|null Opposite currency, or null if mapping undefined
     */
    private static function getOppositeCurrency(string $currency): ?string
    {
        return match($currency) {
            'EUR' => 'USD',
            'USD' => 'EUR',
            'GBX' => 'EUR',
            default => null,
        };
    }

    /**
     * Convert account stats to opposite currency.
     *
     * @param array $accountData Account data with currency info
     * @param string $metric Metric name
     * @param array $stats Stats to convert
     * @return array [convertedMetricName, convertedStats]
     */
    public static function convertAccountStatsToCurrency(array $accountData,
        string $metric, array $stats): array
    {
        self::_checkUser($accountData);
        $accountCurrency = $accountData['accountModel']->currency->iso_code;
        $convertedCurrency = self::getOppositeCurrency($accountCurrency);

        if ($convertedCurrency === null) {
            Log::error("Unexpected account currency: {$accountCurrency}");
            return null;
        }

        return [
            $metric . '_' . $convertedCurrency,
            Stats::convertStatsToCurrency($stats, $convertedCurrency)
        ];
    }

    /**
     * Convert position stats to opposite currency.
     *
     * @param array $position Position data with trade currency info
     * @param array|null $stats Stats to convert
     * @return array [convertedSymbolName, convertedStats]
     */
    public static function convertPositionStatsToCurrency(array $position,
        ?array $stats): array
    {
        $symbolCurrency = $position['tradeCurrencyModel']->iso_code;
        $convertedCurrency = self::getOppositeCurrency($symbolCurrency);

        if ($convertedCurrency === null) {
            Log::error("Unexpected symbol currency: {$symbolCurrency}");
            return null;
        }

        return [
            $position['symbol'] . '_' . $convertedCurrency,
            Stats::convertStatsToCurrency($stats, $convertedCurrency)
        ];
    }

    /**
     * Validate that metric data exists before attempting to retrieve it.
     *
     * Checks if the chart file for a given metric exists and logs a warning if missing.
     * Useful for detecting if chart data wasn't properly generated by the cron job.
     *
     * @param array $accountData Account data array
     * @param string $metric Metric name (e.g., 'cost', 'changePercentage')
     * @return bool True if file exists, false otherwise
     */
    public static function validateAccountChartExists(array $accountData, string $metric)
        : bool
    {
        $path = self::getAccountMetricPath($accountData, $metric);

        if (!Storage::disk('local')->exists($path)) {
            \Log::warning('Chart file missing for account metric', [
                'user_id' => $accountData['accountModel']->user_id ?? null,
                'account_id' => $accountData['accountModel']->id ?? null,
                'metric' => $metric,
                'path' => $path,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Validate that metric data exists for a user overview chart.
     *
     * Checks if the user overview chart file exists and logs a warning if missing.
     *
     * @param int $userId User ID
     * @param string $metric Metric name
     * @return bool True if file exists, false otherwise
     */
    public static function validateUserChartExists(int $userId, string $metric): bool
    {
        $path = self::getOverviewUserMetricPath($userId, $metric);

        if (!Storage::disk('local')->exists($path)) {
            \Log::warning('Chart file missing for user metric', [
                'user_id' => $userId,
                'metric' => $metric,
                'path' => $path,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Convert stats to JavaScript object literal format.
     *
     * Handles both structured stats (with 'historical' and 'today_last' keys)
     * and flat date-indexed arrays. Output: [{ time: '...', value: ... }, ...]
     *
     * @param array|null $stats Stats array (structured or flat)
     * @param bool $isFlat True if stats are flat date-indexed, false if structured
     * @return string JavaScript array of objects
     */
    private static function formatStatsAsJson(?array $stats, bool $isFlat = false): string
    {
        if (empty($stats)) {
            return '[]';
        }

        if ($isFlat) {
            $points = [];
            foreach ($stats as $date => $stat) {
                $points[] = "{ time: '" . $date . "', value: " . $stat['unit_price'] . "}";
            }
            return '[' . implode(',', $points) . ']';
        }

        // Structured format (historical + today_last)
        $points = [];
        $todayDateFormat = date(trans('myfinance2::general.date-format'));

        if (!empty($stats['historical']) && is_array($stats['historical'])) {
            foreach ($stats['historical'] as $stat) {
                if (empty($stat['date'])) {
                    continue;
                }
                if ($stat['date'] === $todayDateFormat && !empty($stats['today_last'])) {
                    continue; // Skip historical for today if we have more recent data
                }
                $points[] = "{ time: '" . $stat['date'] . "', value: "
                            . $stat['unit_price'] . "}";
            }
        }

        if (!empty($stats['today_last'])) {
            $points[] = "{ time: '" . $todayDateFormat . "', value: "
                        . $stats['today_last']['unit_price'] . "}";
        }

        return '[' . implode(',', $points) . ']';
    }

    public static function getStatsAsJsonString(?array $stats): string
    {
        return self::formatStatsAsJson($stats, isFlat: false);
    }

    public static function getOverviewStatsAsJsonString(array $stats): string
    {
        return self::formatStatsAsJson($stats, isFlat: true);
    }
}

