<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use Illuminate\Support\Facades\Auth;
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
     */
    public function getDeposits(int $accountId, int $year): array
    {
        $startDate = "$year-01-01 00:00:00";
        $endDate = "$year-12-31 23:59:59";

        $ledgerTransactions = LedgerTransaction::with('debitAccountModel', 'creditAccountModel')
            ->where('credit_account_id', $accountId)
            ->where('type', 'CREDIT')
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->where('user_id', Auth::id())
            ->orderBy('timestamp', 'ASC')
            ->get();

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

