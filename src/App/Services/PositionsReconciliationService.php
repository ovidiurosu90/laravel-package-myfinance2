<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Safety net for the /positions page. Cross-checks the figures shown against each other so a
 * regression in any aggregation lights up an alert at the top of the page instead of silently
 * producing wrong numbers. Every check compares figures built from the same prices, so they hold
 * to the cent and any gap is a real computation error, never price movement.
 *
 * Check A1 (rows): for each account, the open-position rows summed vs the account header
 *   totals (mvalue, cost, change). The headers show this page load's totals (LiveOverviewSeries),
 *   so both sides use the same prices: catches the totals drifting from the rows.
 * Check A2 (accounts): the User Overview header vs the sum of the account headers below it
 *   (cost, mvalue, change, cash), in EUR and USD. Catches the cross-account sum or the currency
 *   conversion drifting from the accounts shown.
 * Check B (stored): the rows repriced at the prices the cron used for its last snapshot
 *   (PositionsSnapshot) vs the stored account and User Overview series. Catches errors anywhere
 *   in the cron pipeline (persisted stats, chart files, the cross-account sum, the EUR
 *   conversion). Skipped while the cron is rewriting, and for an account whose positions or
 *   cash changed since the snapshot (a trade or a deposit in between).
 *
 * Staleness: snapshotAge() reports how old the cron's last snapshot is, so the page can warn when
 * the stored series stopped updating (the cron stopped, crashed or is stuck).
 */
class PositionsReconciliationService
{
    private const _POSITION_METRICS = ['mvalue', 'cost', 'change'];
    private const _STORED_METRICS = ['cash', 'mvalue', 'cost', 'change'];
    private const _USER_CURRENCIES = ['EUR' => '&euro;', 'USD' => '$'];

    // How each check names its two figures in the alert: [computed, shown].
    private const _LABELS = [
        'rows' => ['positions sum', 'account total'],
        'accounts' => ['sum of the accounts', 'overview total'],
        'stored' => ["positions at the last snapshot's prices", 'stored series'],
    ];

    private ReconciliationComparator $_comparator;
    private float $_tolerance;

    /**
     * @param float|null $tolerance Absolute tolerance per figure; defaults to the config.
     */
    public function __construct(?float $tolerance = null)
    {
        $this->_comparator = $tolerance !== null
            ? new ReconciliationComparator($tolerance)
            : ReconciliationComparator::fromConfig();
        $this->_tolerance = $this->_comparator->tolerance();
    }

    /**
     * @param array $groupedItems Positions grouped by account id, then by symbol.
     * @param array $accountData  Per-account data from Positions::handle().
     * @param int|null $userId    Authenticated user id, or null to skip the User Overview checks.
     * @param array $liveSeries   LiveOverviewSeries::build() result, or [] when not available.
     * @return array<int, array<string, mixed>> One entry per failed check.
     */
    public function reconcile(array $groupedItems, array $accountData, ?int $userId,
        array $liveSeries = []): array
    {
        $issues = array_merge(
            $this->_safe(
                fn() => $this->_checkAccountTotals($groupedItems, $accountData, $liveSeries),
                'account totals'
            ),
            $this->_safe(fn() => $this->_checkUserTotals($accountData, $liveSeries), 'user totals')
        );

        if ($userId !== null) {
            $issues = array_merge($issues, $this->_safe(
                fn() => $this->_checkStored($groupedItems, $accountData, $userId),
                'stored'
            ));
        }

        return $issues;
    }

    /**
     * Seconds since the cron's last snapshot of the user's positions, or null when unknown. Falls
     * back to the User Overview chart file when no snapshot was written yet.
     */
    public function snapshotAge(int $userId): ?int
    {
        try {
            $snapshot = PositionsSnapshot::read($userId);
            if (!empty($snapshot['taken_at'])) {
                return max(0, time() - (int) $snapshot['taken_at']);
            }

            $path = ChartsBuilder::getOverviewUserMetricPath($userId, 'mvalue_EUR');
            $disk = Storage::disk('local');

            return $disk->exists($path) ? max(0, time() - $disk->lastModified($path)) : null;
        } catch (\Throwable $e) {
            Log::warning('Positions snapshot age unavailable: ' . $e->getMessage());
            return null;
        }
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
     * Check A1: for each account, the open-position rows summed vs the account's header totals.
     */
    private function _checkAccountTotals(array $groupedItems, array $accountData,
        array $liveSeries): array
    {
        $issues = [];

        foreach ($groupedItems as $accountId => $items) {
            $account = $accountData[$accountId] ?? null;
            if (empty($account['accountModel'])) {
                continue;
            }

            $rows = $this->_sumPositionRows($items);
            foreach (self::_POSITION_METRICS as $metric) {
                $shown = $this->_lastValue($liveSeries['accounts'][$accountId][$metric] ?? []);
                $issues[] = $this->_compare('rows', 'account', $account['accountModel']->name,
                    $account['accountModel']->currency->display_code, $metric,
                    $rows[$metric], $shown);
            }
        }

        return array_values(array_filter($issues));
    }

    /**
     * Check A2: the User Overview header vs the sum of the account headers below it (cost,
     * mvalue, change and cash), in both currencies the overview can be switched to. Read from
     * the displayed series on both sides, so it checks exactly what the page shows.
     */
    private function _checkUserTotals(array $accountData, array $liveSeries): array
    {
        $eurusd = (float) ($liveSeries['eurusd'] ?? 0);
        if (empty($liveSeries['accounts']) || empty($liveSeries['user']) || $eurusd <= 0) {
            return [];
        }

        $sums = [];
        foreach ($liveSeries['accounts'] as $accountId => $series) {
            $currency = $accountData[$accountId]['accountModel']->currency->iso_code;
            foreach (self::_USER_CURRENCIES as $userCurrency => $symbol) {
                $factor = $this->_fxFactor($currency, $userCurrency, $eurusd);
                if ($factor === null) {
                    Log::warning("Positions reconciliation: cannot convert {$currency} to {$userCurrency}");
                    return [];
                }
                foreach (self::_STORED_METRICS as $metric) {
                    $value = $this->_lastValue($series[$metric] ?? []);
                    if ($value === null) {
                        return [];
                    }
                    $sums[$userCurrency][$metric] = ($sums[$userCurrency][$metric] ?? 0.0)
                        + $value * $factor;
                }
            }
        }

        $issues = [];
        foreach (self::_USER_CURRENCIES as $userCurrency => $symbol) {
            foreach (self::_STORED_METRICS as $metric) {
                $shown = $this->_lastValue($liveSeries['user'][$metric . '_' . $userCurrency] ?? []);
                $issues[] = $this->_compare('accounts', 'user', 'User Overview', $symbol, $metric,
                    $sums[$userCurrency][$metric], $shown);
            }
        }

        return array_values(array_filter($issues));
    }

    /**
     * Check B: the rows repriced at the snapshot's prices vs the stored series.
     */
    private function _checkStored(array $groupedItems, array $accountData, int $userId): array
    {
        $snapshot = PositionsSnapshot::read($userId);
        if (empty($snapshot['complete']) || empty($snapshot['accounts'])) {
            return [];
        }

        $repriced = $this->_repriceAtSnapshot($groupedItems, $accountData, $snapshot);
        $issues = [];

        foreach ($repriced['accounts'] as $accountId => $sums) {
            $account = $accountData[$accountId];
            foreach (self::_STORED_METRICS as $metric) {
                $stored = $this->_storedLastValue(
                    ChartsBuilder::getAccountMetricPath($account, $metric)
                );
                $issues[] = $this->_compare('stored', 'account', $account['accountModel']->name,
                    $account['accountModel']->currency->display_code, $metric,
                    $sums[$metric], $stored);
            }
        }

        $eurusd = (float) ($snapshot['eurusd'] ?? 0);
        if ($repriced['complete'] && $eurusd > 0) {
            $sumsEur = $this->_sumInEur($repriced['accounts'], $accountData, $eurusd);
            foreach (self::_STORED_METRICS as $metric) {
                $stored = $this->_storedLastValue(
                    ChartsBuilder::getOverviewUserMetricPath($userId, $metric . '_EUR')
                );
                $issues[] = $this->_compare('stored', 'user', 'User Overview', '&euro;', $metric,
                    $sumsEur[$metric], $stored);
            }
        }

        // The cron rewrote the series while they were being read: the figures may mix two
        // snapshots, so this round proves nothing either way.
        $after = PositionsSnapshot::read($userId);
        if (empty($after['complete']) || ($after['taken_at'] ?? null) !== $snapshot['taken_at']) {
            return [];
        }

        return array_values(array_filter($issues));
    }

    /**
     * Reprice every snapshot account the rows still match. 'complete' is false when any account
     * could not be compared (positions or cash changed, or an account was added or removed), in
     * which case the User Overview total cannot be compared either.
     *
     * @return array{accounts: array<int, array<string, float>>, complete: bool}
     */
    private function _repriceAtSnapshot(array $groupedItems, array $accountData,
        array $snapshot): array
    {
        $accounts = [];
        $complete = count($groupedItems) === count($snapshot['accounts']);

        foreach ($snapshot['accounts'] as $accountId => $snapAccount) {
            $account = $accountData[$accountId] ?? null;
            $items = $groupedItems[$accountId] ?? null;
            $sums = (empty($account['accountModel']) || $items === null)
                ? null
                : PositionsSnapshot::repriceAccount($items, $snapAccount);

            $cash = $account !== null ? PositionsSnapshot::cashOf($account) : null;
            if ($sums === null || abs($cash - (float) ($snapAccount['cash'] ?? 0)) > $this->_tolerance) {
                $complete = false;
                continue;
            }

            $accounts[$accountId] = $sums + ['cash' => $cash];
        }

        return ['accounts' => $accounts, 'complete' => $complete];
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
     * Sum per-account figures across accounts after converting each to EUR. An account in a
     * currency other than EUR/USD is skipped and logged, like the cron's User Overview.
     *
     * @param array<int, array<string, float>> $sumsByAccount
     */
    private function _sumInEur(array $sumsByAccount, array $accountData, float $eurusd): array
    {
        $total = [];

        foreach ($sumsByAccount as $accountId => $sums) {
            $currency = $accountData[$accountId]['accountModel']->currency->iso_code;
            $factor = $this->_fxFactor($currency, 'EUR', $eurusd);
            if ($factor === null) {
                Log::warning("Positions reconciliation: cannot convert {$currency} to EUR");
                continue;
            }
            foreach ($sums as $metric => $value) {
                $total[$metric] = ($total[$metric] ?? 0.0) + $value * $factor;
            }
        }

        return $total + array_fill_keys(self::_STORED_METRICS, 0.0);
    }

    /**
     * Factor converting an amount between EUR and USD (EURUSD is USD per EUR). Deliberately its
     * own copy rather than LiveOverviewSeries::fxFactor, so a conversion bug in the figures being
     * checked cannot cancel out in the check.
     */
    private function _fxFactor(string $from, string $to, float $eurusd): ?float
    {
        if ($from === $to) {
            return 1.0;
        }
        if ($from === 'USD' && $to === 'EUR') {
            return 1.0 / $eurusd;
        }
        if ($from === 'EUR' && $to === 'USD') {
            return $eurusd;
        }

        return null;
    }

    /**
     * Last value of a stored series, read straight from disk rather than through the chart cache
     * so it belongs to the same cron run as the snapshot read around it.
     */
    private function _storedLastValue(string $path): ?float
    {
        $disk = Storage::disk('local');
        if (!$disk->exists($path)) {
            return null;
        }

        return $this->_lastValue(ChartsBuilder::parseChartLiteral((string) $disk->get($path)));
    }

    private function _lastValue(array $points): ?float
    {
        $last = end($points);

        return ($last !== false && isset($last['value'])) ? (float) $last['value'] : null;
    }

    /**
     * Build an issue when the shown value differs from the independently computed value by more
     * than the tolerance. Both sides use the same prices, so only rounding is tolerated.
     */
    private function _compare(string $check, string $scope, string $account, string $currency,
        string $metric, float $computed, ?float $shown): ?array
    {
        [$computedLabel, $shownLabel] = self::_LABELS[$check];
        $issue = $this->_comparator->compare($check, $account, $currency, $metric, $computed,
            $shown, $computedLabel, $shownLabel);

        return $issue === null ? null : $issue + ['scope' => $scope];
    }
}
