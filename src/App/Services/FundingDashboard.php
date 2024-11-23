<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;

class FundingDashboard
{
    /**
     * Execute the job.
     *
     * @return array
     */
    public function handle()
    {
        $balances = [];
        $items = [];
        $itemsOrder = [];
        $sortedItems = [];

        $ledgerTransactions = LedgerTransaction::orderBy('timestamp')->get();

        foreach ($ledgerTransactions as $transaction) {
            switch($transaction->type) {
                case 'DEBIT':
                    // Update balances
                    $balancesKey = $transaction->getDebitAccount();
                    if (!isset($balances[$balancesKey])) {
                        $balances[$balancesKey] = 0;
                    }
                    $balances[$balancesKey] -= $transaction->amount;
                    $balances[$balancesKey] -= $transaction->fee;

                    // Add item
                    $items[$transaction->id] = [
                        'debit_transaction' => $transaction,
                        'balances'          => $balances,
                    ];
                    $itemsOrder[] = $transaction->id;

                    break;
                case 'CREDIT':
                    // Update balances
                    $balancesKey = $transaction->getCreditAccount();
                    if (!isset($balances[$balancesKey])) {
                        $balances[$balancesKey] = 0;
                    }
                    $balances[$balancesKey] += $transaction->amount;
                    $balances[$balancesKey] -= $transaction->fee;

                    // Add item
                    $itemsKey = 'ID-' . $transaction->id;
                    if (!$transaction->parent_id || // Orphan credit = missing parent
                        !isset($items[$transaction->parent_id]) // Lost credit = has parent but not found
                    ) {
                        $items[$itemsKey] = [];
                        $itemsOrder[] = $itemsKey;
                    } else {
                        $itemsKey = $transaction->parent_id;
                    }

                    $items[$itemsKey]['credits'][] = [
                        'credit_transaction' => $transaction,
                        'balances'           => $balances,
                    ];

                    break;
                default:
                    LOG::warning("Unknown transaction type " . $transaction->type);
            }
        }

        foreach ($itemsOrder as $itemKey) {
            $debitTransaction = !empty($items[$itemKey]['debit_transaction']) ?
                                $items[$itemKey]['debit_transaction'] : null;
            if (empty($items[$itemKey]['credits'])) {
                $sortedItems[] = [
                    'debit_transaction'  => $debitTransaction,
                    'credit_transaction' => null,
                    'balances'           => array_merge(
                        array_combine(array_keys($balances), array_fill(0, count(array_keys($balances)), 0)),
                        $items[$itemKey]['balances']
                    ),
                    'tooltip'            => '',
                ];
                continue;
            }

            foreach ($items[$itemKey]['credits'] as $itemCredit) {
                $creditTransaction = $itemCredit['credit_transaction'];
                $tooltip = '';

                if ($debitTransaction && $debitTransaction->exchange_rate != $creditTransaction->exchange_rate) {
                    $tooltip .= sprintf('Debit exchange rate is different than Credit exchange rate ' .
                        '(%.4f != %.4f). ', $debitTransaction->exchange_rate, $creditTransaction->exchange_rate);
                }
                if ($debitTransaction && 1 < abs(abs($creditTransaction->amount) -
                    abs($debitTransaction->amount) * $debitTransaction->exchange_rate)
                ) {
                    $tooltip .= sprintf('Calculated credit amount is different than credit amount ' .
                        '(%.2f != %.2f). ', abs($debitTransaction->amount) * $debitTransaction->exchange_rate,
                        abs($creditTransaction->amount));
                }

                $sortedItems[] = [
                    'debit_transaction'  => $debitTransaction,
                    'credit_transaction' => $creditTransaction,
                    'balances'           => array_merge(
                        array_combine(array_keys($balances), array_fill(0, count(array_keys($balances)), 0)),
                        $itemCredit['balances']
                    ),
                    'tooltip'            => $tooltip,
                ];
            }
        }

        return [
            'items'    => $sortedItems,
            'balances' => $balances,
        ];
    }

    /**
     * @param string $debitCurrency
     * @param string $creditCurrency
     * @param array  $estimate (exchange_rate, amount, fee)
     * @return array (debit_transactionId => array(currencyExchangesData))
     */
    public function getCurrencyExchanges($debitCurrency = 'USD', $creditCurrency = 'EUR', $estimate = null)
    {
        // Initialization
        $intent = $debitCurrency . $creditCurrency;
        $currencyExchanges = [
            ($debitCurrency . $creditCurrency) => [],
            ($creditCurrency . $debitCurrency) => [],
        ];
        $currencyBalances = [
            ($debitCurrency . $creditCurrency) => [$debitCurrency => 0, $creditCurrency => 0],
            ($creditCurrency . $debitCurrency) => [$debitCurrency => 0, $creditCurrency => 0],
        ];

        $fundingEntries = $this->handle();
        foreach ($fundingEntries['items'] as $item) {
            if (empty($item['debit_transaction']) || empty($item['credit_transaction'])
                || $item['debit_transaction']->getCurrency() == $item['credit_transaction']->getCurrency()
            ) {
                continue;
            }
            $debitTransaction = $item['debit_transaction'];
            $creditTransaction = $item['credit_transaction'];
            // LOG::debug($debitTransaction->amount . ' ' . $debitTransaction->getCurrency() . ' -> ' . $creditTransaction->amount . ' ' . $creditTransaction->getCurrency());

            $key = $debitTransaction->getCurrency() . $creditTransaction->getCurrency();
            $keyReverse = $creditTransaction->getCurrency() . $debitTransaction->getCurrency();

            if ($key == $intent && $currencyBalances[$keyReverse][$debitTransaction->getCurrency()]
                && $currencyBalances[$keyReverse][$creditTransaction->getCurrency()]
            ) {
                $computedGain = $this->computeGain([
                    'debit_amount'    => $debitTransaction->amount,
                    'debit_currency'  => $debitTransaction->getCurrency(),
                    'debit_fee'       => $debitTransaction->fee,
                    'credit_amount'   => $creditTransaction->amount,
                    'credit_currency' => $creditTransaction->getCurrency(),
                    'credit_fee'      => $creditTransaction->fee,
                ], $currencyBalances);

                $currencyExchanges[$key][$item['debit_transaction']->id] = [
                    'debit_transaction' => $item['debit_transaction'],
                    'cost'              => $computedGain['cost'],
                    'amount'            => $computedGain['credit_amount'],
                    'gain'              => $computedGain['gain'],
                ];
            }

            $currencyBalances[$key][$debitTransaction->getCurrency()] -=
                $debitTransaction->amount + $debitTransaction->fee;
            $currencyBalances[$key][$creditTransaction->getCurrency()] +=
                $creditTransaction->amount - $creditTransaction->fee;
            // LOG::debug('currencyBalances'); LOG::debug($currencyBalances);
        }

        $estimatedGain = null;
        if (!empty($estimate)) {
            $estimatedGain = $this->computeGain([
                'debit_amount'    => $estimate['amount'],
                'debit_currency'  => $debitCurrency,
                'debit_fee'       => $estimate['fee'],
                'credit_amount'   => $estimate['amount'] * $estimate['exchange_rate'],
                'credit_currency' => $creditCurrency,
                'credit_fee'      => 0,
            ], $currencyBalances);
        }

        // LOG::debug('currencyBalances'); LOG::debug($currencyBalances);
        // LOG::debug('currencyExchanges'); LOG::debug($currencyExchanges);
        return [
            'currency_exchanges' => $currencyExchanges[$intent],
            'currency_balances'  => $currencyBalances[$intent],
            'estimated_gain'     => $estimatedGain,
        ];
    }

    /**
     * @param $item array(debit_amount, debit_currency, debit_fee, credit_amount, credit_currency, credit_fee)
     * @param $currencyBalances array(credit_currency.debit_currency => (credit_currency => balance, debit_currency => balance), ...)
     *
     * @return array (amount, credit_amount, cost, gain)
     */
    public static function computeGain($item, $currencyBalances)
    {
        $keyReverse = $item['credit_currency'] . $item['debit_currency'];

        if ($item['debit_amount'] > abs($currencyBalances[$keyReverse][$item['debit_currency']])) {
            // LOG::debug('----- Calculate gain on what I exchanged already, not on the full amount');
            $amount       = abs($currencyBalances[$keyReverse][$item['debit_currency']])
                            + $item['debit_fee'];
            $creditAmount = $amount * $item['credit_amount'] / $item['debit_amount']
                            - $item['credit_fee'];
            $cost         = abs($currencyBalances[$keyReverse][$item['credit_currency']]);
        } else {
            $amount       = $item['debit_amount'] + $item['debit_fee'];
            $creditAmount = $item['credit_amount'] - $item['credit_fee'];
            $cost         = ($item['debit_amount'] + $item['debit_fee'])
                            * abs($currencyBalances[$keyReverse][$item['credit_currency']])
                            / abs($currencyBalances[$keyReverse][$item['debit_currency']]);
        }
        $gain = $creditAmount - $cost;

        // LOG::debug(sprintf("----- I paid %.2f %s for %.2f %s", $cost, $item['credit_currency'], $amount, $item['debit_currency']));
        // LOG::debug(sprintf("----- I got %.2f %s => gained %.2f %s", $creditAmount, $item['credit_currency'], $gain, $item['credit_currency']));

        return [
            'amount'        => $amount,
            'credit_amount' => $creditAmount,
            'cost'          => $cost,
            'gain'          => $gain,
        ];
    }
}

