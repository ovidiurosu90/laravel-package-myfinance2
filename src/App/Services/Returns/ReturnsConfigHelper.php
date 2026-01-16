<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

/**
 * Returns Configuration Helper
 *
 * Centralized service for resolving configuration overrides (prices, exchange rates, etc.)
 * from trades config. Implements hierarchical override resolution:
 * 1. Account-specific overrides (highest priority)
 * 2. Global overrides (fallback)
 *
 * Handles cross-year date matching for early January lookups.
 */
class ReturnsConfigHelper
{
    /**
     * Get override value for a symbol on a specific date
     *
     * @param string $symbol The symbol to look up (e.g., 'AAPL', 'EURUSD=X')
     * @param int $accountId The account ID for account-specific overrides
     * @param \DateTimeInterface $date The date to find override for
     * @param string $type The override type ('price' or 'exchange_rate')
     * @return float|null Override value if found, null otherwise
     */
    public function getOverride(
        string $symbol,
        int $accountId,
        \DateTimeInterface $date,
        string $type
    ): ?float {
        $dateString = $date->format('Y-m-d');
        $configKey = "{$type}_overrides";
        $config = config("trades.{$configKey}");

        if (empty($config)) {
            return null;
        }

        // Try account-specific override first (highest priority)
        $byAccountConfig = $config['by_account'] ?? [];
        $accountOverride = $this->findOverrideByDate(
            $symbol,
            $accountId,
            $dateString,
            $byAccountConfig
        );
        if ($accountOverride !== null) {
            return $accountOverride;
        }

        // Fall back to global override
        return $this->findOverrideByDate(
            $symbol,
            null,
            $dateString,
            $config['global'] ?? []
        );
    }

    /**
     * Find override value for a symbol on a specific date
     *
     * Searches through available override dates and returns the most recent
     * override that applies to the requested date. Supports cross-year matching
     * for early January dates (first 7 days can use December overrides).
     *
     * @param string $symbol The symbol to look up
     * @param int|null $accountId Account ID for account-specific lookup, null for global
     * @param string $dateString Date in Y-m-d format
     * @param array $overrideConfig Configuration array to search
     * @return float|null Override value if found, null otherwise
     */
    private function findOverrideByDate(
        string $symbol,
        ?int $accountId,
        string $dateString,
        array $overrideConfig
    ): ?float {
        if ($accountId !== null) {
            if (!isset($overrideConfig[$accountId]) || !isset($overrideConfig[$accountId][$symbol])) {
                return null;
            }
            $symbolDates = $overrideConfig[$accountId][$symbol];
        } else {
            if (!isset($overrideConfig[$symbol])) {
                return null;
            }
            $symbolDates = $overrideConfig[$symbol];
        }

        $requestedYear = substr($dateString, 0, 4);
        $requestedMonth = substr($dateString, 5, 2);
        $requestedDay = substr($dateString, 8, 2);
        $allowCrossYear = ($requestedMonth === '01' && (int)$requestedDay <= 7);

        $applicableDates = array_filter(
            array_keys($symbolDates),
            function ($date) use ($dateString, $requestedYear, $allowCrossYear) {
                if ($date > $dateString) {
                    return false;
                }
                $dateYear = substr($date, 0, 4);
                if ($dateYear === $requestedYear) {
                    return true;
                }
                if ($allowCrossYear && (int)$dateYear === (int)$requestedYear - 1) {
                    $dateMonth = substr($date, 5, 2);
                    return $dateMonth === '12';
                }
                return false;
            }
        );

        if (empty($applicableDates)) {
            return null;
        }

        rsort($applicableDates);
        $applicableDate = $applicableDates[0];
        return (float)$symbolDates[$applicableDate];
    }
}

