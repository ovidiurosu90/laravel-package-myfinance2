<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\CashBalance;
use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Dividend;

class CashBalancesUtils
{
    /**
     * @var CashBalance
     */
    private $_cashBalance;

    /**
     * @var Account
     */
    private $_account;

    /**
     * @var \DateTime
     */
    private $_lastOperationTimestamp;

    /**
     * @param int $accountId The account ID
     * @param \DateTimeInterface|null $date The date to get cash balance for
     * @param Account|null $account Pre-loaded account object (optional, avoids redundant query)
     */
    public function __construct(int $accountId, \DateTimeInterface $date = null, Account $account = null)
    {
        // If account is pre-loaded, skip eager loading in cash balance query
        $this->_populateCashBlance($accountId, $date, $account !== null);
        $this->_populateAccount($accountId, $account);

        // Set the accountModel relationship on CashBalance to avoid lazy loading
        // when CashBalance methods internally access $this->accountModel->currency
        if ($this->_cashBalance !== null && $this->_account !== null) {
            $this->_cashBalance->setRelation('accountModel', $this->_account);
        }
    }

    /**
     * @param int $accountId The account ID
     * @param \DateTimeInterface|null $date The date to get cash balance for
     * @param bool $skipEagerLoading Skip eager loading accountModel (when account is pre-loaded)
     */
    public function _populateCashBlance(int $accountId, \DateTimeInterface $date = null, bool $skipEagerLoading = false)
    {
        $query = CashBalance::where('account_id', $accountId)
            ->where('timestamp', '<', !empty($date) ? $date : \DB::raw('NOW()'))
            ->orderBy('timestamp', 'DESC');

        // Only eager load accountModel if we don't have a pre-loaded account
        if (!$skipEagerLoading) {
            $query->with('accountModel');
        }

        $this->_cashBalance = $query->first();
        // Log::debug($this->_cashBalance);
    }

    /**
     * @param int $accountId The account ID
     * @param Account|null $preloadedAccount Pre-loaded account object (optional)
     */
    public function _populateAccount(int $accountId, Account $preloadedAccount = null)
    {
        // Use pre-loaded account if provided (highest priority)
        if ($preloadedAccount !== null) {
            $this->_account = $preloadedAccount;
            return;
        }

        // Use eager-loaded account from cash balance if available
        if (!empty($this->_cashBalance) && $this->_cashBalance->relationLoaded('accountModel')) {
            $this->_account = $this->_cashBalance->accountModel;
            return;
        }

        $this->_account = Account::with('currency')->findOrFail($accountId);
        // Log::debug($this->_account);
    }

    public function getLastCashBalance()
    {
        return $this->_cashBalance;
    }

    public function getLastOperationTimestamp()
    {
        return $this->_lastOperationTimestamp;
    }

    public function getAmount()
    {
        if (empty($this->_cashBalance)) {
            return null;
        }
        return $this->_cashBalance->amount;
    }

    public function getFormattedAmount()
    {
        if (empty($this->_cashBalance)) {
            return 'unknown';
        }
        return $this->_cashBalance->getFormattedAmount();
    }

    public function getFormattedCurrency()
    {
        return $this->_account->currency->display_code;
    }

    public function getFormattedDetails()
    {
        if (empty($this->_cashBalance)) {
            return '';
        }
        $data = $this->_cashBalance->timestamp . ' ' .
            $this->_cashBalance->description;

        return '<span class="small text-secondary">' . $data . '</span>';
    }

    /**
     * @return array<string>
     */
    public function getCashBalances(string $timestamp): array
    {
        $cashBalances = [];

        // Compute start balance
        // Log::debug($this->_cashBalance);
        if (!empty($this->_cashBalance) && !empty($this->_cashBalance->amount)) {
            $cashBalances[] = 'start ' . round($this->_cashBalance->amount, 2);
            $this->_lastOperationTimestamp = $this->_cashBalance->timestamp;
        }


        // Get all debit ledger transactions
        $ledgerTransactionsDebitWhere = [
            ['debit_account_id', '=', $this->_account->id],
            ['type', '=', 'DEBIT'],
        ];
        if (!empty($timestamp)) {
            $ledgerTransactionsDebitWhere[] = ['timestamp', '<', $timestamp];
        }
        if (!empty($this->_cashBalance) && !empty($this->_cashBalance->timestamp)) {
            $ledgerTransactionsDebitWhere[] = [
                'timestamp', '>=', $this->_cashBalance->timestamp
            ];
        }
        $ledgerTransactionsDebit = LedgerTransaction
            ::with('debitAccountModel', 'creditAccountModel')
            ->where($ledgerTransactionsDebitWhere)
            ->orderBy('timestamp', 'ASC')
            ->get();
        // Log::debug($ledgerTransactionsDebit);
        foreach ($ledgerTransactionsDebit as $ledgerTransactionDebit) {
            $cashBalances[] = '-' . round($ledgerTransactionDebit->amount, 2)
                . ($ledgerTransactionDebit->fee != 0.0 ?
                    ' -' . round($ledgerTransactionDebit->fee, 2) : '')
                . ' funding to '
                . $ledgerTransactionDebit->creditAccountModel->name . '('
                . $ledgerTransactionDebit->creditAccountModel->currency
                    ->display_code
                . ')'
            ;

            if ($ledgerTransactionDebit->timestamp >
                $this->_lastOperationTimestamp
            ) {
                $this->_lastOperationTimestamp = $ledgerTransactionDebit->timestamp;
            }
        }


        // Get all credit ledger transactions
        $ledgerTransactionsCreditWhere = [
            ['credit_account_id', '=', $this->_account->id],
            ['type', '=', 'CREDIT'],
        ];
        if (!empty($timestamp)) {
            $ledgerTransactionsCreditWhere[] = ['timestamp', '<', $timestamp];
        }
        if (!empty($this->_cashBalance) && !empty($this->_cashBalance->timestamp)) {
            $ledgerTransactionsCreditWhere[] = [
                'timestamp', '>=', $this->_cashBalance->timestamp
            ];
        }
        $ledgerTransactionsCredit = LedgerTransaction
            ::with('debitAccountModel', 'creditAccountModel')
            ->where($ledgerTransactionsCreditWhere)
            ->orderBy('timestamp', 'ASC')
            ->get();
        // Log::debug($ledgerTransactionsCredit);
        foreach ($ledgerTransactionsCredit as $ledgerTransactionCredit) {
            $cashBalances[] = '+' . round($ledgerTransactionCredit->amount, 2)
                . ($ledgerTransactionCredit->fee != 0.0 ?
                    ' -' . round($ledgerTransactionCredit->fee, 2) : '')
                . ' funding from '
                . $ledgerTransactionCredit->debitAccountModel->name . '('
                . $ledgerTransactionCredit->debitAccountModel->currency
                    ->display_code
                . ')'
            ;

            if ($ledgerTransactionCredit->timestamp >
                $this->_lastOperationTimestamp
            ) {
                $this->_lastOperationTimestamp =
                    $ledgerTransactionCredit->timestamp;
            }
        }


        // Get all trades
        $tradesWhere = [
            ['account_id', '=', $this->_account->id],
        ];
        if (!empty($timestamp)) {
            $tradesWhere[] = ['timestamp', '<', $timestamp];
        }
        if (!empty($this->_cashBalance) && !empty($this->_cashBalance->timestamp)) {
            $tradesWhere[] = [
                'timestamp', '>=', $this->_cashBalance->timestamp
            ];
        }
        $trades = Trade::with('accountModel', 'tradeCurrencyModel')
            ->where($tradesWhere)
            ->where(function ($query)
            {
                $query->where('is_transfer', false)
                    ->orWhereNull('is_transfer');
            })
            ->orderBy('timestamp', 'ASC')
            ->get();
        // Log::debug($trades);
        foreach ($trades as $trade) {
            $amount = $trade->quantity * $trade->unit_price;
            if ($trade->accountModel->currency->iso_code !=
                $trade->tradeCurrencyModel->iso_code
            ) {
                $amount /= $trade->exchange_rate;
            }
            $sign = '?';
            switch($trade->action) {
                case 'BUY':
                    $sign = '-';
                    break;
                case 'SELL':
                    $sign = '+';
                    break;
                default:
                    Log::error('Invalid trade action: ' . $trade->action);
                    continue 2; // continue the foreach loop
            }
            $cashBalances[] = $sign . round($amount, 2) .
                ($trade->fee != 0.0 ?
                    ' -' . round($trade->fee, 2) : '') .
                ' ' . strtolower($trade->action) . ' ' . $trade->symbol
            ;

            if ($trade->timestamp > $this->_lastOperationTimestamp) {
                $this->_lastOperationTimestamp = $trade->timestamp;
            }
        }


        // Get all dividends
        $dividendsWhere = [
            ['account_id', '=', $this->_account->id],
        ];
        if (!empty($timestamp)) {
            $dividendsWhere[] = ['timestamp', '<', $timestamp];
        }
        if (!empty($this->_cashBalance) && !empty($this->_cashBalance->timestamp)) {
            $dividendsWhere[] = [
                'timestamp', '>=', $this->_cashBalance->timestamp
            ];
        }
        $dividends = Dividend::with('accountModel', 'dividendCurrencyModel')
            ->where($dividendsWhere)
            ->orderBy('timestamp', 'ASC')
            ->get();
        // Log::debug($dividends);
        foreach ($dividends as $dividend) {
            $amount = $dividend->amount;
            if ($dividend->accountModel->currency->iso_code !=
                $dividend->dividendCurrencyModel->iso_code
            ) {
                $amount /= $dividend->exchange_rate;
            }
            $cashBalances[] = '+' . round($amount, 2) .
                ($dividend->fee != 0.0 ?
                    ' -' . round($dividend->fee, 2) : '') .
                ' dividend ' . $dividend->symbol
            ;

            if ($dividend->timestamp > $this->_lastOperationTimestamp) {
                $this->_lastOperationTimestamp = $dividend->timestamp;
            }
        }


        return $cashBalances;
    }
}

