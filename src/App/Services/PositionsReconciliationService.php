<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Safety net for the /positions page. Cross-checks the figures shown against each other so a
 * regression in any aggregation lights up an alert at the top of the page instead of silently
 * producing wrong numbers.
 *
 * Check 1 (per account, same currency): the live sum of the open-position rows (mvalue, cost,
 *   gain) must match the account-overview-summary the card header shows. Held to a tight
 *   tolerance since there is no FX conversion involved.
 * Check 2 (whole portfolio): the live positions, summed across accounts and converted to EUR at
 *   the current rate, must match the User Overview total, within a small tolerance that absorbs
 *   FX-conversion differences. This is deliberately an INDEPENDENT computation: the cron builds
 *   the User Overview by summing the per-account chart series, so comparing that sum back to the
 *   User Overview would be tautological. Summing the live position rows instead makes the check
 *   real, and surfaces the execution-rate-vs-current-rate variation on cost.
 *
 * All figures are read from the same sources the page renders: the live position rows and cash
 * from Positions::handle(), and the shown summaries from the stored chart series via ChartsBuilder.
 */
class PositionsReconciliationService
{
    private const _ACCOUNT_METRICS = ['mvalue', 'cost', 'change'];
    private const _USER_METRICS = ['cost', 'mvalue', 'change', 'cash'];

    /**
     * @param array $groupedItems Positions grouped by account id, then by symbol.
     * @param array $accountData  Per-account data from Positions::handle().
     * @param int|null $userId    Authenticated user id, or null to skip the portfolio check.
     * @return array<int, array<string, mixed>> One entry per failed check.
     */
    public function reconcile(array $groupedItems, array $accountData, ?int $userId): array
    {
        $issues = $this->_safe(
            fn() => $this->_checkAccounts($groupedItems, $accountData),
            'accounts'
        );

        if ($userId !== null) {
            $issues = array_merge($issues, $this->_safe(
                fn() => $this->_checkUserOverview($groupedItems, $accountData, $userId),
                'user'
            ));
        }

        return $issues;
    }

    /**
     * Run one check in isolation so a failure in it neither hides another check's issues nor
     * disappears silently: the reason is logged instead.
     */
    private function _safe(callable $check, string $label): array
    {
        try {
            return $check();
        } catch (\Throwable $e) {
            Log::warning("Positions reconciliation [{$label}] failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Check 1: independent sum of the open-position rows vs the shown account summary.
     */
    private function _checkAccounts(array $groupedItems, array $accountData): array
    {
        $tolerancePct = (float) config('myfinance2.reconciliation.account_tolerance_pct', 0.5);
        $floor = (float) config('myfinance2.reconciliation.absolute_floor', 1.0);
        $issues = [];

        foreach ($groupedItems as $accountId => $items) {
            $account = $accountData[$accountId] ?? null;
            if (empty($account['accountModel'])) {
                continue;
            }

            $liveSums = $this->_sumPositionRows($items);
            $currency = $account['accountModel']->currency->display_code;
            $name = $account['accountModel']->name;

            foreach (self::_ACCOUNT_METRICS as $metric) {
                $shown = ChartsBuilder::getChartAccountLastValue($account, $metric);
                if ($shown === null) {
                    continue;
                }

                $issue = $this->_compare(
                    'account', $name, $currency, $metric,
                    $liveSums[$metric], $shown, $tolerancePct, $floor
                );
                if ($issue !== null) {
                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }

    /**
     * Sum the raw account-currency values of the open-position rows for one account. Uses the
     * same cost2 / change2 fields the account totals accumulate, so a divergence points at a
     * real bug rather than a field mismatch.
     */
    private function _sumPositionRows(array $items): array
    {
        $sums = ['mvalue' => 0.0, 'cost' => 0.0, 'change' => 0.0];

        foreach ($items as $item) {
            $sums['mvalue'] += (float) ($item['market_value_in_account_currency'] ?? 0);
            $sums['cost'] += (float) ($item['cost2_in_account_currency'] ?? 0);
            $sums['change'] += (float) ($item['overall_change2_in_account_currency'] ?? 0);
        }

        return $sums;
    }

    /**
     * Check 2: live positions summed across accounts and converted to EUR vs the User Overview.
     */
    private function _checkUserOverview(array $groupedItems, array $accountData,
        int $userId): array
    {
        $eurusd = ChartsBuilder::getLatestSymbolValue('EURUSD=X');
        if ($eurusd === null || $eurusd <= 0) {
            Log::warning('Positions reconciliation: missing EURUSD rate, portfolio check skipped');
            return [];
        }

        $tolerancePct = (float) config('myfinance2.reconciliation.user_fx_tolerance_pct', 0.1);
        $floor = (float) config('myfinance2.reconciliation.absolute_floor', 1.0);
        $live = $this->_sumPortfolioLiveInEur($groupedItems, $accountData, $eurusd);
        $issues = [];

        foreach (self::_USER_METRICS as $metric) {
            $shown = ChartsBuilder::getChartOverviewUserLastValue($userId, $metric . '_EUR');
            if ($shown === null) {
                continue;
            }

            $issue = $this->_compare(
                'user', 'User Overview', '&euro;', $metric,
                $live[$metric], $shown, $tolerancePct, $floor
            );
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }

    /**
     * Sum the live figures across all accounts, converted to EUR at the current rate: mvalue,
     * cost and gain from the open-position rows (accounts with positions), and cash from every
     * account's live balance. This is independent of the stored User Overview series.
     */
    private function _sumPortfolioLiveInEur(array $groupedItems, array $accountData,
        float $eurusd): array
    {
        $sums = array_fill_keys(self::_USER_METRICS, 0.0);

        foreach ($groupedItems as $accountId => $items) {
            $account = $accountData[$accountId] ?? null;
            $factor = $this->_accountEurFactor($account, $eurusd);
            if ($factor === null) {
                continue;
            }

            $rows = $this->_sumPositionRows($items);
            $sums['mvalue'] += $rows['mvalue'] * $factor;
            $sums['cost'] += $rows['cost'] * $factor;
            $sums['change'] += $rows['change'] * $factor;
        }

        foreach ($accountData as $account) {
            $factor = $this->_accountEurFactor($account, $eurusd);
            if ($factor === null || empty($account['cashBalanceUtils'])) {
                continue;
            }

            $sums['cash'] += ((float) $account['cashBalanceUtils']->getAmount()) * $factor;
        }

        return $sums;
    }

    /**
     * EUR conversion factor for an account, or null when the account or its currency cannot be
     * resolved (the account is then skipped so a partial sum never masquerades as a match).
     */
    private function _accountEurFactor(?array $account, float $eurusd): ?float
    {
        if (empty($account['accountModel'])) {
            return null;
        }

        $currency = $account['accountModel']->currency->iso_code;
        $factor = $this->_eurFactor($currency, $eurusd);
        if ($factor === null) {
            Log::warning("Positions reconciliation: cannot convert {$currency} to EUR");
        }

        return $factor;
    }

    private function _eurFactor(string $currency, float $eurusd): ?float
    {
        return match ($currency) {
            'EUR' => 1.0,
            'USD' => 1.0 / $eurusd,
            default => null,
        };
    }

    /**
     * Build an issue when the shown value differs from the independently computed value beyond
     * the tolerance. The absolute floor prevents near-zero metrics from tripping the percentage
     * test on rounding noise.
     */
    private function _compare(string $scope, string $account, string $currency, string $metric,
        float $computed, float $shown, float $tolerancePct, float $floor): ?array
    {
        $diff = $shown - $computed;
        $absDiff = abs($diff);
        $base = max(abs($computed), abs($shown), $floor);
        $diffPct = $base > 0 ? ($absDiff / $base) * 100 : 0.0;

        if ($absDiff <= $floor || $diffPct <= $tolerancePct) {
            return null;
        }

        return [
            'scope' => $scope,
            'account' => $account,
            'currency' => $currency,
            'metric' => $metric,
            'computed' => $computed,
            'shown' => $shown,
            'diff' => $diff,
            'diff_pct' => $diffPct,
        ];
    }
}
