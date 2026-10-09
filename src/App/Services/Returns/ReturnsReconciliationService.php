<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Services\ReconciliationComparator;

/**
 * Safety net for the /returns page. Cross-checks the totals it shows against the parts they are
 * built from, all from the same request, in EUR and USD:
 *
 * Check R1 (table): the header total return vs the sum of the account returns in the table.
 * Check R2 (chart): for every year of the overview chart, the total bar vs the sum of the
 *   account bars of that year.
 * Check R3 (chart vs table): for a past year, the overview chart's total vs the table's total.
 *   The chart is cached for up to an hour, so a gap means it is stale (e.g. right after a trade
 *   or an override of that year was edited) and shows outdated figures. The current year is
 *   skipped: its end value follows live prices, which move within the cache lifetime.
 *
 * The chart values are rounded to the cent, so the sums of them allow half a cent per value on
 * top of the tolerance.
 */
class ReturnsReconciliationService
{
    private const _CURRENCIES = ['EUR' => '&euro;', 'USD' => '$'];
    private const _META_KEYS = [
        'totalReturnEUR', 'totalReturnUSD', 'totalReturnEURFormatted', 'totalReturnUSDFormatted',
    ];
    private const _HALF_CENT = 0.005;
    private const _METRIC = 'return';

    private float $_tolerance;

    /**
     * @param float|null $tolerance Absolute tolerance per figure; defaults to the config.
     */
    public function __construct(?float $tolerance = null)
    {
        $this->_tolerance = ($tolerance !== null
            ? new ReconciliationComparator($tolerance)
            : ReconciliationComparator::fromConfig())->tolerance();
    }

    /**
     * @param array $serviceData  Returns::handle() result for the selected year.
     * @param array $overviewData ReturnsOverview::handle() result, [] when the chart is skipped.
     * @return array<int, array<string, mixed>> One entry per failed check.
     */
    public function reconcile(array $serviceData, array $overviewData, int $year,
        int $currentYear): array
    {
        try {
            $issues = $this->_checkTable($serviceData, $year);
            if (!empty($overviewData)) {
                $issues = array_merge($issues, $this->_checkChart($overviewData));
                if ($year < $currentYear) {
                    $issues = array_merge($issues, $this->_checkChartVsTable($serviceData, $overviewData, $year));
                }
            }

            return array_values(array_filter($issues));
        } catch (\Throwable $e) {
            Log::warning('Returns reconciliation failed: ' . $e->getMessage());
            return [];
        }
    }

    private function _checkTable(array $serviceData, int $year): array
    {
        $comparator = new ReconciliationComparator($this->_tolerance);
        $issues = [];

        foreach (self::_CURRENCIES as $currency => $symbol) {
            $sum = 0.0;
            foreach ($serviceData as $key => $account) {
                if (!in_array($key, self::_META_KEYS, true) && is_array($account)) {
                    $sum += (float) ($account[$currency]['actualReturn'] ?? 0);
                }
            }
            $issues[] = $comparator->compare('table', "{$year} total", $symbol, self::_METRIC, $sum,
                (float) ($serviceData['totalReturn' . $currency] ?? 0), 'sum of the accounts', 'header total');
        }

        return $issues;
    }

    private function _checkChart(array $overviewData): array
    {
        $issues = [];

        foreach (self::_CURRENCIES as $currency => $symbol) {
            $accountsByYear = [];
            foreach ($overviewData['accounts'] ?? [] as $account) {
                foreach ($account[$currency] ?? [] as $point) {
                    $accountsByYear[$point['time']][] = (float) $point['value'];
                }
            }

            foreach ($overviewData['total'][$currency] ?? [] as $point) {
                $values = $accountsByYear[$point['time']] ?? [];
                $comparator = new ReconciliationComparator(
                    $this->_tolerance + self::_HALF_CENT * (count($values) + 1)
                );
                $issues[] = $comparator->compare('chart', "{$point['time']} chart", $symbol,
                    self::_METRIC, array_sum($values), (float) $point['value'],
                    'sum of the account bars', 'total bar');
            }
        }

        return $issues;
    }

    private function _checkChartVsTable(array $serviceData, array $overviewData, int $year): array
    {
        $comparator = new ReconciliationComparator($this->_tolerance + self::_HALF_CENT);
        $issues = [];

        foreach (self::_CURRENCIES as $currency => $symbol) {
            $chart = $this->_pointValue($overviewData['total'][$currency] ?? [], (string) $year);
            $issues[] = $comparator->compare('chart-table', "{$year} total", $symbol, self::_METRIC,
                (float) ($serviceData['totalReturn' . $currency] ?? 0), $chart,
                'table', 'overview chart (cached up to 1h)');
        }

        return $issues;
    }

    private function _pointValue(array $series, string $time): ?float
    {
        foreach ($series as $point) {
            if ((string) $point['time'] === $time) {
                return (float) $point['value'];
            }
        }

        return null;
    }
}
