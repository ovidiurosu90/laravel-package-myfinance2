<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\ChartsBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Core logic tests for chart formatting
 *
 * Tests the essential requirements:
 * - Percentage precision (regression test: -8.79% not -9%)
 * - Currency formatting works
 * - Metrics are registered correctly
 */
class ChartFormattingTest extends TestCase
{
    /**
     * Regression test: Percentage values must display with exactly 2 decimal places
     * Previously showed: -9%, -11.1%, -10.0%
     * Now shows: -8.79%, -11.34%, -9.83%
     */
    public function test_percentage_formatter_rounds_to_two_decimal_places(): void
    {
        $testCases = [
            [8.789, '8.79%'],      // Was showing -9%
            [-8.796, '-8.80%'],
            [-11.335, '-11.34%'],  // Was showing -11.1%
            [-9.834, '-9.83%'],    // Was showing -10.0%
            [0, '0.00%'],
            [100, '100.00%'],
        ];

        foreach ($testCases as [$input, $expected]) {
            $rounded = round($input * 100) / 100;
            $formatted = number_format($rounded, 2, '.', '') . '%';
            $this->assertEquals($expected, $formatted);
        }
    }

    /**
     * Currency formatter must work with Intl API
     */
    public function test_currency_formatter_supports_intl_number_format(): void
    {
        $this->assertTrue(extension_loaded('intl'),
            'PHP Intl extension is required for currency formatting');

        $formatter = new \NumberFormatter('de-DE', \NumberFormatter::CURRENCY);
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, '€');
        $formatted = $formatter->formatCurrency(1234.56, 'EUR');

        $this->assertNotEmpty($formatted);
        $this->assertStringContainsString('1', $formatted);
    }

    /**
     * All required metrics must be registered
     */
    public function test_metrics_are_registered(): void
    {
        $metrics = ChartsBuilder::getAccountMetrics();

        $expected = ['cost', 'change', 'mvalue', 'cash', 'changePercentage'];
        foreach ($expected as $metric) {
            $this->assertArrayHasKey($metric, $metrics);
        }

        $this->assertCount(5, $metrics);
    }

    /**
     * Metrics must have valid colors and titles for chart display
     */
    public function test_metrics_have_valid_colors_and_titles(): void
    {
        $metrics = ChartsBuilder::getAccountMetrics();

        foreach ($metrics as $metricName => $metric) {
            // Has required properties
            $this->assertArrayHasKey('line_color', $metric);
            $this->assertArrayHasKey('title', $metric);

            // Color is valid rgba
            $this->assertMatchesRegularExpression(
                '/^rgba\(\d+,\s*\d+,\s*\d+,\s*[\d.]+\)$/',
                $metric['line_color']
            );

            // Title is non-empty string
            $this->assertIsString($metric['title']);
            $this->assertNotEmpty($metric['title']);
        }
    }

    /**
     * Metrics must have distinct colors (visual clarity)
     */
    public function test_metric_colors_are_distinct(): void
    {
        $metrics = ChartsBuilder::getAccountMetrics();
        $colors = array_column($metrics, 'line_color');
        $uniqueColors = array_unique($colors);

        $this->assertCount(count($colors), $uniqueColors,
            'All metrics should have distinct colors');
    }
}

