<?php

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\ChartsBuilder;
use PHPUnit\Framework\TestCase;

class ChartsBuilderMetricsTest extends TestCase
{
    /**
     * Test that changePercentage metric is registered with correct properties
     *
     * Verifies that the new changePercentage metric is properly defined in the
     * metrics list with the correct color and title.
     */
    public function test_change_percentage_metric_is_registered(): void
    {
        $metrics = ChartsBuilder::getAccountMetrics();

        // Verify changePercentage metric exists
        $this->assertArrayHasKey('changePercentage', $metrics);
    }

    /**
     * Test that changePercentage has correct properties
     *
     * Verifies that changePercentage metric has:
     * - A purple color (rgba(156, 39, 176, 1))
     * - A title of 'Change %'
     */
    public function test_change_percentage_metric_properties(): void
    {
        $metrics = ChartsBuilder::getAccountMetrics();
        $changePercentage = $metrics['changePercentage'];

        $this->assertArrayHasKey('line_color', $changePercentage);
        $this->assertArrayHasKey('title', $changePercentage);

        // Verify purple color
        $this->assertEquals('rgba(156, 39, 176, 1)', $changePercentage['line_color']);

        // Verify title
        $this->assertEquals('Change %', $changePercentage['title']);
    }

    /**
     * Test that all 5 account metrics are registered
     *
     * Verifies that the metrics list contains all expected metrics:
     * cost, change, mvalue, cash, and changePercentage
     */
    public function test_all_account_metrics_are_registered(): void
    {
        $metrics = ChartsBuilder::getAccountMetrics();

        $expectedMetrics = ['cost', 'change', 'mvalue', 'cash', 'changePercentage'];

        foreach ($expectedMetrics as $metric) {
            $this->assertArrayHasKey($metric, $metrics,
                "Metric '{$metric}' should be registered in getAccountMetrics()");
        }

        // Verify we have exactly 5 metrics (no extras)
        $this->assertCount(5, $metrics);
    }

    /**
     * Test that each metric has required properties
     *
     * Verifies that all metrics have line_color and title properties.
     */
    public function test_each_metric_has_required_properties(): void
    {
        $metrics = ChartsBuilder::getAccountMetrics();

        foreach ($metrics as $metricName => $properties) {
            $this->assertArrayHasKey('line_color', $properties,
                "Metric '{$metricName}' should have 'line_color' property");

            $this->assertArrayHasKey('title', $properties,
                "Metric '{$metricName}' should have 'title' property");

            // Verify color is a valid CSS rgba string
            $this->assertMatchesRegularExpression(
                '/^rgba\(\d+,\s*\d+,\s*\d+,\s*[\d.]+\)$/',
                $properties['line_color'],
                "Metric '{$metricName}' color should be valid rgba format"
            );
        }
    }
}

