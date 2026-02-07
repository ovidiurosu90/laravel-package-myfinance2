<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\Returns\Returns;
use ovidiuro\myfinance2\App\Services\Returns\ReturnsAlerts;
use ovidiuro\myfinance2\App\Services\Returns\ReturnsConstants;
use ovidiuro\myfinance2\App\Services\Returns\ReturnsOverview;
use ovidiuro\myfinance2\App\Services\Returns\ReturnsViewTransformer;

class ReturnsController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the returns dashboard
     *
     * @return \Illuminate\View\View
     */
    public function index(): \Illuminate\View\View
    {
        // Get year from query parameter, default to current year
        $year = (int) request()->input('year', date('Y'));

        // Validate year range (MIN_YEAR to current year)
        $currentYear = (int) date('Y');
        if ($year < ReturnsConstants::MIN_YEAR || $year > $currentYear) {
            $year = $currentYear;
        }

        // Get currency from query parameter, default to EUR
        $currency = request()->input('currency_iso_code', 'EUR');
        if (!in_array($currency, ['EUR', 'USD'])) {
            $currency = 'EUR';
        }

        $service = new Returns();

        // Calculate returns for the selected year
        $serviceData = $service->handle($year);

        // Extract metadata from service response
        $totalReturnEUR = $serviceData['totalReturnEUR'] ?? 0;
        $totalReturnUSD = $serviceData['totalReturnUSD'] ?? 0;

        // Create colored versions for initial display
        $totalReturnEURColored = MoneyFormat::get_formatted_gain('€', $totalReturnEUR);
        $totalReturnUSDColored = MoneyFormat::get_formatted_gain('$', $totalReturnUSD);
        $totalReturnSelectedColored = ($currency === 'EUR') ? $totalReturnEURColored : $totalReturnUSDColored;

        // Create plain formatted versions (number + currency, no sign/color HTML) for data attributes
        // This allows JavaScript to reconstruct the sign and color properly on currency toggle
        $eurAbsValue = abs($totalReturnEUR);
        $usdAbsValue = abs($totalReturnUSD);
        $totalReturnEURFormatted = number_format($eurAbsValue, 2) . ' €';
        $totalReturnUSDFormatted = number_format($usdAbsValue, 2) . ' $';

        // Remove metadata from returnsData (keep only account data)
        $returnsData = array_filter(
            $serviceData,
            function ($key) {
                return !in_array($key, ['totalReturnEUR', 'totalReturnUSD', 'totalReturnEURFormatted', 'totalReturnUSDFormatted']);
            },
            ARRAY_FILTER_USE_KEY
        );

        // Transform returns data for view (pre-calculate all display values)
        $transformer = new ReturnsViewTransformer();
        $transformedReturnsData = $transformer->transform($returnsData, $year);

        // Fetch overview data for all years (for the overview chart)
        // Skip if skip_overview=1 is passed (useful for tests to avoid expensive all-years calculation)
        $overviewData = [];
        $skipOverview = request()->boolean('skip_overview', false);
        if (!$skipOverview) {
            $overviewService = new ReturnsOverview();
            $overviewData = $overviewService->handle(Auth::user()->id);
        }

        // Check for alerts (reuses the already-fetched serviceData)
        $alertsService = new ReturnsAlerts();
        $alerts = $alertsService->check($serviceData, $year);

        // Prepare view data
        $viewData = [
            'returnsData' => $transformedReturnsData,
            'totalReturnEUR' => $totalReturnEUR,
            'totalReturnUSD' => $totalReturnUSD,
            'totalReturnEURFormatted' => $totalReturnEURFormatted,
            'totalReturnUSDFormatted' => $totalReturnUSDFormatted,
            'totalReturnEURColored' => $totalReturnEURColored,
            'totalReturnUSDColored' => $totalReturnUSDColored,
            'totalReturnSelectedColored' => $totalReturnSelectedColored,
            'selectedYear' => $year,
            'selectedCurrency' => $currency,
            'availableYears' => range($currentYear, ReturnsConstants::MIN_YEAR),
            'overviewData' => $overviewData,
            'showOverview' => !$skipOverview,
            'alerts' => $alerts,
        ];

        return view('myfinance2::returns.dashboard', $viewData);
    }

    /**
     * Clear the returns cache and redirect back
     *
     * NOTE: This clears ALL application cache, not just returns cache.
     * When called from PHPUnit tests, this clears the test's isolated 'array' cache,
     * not the production cache (file/redis). This is expected behavior - tests should
     * not affect production cache state.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearCache(): \Illuminate\Http\RedirectResponse
    {
        $year = (int) request()->input('year', date('Y'));

        try {
            $cacheDriver = config('cache.default');
            $cacheFilesBefore = 0;
            $cacheFilesAfter = 0;
            $cachePath = null;

            // Count cache files before flush (for file driver only)
            if ($cacheDriver === 'file') {
                $cachePath = storage_path('framework/cache/data');
                if (is_dir($cachePath)) {
                    $cacheFilesBefore = $this->_countCacheFiles($cachePath);
                }
            }

            // Log with context about what's being cleared
            if ($cacheDriver === 'array') {
                Log::info(
                    "Clearing cache (driver: array - in-memory only, typically PHPUnit test context). "
                    . "This does not affect production cache."
                );
            } else {
                Log::info("Clearing cache (driver: $cacheDriver, files before: $cacheFilesBefore)");
            }

            // Clear all cache
            $flushResult = Cache::flush();

            // Count cache files after flush (for file driver only)
            if ($cacheDriver === 'file' && $cachePath && is_dir($cachePath)) {
                $cacheFilesAfter = $this->_countCacheFiles($cachePath);
            }

            // If we're using file driver and have files, verify they were actually deleted
            if ($cacheDriver === 'file' && $cacheFilesBefore > 0 && $cacheFilesAfter >= $cacheFilesBefore) {
                Log::error(
                    "Cache flush returned success but cache files were not deleted. "
                    . "Files before: $cacheFilesBefore, Files after: $cacheFilesAfter. "
                    . "This usually indicates a permission issue."
                );
                return redirect()->route('myfinance2::returns.index', ['year' => $year])
                    ->with('error', 'Failed to clear cache. Cache files were not deleted. '
                        . 'This is likely a permissions issue. '
                        . 'Please run: sudo chown -R $USER:www-data storage/framework/cache/ && sudo chmod -R 775 storage/framework/cache/');
            }

            if ($cacheDriver === 'array') {
                Log::info(
                    "Cache clear completed (driver: array - in-memory only). "
                    . "Production cache was not affected."
                );
            } else {
                Log::info("Returns cache cleared successfully (driver: $cacheDriver, files after: $cacheFilesAfter)");
            }

            return redirect()->route('myfinance2::returns.index', ['year' => $year])
                ->with(
                    'success',
                    'Cache cleared successfully. '
                    . 'The returns page will recalculate on next load.'
                );
        } catch (\Exception $e) {
            Log::error(
                "Failed to clear returns cache: " . $e->getMessage()
                . " | " . $e->getFile() . ":" . $e->getLine()
            );
            return redirect()->route('myfinance2::returns.index', ['year' => $year])
                ->with('error', 'Failed to clear cache. Error: ' . $e->getMessage());
        }
    }

    /**
     * Recursively count cache files in a directory
     */
    private function _countCacheFiles(string $path): int
    {
        $count = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to count cache files: " . $e->getMessage());
        }
        return $count;
    }
}

