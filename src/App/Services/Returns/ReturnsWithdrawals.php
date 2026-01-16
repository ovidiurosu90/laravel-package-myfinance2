<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use Illuminate\Support\Facades\Auth;
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
     */
    public function getWithdrawals(int $accountId, int $year): array
    {
        $startDate = "$year-01-01 00:00:00";
        $endDate = "$year-12-31 23:59:59";

        $ledgerTransactions = LedgerTransaction::with('debitAccountModel', 'creditAccountModel')
            ->where('debit_account_id', $accountId)
            ->where('type', 'DEBIT')
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->where('user_id', Auth::id())
            ->orderBy('timestamp', 'ASC')
            ->get();

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

