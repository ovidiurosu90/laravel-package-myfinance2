<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Withdrawals Service
 *
 * Handles fetching and formatting withdrawals (debits from trading account) for returns calculations.
 */
class ReturnsWithdrawals
{
    /**
     * Get withdrawals (debits from trading account) for a year
     *
     * @param int $accountId The account ID
     * @param int $year The year to get withdrawals for
     * @param Account|null $preloadedAccount Pre-loaded account object (optional, avoids redundant query)
     */
    public function getWithdrawals(int $accountId, int $year, ?Account $preloadedAccount = null): array
    {
        $startDate = "$year-01-01 00:00:00";
        $endDate = "$year-12-31 23:59:59";

        // Only eager load debitAccountModel if we don't have a pre-loaded account
        // Still need creditAccountModel to show destination account name
        $eagerLoad = $preloadedAccount !== null
            ? ['creditAccountModel']
            : ['debitAccountModel', 'creditAccountModel'];

        $ledgerTransactions = LedgerTransaction::with($eagerLoad)
            ->where('debit_account_id', $accountId)
            ->where('type', 'DEBIT')
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp', 'ASC')
            ->get();

        // Set the pre-loaded account on all transactions to avoid lazy loading
        if ($preloadedAccount !== null) {
            foreach ($ledgerTransactions as $transaction) {
                $transaction->setRelation('debitAccountModel', $preloadedAccount);
            }
        }

        $withdrawals = [];
        foreach ($ledgerTransactions as $transaction) {
            // Handle both string and DateTime timestamp
            $timestamp = $transaction->timestamp;
            if (is_string($timestamp)) {
                $timestamp = new \DateTime($timestamp);
            }

            $withdrawals[] = [
                'date' => $timestamp->format('Y-m-d'),
                'amount' => $transaction->amount,
                'fee' => $transaction->fee,
                'description' => $transaction->description,
                'toAccount' => $transaction->creditAccountModel->name ?? 'Unknown',
                'formatted' => MoneyFormat::get_formatted_balance(
                    $transaction->debitAccountModel->currency->display_code,
                    $transaction->amount
                ) . ($transaction->fee > 0
                    ? ' (fee: ' . MoneyFormat::get_formatted_fee(
                        $transaction->debitAccountModel->currency->display_code,
                        $transaction->fee
                    ) . ')'
                    : ''
                ),
            ];
        }

        return $withdrawals;
    }
}

