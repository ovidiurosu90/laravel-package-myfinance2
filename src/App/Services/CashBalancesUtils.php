<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\CashBalance;
use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

class CashBalancesUtils
{
    /**
     * @var bool
     */
    private $_withUser = true;

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

    public function __construct(int $accountId,
        bool $withUser = true, \DateTimeInterface $date = null)
    {
        $this->setWithUser($withUser);

        $this->_populateCashBlance($accountId, $date);
        $this->_populateAccount($accountId);
    }

    public function setWithUser(bool $withUser = true)
    {
        if (!$withUser
            && php_sapi_name() !== 'cli' // in browser we have 'apache2handler'
        ) {
            abort(403, 'Access denied in Account Model');
        }

        $this->_withUser = $withUser;
    }

    public function _populateCashBlance(int $accountId,
        \DateTimeInterface $date = null)
    {
        $queryBuilder = CashBalance::with('accountModel');
        if (!$this->_withUser) {
            $queryBuilder = CashBalance::with('accountModelNoUser')
                ->withoutGlobalScope(AssignedToUserScope::class);
        }

        $this->_cashBalance = $queryBuilder
            ->where('account_id', $accountId)
            ->where('timestamp', '<', !empty($date) ? $date : \DB::raw('NOW()'))
            ->orderBy('timestamp', 'DESC')
            ->first();

        if (!$this->_withUser
            && !empty($this->_cashBalance)
            // && empty($this->_cashBalance->accountModel)
            && !empty($this->_cashBalance->accountModelNoUser)
        ) {
            $this->_cashBalance->accountModel = $this->_cashBalance
                ->accountModelNoUser;
        }
        // Log::debug($this->_cashBalance);
    }

    public function _populateAccount(int $accountId)
    {
        if (!empty($this->_cashBalance)) {
            $this->_account = $this->_cashBalance->accountModel;
            return;
        }

        $queryBuilder = Account::with('currency');
        if (!$this->_withUser) {
            $queryBuilder = Account::with('currencyNoUser')
                ->withoutGlobalScope(AssignedToUserScope::class);
        }

        $this->_account = $queryBuilder->findOrFail($accountId);

        if (!$this->_withUser
            && !empty($this->_account)
            // && empty($this->_account->currency)
            && !empty($this->_account->currencyNoUser)
        ) {
            $this->_account->currency = $this->_account->currencyNoUser;
        }
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

