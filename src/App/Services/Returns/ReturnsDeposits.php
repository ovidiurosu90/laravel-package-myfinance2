<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Deposits Service
 *
 * Handles fetching and formatting deposits (credits to trading account) for returns calculations.
 */
class ReturnsDeposits
{
    /**
     * Get deposits (credits to trading account) for a year
     *
     * @param int $accountId The account ID
     * @param int $year The year to get deposits for
     * @param Account|null $preloadedAccount Pre-loaded account object (optional, avoids redundant query)
     */
    public function getDeposits(int $accountId, int $year, ?Account $preloadedAccount = null): array
    {
        $startDate = "$year-01-01 00:00:00";
        $endDate = "$year-12-31 23:59:59";

        // Only eager load creditAccountModel if we don't have a pre-loaded account
        // Still need debitAccountModel to show source account name
        $eagerLoad = $preloadedAccount !== null
            ? ['debitAccountModel']
            : ['debitAccountModel', 'creditAccountModel'];

        $ledgerTransactions = LedgerTransaction::with($eagerLoad)
            ->where('credit_account_id', $accountId)
            ->where('type', 'CREDIT')
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp', 'ASC')
            ->get();

        // Set the pre-loaded account on all transactions to avoid lazy loading
        if ($preloadedAccount !== null) {
            foreach ($ledgerTransactions as $transaction) {
                $transaction->setRelation('creditAccountModel', $preloadedAccount);
            }
        }

        $deposits = [];
        foreach ($ledgerTransactions as $transaction) {
            // Handle both string and DateTime timestamp
            $timestamp = $transaction->timestamp;
            if (is_string($timestamp)) {
                $timestamp = new \DateTime($timestamp);
            }

            $deposits[] = [
                'date' => $timestamp->format('Y-m-d'),
                'amount' => $transaction->amount,
                'fee' => $transaction->fee,
                'description' => $transaction->description,
                'fromAccount' => $transaction->debitAccountModel->name ?? 'Unknown',
                'formatted' => MoneyFormat::get_formatted_balance(
                    $transaction->creditAccountModel->currency->display_code,
                    $transaction->amount
                ) . ($transaction->fee > 0
                    ? ' (fee: ' . MoneyFormat::get_formatted_fee(
                        $transaction->creditAccountModel->currency->display_code,
                        $transaction->fee
                    ) . ')'
                    : ''
                ),
            ];
        }

        return $deposits;
    }
}

