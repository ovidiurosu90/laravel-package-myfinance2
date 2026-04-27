<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;

class DisableAutoFxLedgerService
{
    public function createLedgerTransactions(array $tradeData, int $pairedAccountId): void
    {
        $exchangeRate = (float) $tradeData['exchange_rate'];
        if ($exchangeRate <= 0) {
            throw new \InvalidArgumentException("exchange_rate must be > 0, got {$exchangeRate}");
        }

        // Proceeds in trade currency (e.g. USD) — used for the CREDIT side
        $tradeAmount = (float) $tradeData['quantity'] * (float) $tradeData['unit_price'];

        // Matches Trade::getFormattedPrincipleAmountInAccountCurrency(): quantity * unit_price / rate
        $accountAmount = $tradeAmount / $exchangeRate;

        $description = $this->buildDescription($tradeData, $tradeAmount, $accountAmount);

        $commonData = [
            'timestamp'         => $tradeData['timestamp'],
            'debit_account_id'  => (int) $tradeData['account_id'],
            'credit_account_id' => $pairedAccountId,
            'exchange_rate'     => $tradeData['exchange_rate'],
            'fee'               => 0,
            'description'       => $description,
        ];

        // DEBIT: shown in debit account currency (EUR) → amount in EUR
        $debitTx = LedgerTransaction::create(array_merge($commonData, [
            'type'      => 'DEBIT',
            'amount'    => $accountAmount,
            'parent_id' => null,
        ]));

        // CREDIT: shown in credit account currency (USD) → amount in USD
        LedgerTransaction::create(array_merge($commonData, [
            'type'      => 'CREDIT',
            'amount'    => $tradeAmount,
            'parent_id' => $debitTx->id,
        ]));
    }

    private function buildDescription(
        array $tradeData,
        float $tradeAmount,
        float $accountAmount
    ): string
    {
        $tradeCurrency = Currency::findOrFail((int) $tradeData['trade_currency_id']);
        $account = Account::with('currency')->findOrFail((int) $tradeData['account_id']);

        $action = $this->getActionVerb($tradeData['action']);
        $symbol = $tradeData['symbol'];
        $proceeds = number_format($tradeAmount, 2);
        $converted = number_format($accountAmount, 2);
        $fee = number_format(abs((float) $tradeData['fee']), 2);
        $tradeIso = $tradeCurrency->iso_code;
        $accountIso = $account->currency->iso_code;

        return "NOTE: No auto FX — {$action} {$symbol} got {$proceeds} {$tradeIso}"
             . " ({$converted} {$accountIso}); trade fee -{$fee} {$accountIso} (ledger fee: 0)";
    }

    private function getActionVerb(string $action): string
    {
        $verbs = [
            'SELL' => 'selling',
            'BUY'  => 'buying',
        ];
        return $verbs[$action] ?? strtolower($action);
    }
}
