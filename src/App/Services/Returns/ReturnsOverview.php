<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Models\Account;

/**
 * Returns Overview Service
 *
 * Collects returns data across all years for overview chart display.
 * Provides aggregated total returns and per-account returns for each year.
 */
class ReturnsOverview
{
    /**
     * Cache TTL for overview data (in seconds)
     * 1 hour = 3600 seconds
     */
    private const CACHE_TTL = 3600;

    private Returns $_returnsService;

    public function __construct(Returns $returnsService = null)
    {
        $this->_returnsService = $returnsService ?? new Returns();
    }

    /**
     * Get returns data for all years for the overview chart
     *
     * Returns structure:
     * [
     *     'total' => [
     *         'EUR' => [['time' => '2016', 'value' => 100], ...],
     *         'USD' => [['time' => '2016', 'value' => 95], ...],
     *     ],
     *     'accounts' => [
     *         accountId => [
     *             'name' => 'Account Name',
     *             'EUR' => [['time' => '2016', 'value' => 50], ...],
     *             'USD' => [['time' => '2016', 'value' => 48], ...],
     *         ],
     *         ...
     *     ],
     * ]
     *
     * @param int $userId User ID for cache key
     * @return array Returns overview data
     */
    public function handle(int $userId): array
    {
        $cacheKey = "returns:overview:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function ()
        {
            return $this->_calculateOverviewData();
        });
    }

    /**
     * Calculate overview data for all years
     */
    private function _calculateOverviewData(): array
    {
        $currentYear = (int) date('Y');
        $minYear = ReturnsConstants::MIN_YEAR;

        // Get all accounts to maintain consistent ordering
        $accounts = Account::with('currency')
            ->where('is_trade_account', '1')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        // Initialize result structure
        $result = [
            'total' => [
                'EUR' => [],
                'USD' => [],
            ],
            'accounts' => [],
        ];

        // Initialize account entries with names
        foreach ($accounts as $accountId => $account) {
            $result['accounts'][$accountId] = [
                'name' => $account->name,
                'EUR' => [],
                'USD' => [],
            ];
        }

        // Collect data for each year
        for ($year = $minYear; $year <= $currentYear; $year++) {
            try {
                $yearData = $this->_returnsService->handle($year);
                $this->_processYearData($result, $yearData, $year);
            } catch (\Exception $e) {
                Log::error("Failed to get returns for year {$year}: " . $e->getMessage());
                continue;
            }
        }

        // Remove accounts with no data across all years
        $result['accounts'] = array_filter(
            $result['accounts'],
            fn($accountData) => !empty($accountData['EUR']) || !empty($accountData['USD'])
        );

        // Calculate cumulative totals (sum of all years)
        $result['cumulativeTotal'] = [
            'EUR' => $this->_calculateCumulativeTotal($result['total']['EUR']),
            'USD' => $this->_calculateCumulativeTotal($result['total']['USD']),
        ];

        return $result;
    }

    /**
     * Process year data and add to result structure
     */
    private function _processYearData(array &$result, array $yearData, int $year): void
    {
        $yearString = (string) $year;

        // Add total returns for this year
        if (isset($yearData['totalReturnEUR'])) {
            $result['total']['EUR'][] = [
                'time' => $yearString,
                'value' => round($yearData['totalReturnEUR'], 2),
            ];
        }

        if (isset($yearData['totalReturnUSD'])) {
            $result['total']['USD'][] = [
                'time' => $yearString,
                'value' => round($yearData['totalReturnUSD'], 2),
            ];
        }

        // Add per-account returns for this year
        foreach ($yearData as $key => $data) {
            // Skip metadata keys
            if (in_array($key, ['totalReturnEUR', 'totalReturnUSD',
                'totalReturnEURFormatted', 'totalReturnUSDFormatted'])) {
                continue;
            }

            // This should be an account ID
            $accountId = $key;

            if (!isset($result['accounts'][$accountId])) {
                continue;
            }

            // Get the actual return values for EUR and USD
            if (isset($data['EUR']['actualReturn'])) {
                $result['accounts'][$accountId]['EUR'][] = [
                    'time' => $yearString,
                    'value' => round($data['EUR']['actualReturn'], 2),
                ];
            }

            if (isset($data['USD']['actualReturn'])) {
                $result['accounts'][$accountId]['USD'][] = [
                    'time' => $yearString,
                    'value' => round($data['USD']['actualReturn'], 2),
                ];
            }
        }
    }

    /**
     * Calculate cumulative total from yearly data
     *
     * @param array $yearlyData Array of ['time' => year, 'value' => amount]
     * @return float Sum of all values
     */
    private function _calculateCumulativeTotal(array $yearlyData): float
    {
        $total = 0;
        foreach ($yearlyData as $item) {
            $total += $item['value'];
        }
        return round($total, 2);
    }

    /**
     * Clear the overview cache for a user
     *
     * @param int $userId User ID
     * @return void
     */
    public static function clearCache(int $userId): void
    {
        $cacheKey = "returns:overview:{$userId}";
        Cache::forget($cacheKey);
    }
}

