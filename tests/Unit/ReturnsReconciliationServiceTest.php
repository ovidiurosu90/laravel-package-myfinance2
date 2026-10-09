<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Services\Returns\ReturnsReconciliationService;

/**
 * Unit tests for ReturnsReconciliationService: the /returns header total vs the account returns,
 * and the overview chart totals vs its account bars and vs the table. Pure, no config access.
 */
class ReturnsReconciliationServiceTest extends TestCase
{
    private ReturnsReconciliationService $_service;

    protected function setUp(): void
    {
        $this->_service = new ReturnsReconciliationService(0.05);
    }

    /**
     * Table for 2024: two accounts and a virtual one, the header totals being their sums.
     */
    private function _serviceData(array $overrides = []): array
    {
        return array_merge([
            4 => ['EUR' => ['actualReturn' => 1000.0], 'USD' => ['actualReturn' => 1100.0]],
            5 => ['EUR' => ['actualReturn' => -200.0], 'USD' => ['actualReturn' => -220.0]],
            'virtual_x' => ['EUR' => ['actualReturn' => 50.0], 'USD' => ['actualReturn' => 55.0]],
            'totalReturnEUR' => 850.0,
            'totalReturnUSD' => 935.0,
            'totalReturnEURFormatted' => '850 €',
            'totalReturnUSDFormatted' => '935 $',
        ], $overrides);
    }

    private function _point(string $year, float $value): array
    {
        return ['time' => $year, 'value' => $value];
    }

    /**
     * Overview chart for 2023-2024, each total bar being the sum of the account bars.
     */
    private function _overviewData(float $total2024Eur = 850.0): array
    {
        return [
            'total' => [
                'EUR' => [$this->_point('2023', 300.0), $this->_point('2024', $total2024Eur)],
                'USD' => [$this->_point('2023', 330.0), $this->_point('2024', 935.0)],
            ],
            'accounts' => [
                4 => [
                    'EUR' => [$this->_point('2023', 300.0), $this->_point('2024', 1000.0)],
                    'USD' => [$this->_point('2023', 330.0), $this->_point('2024', 1100.0)],
                ],
                5 => [
                    'EUR' => [$this->_point('2024', -200.0)],
                    'USD' => [$this->_point('2024', -220.0)],
                ],
                'virtual_x' => [
                    'EUR' => [$this->_point('2024', 50.0)],
                    'USD' => [$this->_point('2024', 55.0)],
                ],
            ],
        ];
    }

    public function test_consistent_page_produces_no_issue(): void
    {
        $this->assertSame([], $this->_service->reconcile($this->_serviceData(), $this->_overviewData(), 2024, 2026));
    }

    /**
     * R1: a header total that is not the sum of the account returns.
     */
    public function test_header_total_off_from_the_accounts_is_flagged(): void
    {
        $issues = $this->_service->reconcile($this->_serviceData(['totalReturnUSD' => 900.0]), [], 2024, 2026);

        $this->assertCount(1, $issues);
        $this->assertSame('table', $issues[0]['check']);
        $this->assertSame('$', $issues[0]['currency']);
        $this->assertEqualsWithDelta(-35.0, $issues[0]['diff'], 0.0001);
    }

    /**
     * R2 and R3: a 2024 chart total that matches neither its account bars nor the table.
     */
    public function test_chart_total_off_is_flagged_against_its_bars_and_the_table(): void
    {
        $issues = $this->_service->reconcile($this->_serviceData(), $this->_overviewData(800.0), 2024, 2026);

        $this->assertSame(['chart', 'chart-table'], array_column($issues, 'check'));
        $this->assertSame('2024 chart', $issues[0]['subject']);
        $this->assertEqualsWithDelta(-50.0, $issues[0]['diff'], 0.0001);
        $this->assertEqualsWithDelta(-50.0, $issues[1]['diff'], 0.0001);
    }

    /**
     * R3: a stale chart for the current year is expected (live prices), so it is not compared.
     */
    public function test_chart_vs_table_skips_the_current_year(): void
    {
        $overview = $this->_overviewData();
        $overview['total']['EUR'][1]['value'] = 800.0;
        $overview['accounts'][4]['EUR'][1]['value'] = 950.0;

        $this->assertSame([], $this->_service->reconcile($this->_serviceData(), $overview, 2024, 2024));
    }

    /**
     * R2: the chart's values are rounded to the cent; their rounding is tolerated.
     */
    public function test_cent_rounding_of_the_chart_is_tolerated(): void
    {
        $overview = $this->_overviewData();
        $overview['accounts'][4]['EUR'][1]['value'] = 1000.01;
        $overview['accounts'][5]['EUR'][0]['value'] = -199.99;

        $this->assertSame([], $this->_service->reconcile($this->_serviceData(), $overview, 2024, 2024));
    }
}
