<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Reconciles the overview's totals, in EUR:
 *
 *   growth    = Investment total + Funding total (the Funding total is negative: money that left the sources)
 *   explained = realized (Gains Per Year: stock gains + dividends) + unrealized (the open positions' Gain)
 *               - money still in intermediary accounts (left a source, not invested yet)
 *   gap       = growth - explained
 *   explained_pct = explained / growth: how much of the growth the gains explain
 *
 * The gap is what no gain column captures: currency moves (Gains Per Year converts at each year's year-end rate,
 * Funding and Investment at today's), interest, transferred positions' funding, fees outside trades. A gap that
 * jumps usually means something is recorded wrong.
 *
 * Pure: works on the arrays OverviewDashboard already built, no DB or API calls.
 */
class OverviewReconciliation
{
    /**
     * @param array $sourceAccounts       Funding card rows (balance_in_eur)
     * @param array $intermediaryAccounts Intermediary account rows (balance_in_eur)
     * @param array $investmentAccounts   Investment card rows (balance_in_eur, total_change, exchange_rate)
     * @param array $gainsPerYear         year => accountId => symbol => data
     * @param array $dividendsPerYear     year => accountId => symbol => data
     * @param array $eurRatesPerYear      year => accountId => {exchange_rate}
     *
     * @return array{funding: float, investment: float, growth: float, realized_stock: float,
     *               realized_dividends: float, unrealized: float, unrealized_accounts: array,
     *               intermediary: float, explained: float, explained_pct: float|null, gap: float}
     */
    public static function build(
        array $sourceAccounts,
        array $intermediaryAccounts,
        array $investmentAccounts,
        array $gainsPerYear,
        array $dividendsPerYear,
        array $eurRatesPerYear
    ): array
    {
        $funding = self::_sum($sourceAccounts, 'balance_in_eur');
        $investment = self::_sum($investmentAccounts, 'balance_in_eur');
        $intermediary = self::_sum($intermediaryAccounts, 'balance_in_eur');
        $realizedStock = self::_realizedEur($gainsPerYear, $eurRatesPerYear, 'total_gain_in_account_currency');
        $realizedDividends = self::_realizedEur(
            $dividendsPerYear,
            $eurRatesPerYear,
            'total_dividend_in_account_currency'
        );
        $unrealizedAccounts = self::_unrealizedAccounts($investmentAccounts);
        $unrealized = array_sum(array_column($unrealizedAccounts, 'gain_eur'));

        $growth = $investment + $funding;
        $explained = $realizedStock + $realizedDividends + $unrealized - $intermediary;
        $gap = $growth - $explained;

        return [
            'funding'             => $funding,
            'investment'          => $investment,
            'growth'              => $growth,
            'realized_stock'      => $realizedStock,
            'realized_dividends'  => $realizedDividends,
            'unrealized'          => $unrealized,
            'unrealized_accounts' => $unrealizedAccounts,
            'intermediary'        => $intermediary,
            'explained'           => $explained,
            'explained_pct'       => abs($growth) >= 0.01 ? $explained / $growth * 100 : null,
            'gap'                 => $gap,
        ];
    }

    private static function _sum(array $rows, string $key): float
    {
        return (float) array_sum(array_map(fn ($row) => (float) ($row[$key] ?? 0), $rows));
    }

    /**
     * The Gains Per Year total of one amount (stock gains or dividends): each account's amount converted at that
     * year's rate, like the card does.
     */
    private static function _realizedEur(array $perYear, array $eurRatesPerYear, string $amountKey): float
    {
        $total = 0.0;
        foreach ($perYear as $year => $accounts) {
            foreach ($accounts as $accountId => $symbols) {
                $rate = $eurRatesPerYear[$year][$accountId]['exchange_rate'] ?? null;
                foreach ($symbols as $data) {
                    $amount = (float) ($data[$amountKey] ?? 0);
                    $total += $rate !== null ? $amount * $rate : $amount;
                }
            }
        }
        return $total;
    }

    /**
     * The open positions' Gain per investment account (account currency and EUR at today's rate), for the accounts
     * that have one.
     *
     * @return array<int, array{name: string, display_code: string, gain: float, gain_eur: float}>
     */
    private static function _unrealizedAccounts(array $investmentAccounts): array
    {
        $accounts = [];
        foreach ($investmentAccounts as $accountId => $row) {
            $gain = (float) ($row['total_change'] ?? 0);
            if ($gain == 0) {
                continue;
            }
            $rate = $row['exchange_rate'] ?? null;
            $accounts[$accountId] = [
                'name'         => $row['accountModel']->name,
                'display_code' => $row['accountModel']->currency->display_code,
                'gain'         => $gain,
                'gain_eur'     => $rate !== null ? $gain * $rate : $gain,
            ];
        }
        return $accounts;
    }
}
