<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Shared comparison for the page reconciliation safety nets (/positions, /watchlist-symbols,
 * /returns). Each check compares a figure the page shows against the same figure computed
 * independently from the same data, so only rounding is tolerated: a gap beyond the absolute
 * tolerance is a real computation error. Issues render through the
 * general.partials.reconciliation-alerts partial.
 */
class ReconciliationComparator
{
    public function __construct(private float $_tolerance)
    {
    }

    public static function fromConfig(): self
    {
        return new self((float) config('myfinance2.reconciliation.tolerance', 0.05));
    }

    public function tolerance(): float
    {
        return $this->_tolerance;
    }

    /**
     * Label of a metric as the pages show it (the chart legend titles: MValue, Cost, Change, Cash),
     * so an alert names a figure the same way the page does.
     */
    public static function metricLabel(string $metric): string
    {
        return ChartsBuilder::getAccountMetrics()[$metric]['title'] ?? ucfirst($metric);
    }

    /**
     * @param string $check         Short id of the check, e.g. 'rows'.
     * @param string $subject       What is checked, e.g. the account name or 'User Overview'.
     * @param string $currency      Display currency code for the amounts.
     * @param string $computedLabel How the independent figure was computed, e.g. 'positions sum'.
     * @param string $shownLabel    Where the page shows the figure, e.g. 'account total'.
     * @return array<string, mixed>|null The issue, or null when the figures agree (or one is missing).
     */
    public function compare(string $check, string $subject, string $currency, string $metric,
        float $computed, ?float $shown, string $computedLabel, string $shownLabel): ?array
    {
        if ($shown === null) {
            return null;
        }

        $diff = $shown - $computed;
        if (abs($diff) <= $this->_tolerance) {
            return null;
        }

        $base = max(abs($computed), abs($shown));

        return [
            'check' => $check,
            'subject' => $subject,
            'currency' => $currency,
            'metric' => $metric,
            'metric_label' => self::metricLabel($metric),
            'computed' => $computed,
            'shown' => $shown,
            'computed_label' => $computedLabel,
            'shown_label' => $shownLabel,
            'diff' => $diff,
            'diff_pct' => $base > 0 ? (abs($diff) / $base) * 100 : 0.0,
        ];
    }
}
