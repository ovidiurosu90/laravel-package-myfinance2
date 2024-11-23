<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Trade;

class PositionsDashboard
{
    /**
     * Execute the job.
     *
     * @param $extraSymbols array(MSFT, MA, NFLX, ...)
     *
     * @return array (items => array(positionData))
     */
    public function handle(array $extraSymbols = array())
    {
        $trades = Trade::where('status', 'OPEN')->orderBy('timestamp')->get();
        $symbols = []; // for getting quotes for trades
        $positions = []; // positions grouped by account & symbol
        $accountData = []; // currency & totals grouped by account
        $exchangeRateData = []; // exchange rates used for positions

        // Compute purchase data
        foreach ($trades as $trade) {
            $account = $trade->getAccount();
            $accountData[$account] = [
                'account'          => $trade->account,
                'currency'         => $trade->account_currency,
                'currency_display' => MoneyFormat::get_currency_display($trade->account_currency),
            ];
            $exchangeRateIndex = $trade->account_currency . $trade->trade_currency; // EURUSD
            if (empty($exchangeRateData[$exchangeRateIndex])) {
                $exchangeRateData[$exchangeRateIndex] = [
                    'account_currency' => $trade->account_currency,
                    'trade_currency'   => $trade->trade_currency,
                ];
            }

            $symbol = $trade->symbol;
            // if ($symbol == 'EZJ.L') {
            //     LOG::debug("trade"); LOG::debug(var_export($trade->toArray(), true));
            // }
            $symbols[$symbol] = 1;

            if (empty($positions[$account])) {
                $positions[$account] = [];
            }
            if (empty($positions[$account][$symbol])) {
                $positions[$account][$symbol] = [
                    'account_currency'          => $trade->account_currency,
                    'trade_currency'            => $trade->trade_currency,
                    'quantity'                  => 0,
                    'cost_in_account_currency'  => 0,
                    'cost_in_trade_currency'    => 0,
                    'quantity2'                 => 0,
                    'cost2_in_account_currency' => 0,
                    'cost2_in_trade_currency'   => 0,
                ];
            } else {
                // Check if trade_currency changed
                if ($trade->trade_currency != $positions[$account][$symbol]['trade_currency']) {
                    LOG::error("Inconsistent trade currency for account $account | symbol $symbol");
                    return null;
                }
            }

            //NOTE We use the inversed exchange rate
            $principleAmount = 1 / $trade->exchange_rate * $trade->quantity * $trade->unit_price;
            $principleAmountInTradeCurrency = $trade->quantity * $trade->unit_price;

            switch($trade->action) {
                case 'BUY':
                    $positions[$account][$symbol]['quantity'] += $trade->quantity;
                    $positions[$account][$symbol]['cost_in_account_currency'] += $principleAmount + $trade->fee;
                    $positions[$account][$symbol]['cost_in_trade_currency'] += $principleAmountInTradeCurrency +
                        ($trade->fee * $trade->exchange_rate);

                    // We compute cost2 that won't be affected by the sell actions
                    // The other cost has gains factored in
                    // (if you sold half your stocks for double the value the remaining cost becomes 0)
                    $positions[$account][$symbol]['quantity2'] += $trade->quantity;
                    $positions[$account][$symbol]['cost2_in_account_currency'] += $principleAmount + $trade->fee;
                    $positions[$account][$symbol]['cost2_in_trade_currency'] += $principleAmountInTradeCurrency +
                        ($trade->fee * $trade->exchange_rate);

                    break;
                case 'SELL':
                    $positions[$account][$symbol]['quantity'] -= $trade->quantity;
                    $positions[$account][$symbol]['cost_in_account_currency'] -= $principleAmount - $trade->fee;
                    $positions[$account][$symbol]['cost_in_trade_currency'] -= $principleAmountInTradeCurrency -
                        ($trade->fee * $trade->exchange_rate);

                    break;
                default:
                    LOG::warning("Unknown trade action " . $trade->action);
            }
        }

        $financeUtils = new FinanceUtils();

        $exchangeRateData = $financeUtils->getExchangeRates($exchangeRateData);
        // LOG::debug('-- exhangeRateData: ' . print_r($exchangeRateData, true));

        $quotes = $financeUtils->getQuotes(array_merge(array_keys($symbols), $extraSymbols));
        // LOG::debug('-- quotes 106: ' . print_r($quotes, true));

        $currenciesMapping = config('general.currencies_mapping');

        // Add market data
        $items = [];
        foreach ($positions as $account => $accountPositions) {
            if (empty($items[$account])) {
                $items[$account] = [];
            }
            if (empty($totals[$account])) {
                $accountData[$account] = array_merge($accountData[$account], [
                    'total_change'                 => 0,
                    'total_change_formatted'       => '',
                    'total_cost'                   => 0,
                    'total_cost_formatted'         => '',
                    'total_market_value'           => 0,
                    'total_market_value_formatted' => '',
                ]);
            }

            foreach ($accountPositions as $symbol => $position) {
                $tradeCurrency = $quotes[$symbol]['currency'];
                if (!empty($currenciesMapping[$tradeCurrency])) {
                    $tradeCurrency = $currenciesMapping[$tradeCurrency];
                }
                if ($tradeCurrency != $position['trade_currency']) {
                    LOG::error("Inconsistent quote trade currency for account $account | symbol $symbol");
                    return null;
                }

                $exchangeRate = 1;
                if ($position['trade_currency'] != $position['account_currency']) {
                    $exchangeRateIndex = $position['account_currency'] . $position['trade_currency'];
                    if (empty($exchangeRateData[$exchangeRateIndex]) ||
                        empty($exchangeRateData[$exchangeRateIndex]['exchange_rate'])
                    ) {
                        LOG::error("Exchange rate not found for account_currency " . $position['account_currency'] .
                                   " / trade_currency " . $position['trade_currency'] . "; exchangeRateData: " .
                                   print_r($exchangeRateData, true));
                        return null;
                    }
                    $exchangeRate = $exchangeRateData[$exchangeRateIndex]['exchange_rate'];
                }

                // We compute cost2 that won't be affected by the sell actions
                // cost2 and quantity2 only measure BUY actions
                $position['cost2_in_account_currency'] = $position['quantity'] *
                    $position['cost2_in_account_currency'] /  $position['quantity2'];
                $position['cost2_in_trade_currency'] = $position['quantity'] *
                    $position['cost2_in_trade_currency'] /  $position['quantity2'];
                $hasCost2 = round($position['cost_in_account_currency'], 4) !=
                            round($position['cost2_in_account_currency'], 4); #NOTE comparing floats

                $marketValueInAccountCurrency    = $quotes[$symbol]['price'] * $position['quantity'] / $exchangeRate;
                $orverallChangeInAccountCurrency = $marketValueInAccountCurrency
                                                   - $position['cost_in_account_currency'];
                $orverallChange2InAccountCurrency = $marketValueInAccountCurrency
                                                   - $position['cost2_in_account_currency'];

                $items[$account][] = [
                    'account'                              => $account,
                    'symbol'                               => $symbol,
                    'symbol_name'                          => $quotes[$symbol]['name'],
                    'quantity'                             => $position['quantity'],
                    'cost_in_account_currency'             => MoneyFormat::get_formatted_balance(
                        $position['account_currency'], $position['cost_in_account_currency']),
                    'cost2_in_account_currency'            => !$hasCost2 ? '' :
                        MoneyFormat::get_formatted_balance($position['account_currency'],
                            $position['cost2_in_account_currency']),
                    'market_value_in_account_currency'     => MoneyFormat::get_formatted_balance(
                        $position['account_currency'], $marketValueInAccountCurrency),
                    'trade_currency'                       => $tradeCurrency,
                    'exchange_rate'                        => $exchangeRate,
                    'average_unit_cost_in_trade_currency'  => !$position['quantity'] ? '' :
                        MoneyFormat::get_formatted_balance($tradeCurrency,
                        $position['cost_in_trade_currency'] / $position['quantity']),
                    'average_unit_cost2_in_trade_currency'  => !$position['quantity'] || !$hasCost2 ? '' :
                        MoneyFormat::get_formatted_balance($tradeCurrency,
                        $position['cost2_in_trade_currency'] / $position['quantity']),
                    'current_unit_price_in_trade_currency' => MoneyFormat::get_formatted_balance(
                        $tradeCurrency, $quotes[$symbol]['price']),
                    'quote_timestamp'                      => $quotes[$symbol]['quote_timestamp']
                                                              ->format(trans('myfinance2::general.datetime-format')),
                    'day_change_in_account_currency'       => !$position['quantity'] ? '' :
                        MoneyFormat::get_formatted_gain($position['account_currency'], $position['quantity']
                        * $quotes[$symbol]['day_change'] / $exchangeRate),
                    'day_change_in_percentage'             => !$position['quantity'] ? '' :
                        MoneyFormat::get_formatted_gain_percentage($quotes[$symbol]['day_change_percentage']),
                    'overall_change_in_account_currency'   => MoneyFormat::get_formatted_gain(
                        $position['account_currency'], $orverallChangeInAccountCurrency),
                    'overall_change2_in_account_currency'   => !$hasCost2 ? '' : MoneyFormat::get_formatted_gain(
                        $position['account_currency'], $orverallChange2InAccountCurrency),
                    'overall_change_in_percentage'         => !$position['quantity'] ? '' :
                        MoneyFormat::get_formatted_gain_percentage(
                        -100 + $marketValueInAccountCurrency * 100 / $position['cost_in_account_currency']),
                    'overall_change2_in_percentage'         => !$position['quantity'] || !$hasCost2 ? '' :
                        MoneyFormat::get_formatted_gain_percentage(
                        -100 + $marketValueInAccountCurrency * 100 / $position['cost2_in_account_currency']),

                    'marketUtils' => $quotes[$symbol]['marketUtils'],
                ];

                $accountData[$account]['total_change'] += $orverallChangeInAccountCurrency;
                $accountData[$account]['total_cost'] += $position['cost_in_account_currency'];
                $accountData[$account]['total_market_value'] += $marketValueInAccountCurrency;
            }
            $accountData[$account]['total_change_formatted']       = MoneyFormat::get_formatted_gain(
                $position['account_currency'], $accountData[$account]['total_change']);
            $accountData[$account]['total_cost_formatted']         = MoneyFormat::get_formatted_balance(
                $position['account_currency'], $accountData[$account]['total_cost']);
            $accountData[$account]['total_market_value_formatted'] = MoneyFormat::get_formatted_balance(
                $position['account_currency'], $accountData[$account]['total_market_value']);

            $accountData[$account]['cash'] = new CashBalancesUtils($accountData[$account]['account'],
                $accountData[$account]['currency']);
        }

        return [
            'groupedItems' => $items,
            'accountData'  => $accountData,
            'quotes'       => $quotes,
        ];
    }

    /**
     * @return array(year => array(account => array(symbol => array(totalsData))))
     */
    public function getGainsPerYear()
    {
        $trades = Trade::orderBy('timestamp')->get();
        $groupedItems = [];

        // Initialize sells per year
        $firstTradingYear = date('Y');
        $sellsPerYear = [];

        foreach ($trades as $trade) {
            $account = $trade->getAccount();
            $symbol = $trade->symbol;
            if (empty($groupedItems[$account])) {
                $groupedItems[$account] = [];
            }
            if (empty($groupedItems[$account][$symbol])) {
                $groupedItems[$account][$symbol] = [
                    'account_currency' => $trade->account_currency,
                    'total_gain_in_account_currency' => 0,
                    'price_per_share' => 0,
                    'quantity' => 0,
                ];
            }

            $year = $trade->timestamp->format('Y');
            $previousTotalAmount = $groupedItems[$account][$symbol]['price_per_share'] * $groupedItems[$account][$symbol]['quantity'];
            $previousPricePerShare = $groupedItems[$account][$symbol]['price_per_share'];
            $previousQuantity = $groupedItems[$account][$symbol]['quantity'];
            $currentQuantity = $trade->quantity;

            //NOTE We use the inversed exchange rate
            $principleAmount = 1 / $trade->exchange_rate * $trade->quantity * $trade->unit_price;

            //NOTE The signs are inversed compared to above (here we compute gain, while above we compute cost)
            switch($trade->action) {
                case 'BUY':
                    $groupedItems[$account][$symbol]['price_per_share'] = ($previousTotalAmount + $principleAmount + $trade->fee) / ($previousQuantity + $currentQuantity);
                    $groupedItems[$account][$symbol]['quantity'] += $currentQuantity;

                    break;
                case 'SELL':

                    if ($previousQuantity - $currentQuantity == 0) {
                        $groupedItems[$account][$symbol]['price_per_share'] = 0;
                    } else if ($previousQuantity - $currentQuantity < 0) {
                        LOG::error("Inconsistent quantity for account $account | symbol $symbol");
                        return null;
                    } else {
                        //NOTE We don't need to recompute price per share on sell
                        // $groupedItems[$account][$symbol]['price_per_share'] = ($previousTotalAmount - $principleAmount + $trade->fee) / ($previousQuantity - $currentQuantity);
                    }

                    $currentGain = $principleAmount - ($currentQuantity * $previousPricePerShare) - $trade->fee;
                    $groupedItems[$account][$symbol]['quantity'] -= $currentQuantity;
                    $groupedItems[$account][$symbol]['total_gain_in_account_currency'] += $currentGain;

                    // Populate sells per year
                    if ($year < $firstTradingYear) {
                        $firstTradingYear = $year;
                    }
                    if (empty($sellsPerYear[$year])) {
                        $sellsPerYear[$year] = [];
                    }
                    if (empty($sellsPerYear[$year][$account])) {
                        $sellsPerYear[$year][$account] = [];
                    }
                    if (empty($sellsPerYear[$year][$account][$symbol])) {
                        $sellsPerYear[$year][$account][$symbol] = [
                            'account_currency' => $trade->account_currency,
                            'total_gain_in_account_currency' => 0,
                        ];
                    }
                    $sellsPerYear[$year][$account][$symbol]['total_gain_in_account_currency'] =
                        $groupedItems[$account][$symbol]['total_gain_in_account_currency'];

                    for ($i = $firstTradingYear; $i < $year; $i++) {
                        // Reduce the gain from past years
                        if (!empty($sellsPerYear[$i][$account][$symbol]['total_gain_in_account_currency'])) {
                            $sellsPerYear[$year][$account][$symbol]['total_gain_in_account_currency'] -=
                                $sellsPerYear[$i][$account][$symbol]['total_gain_in_account_currency'];
                        }
                    }

                    break;
                default:
                    LOG::warning("Unknown trade action " . $trade->action);
            }
            /*
            if ($symbol == 'EZJ.L') {
                LOG::debug("trade $year $account $symbol: "); LOG::debug(var_export($groupedItems[$account][$symbol], true)); LOG::debug(var_export($trade->toArray(), true));
            }
            */
        }
        // LOG::debug('sellsPerYear: '); LOG::debug(var_export($sellsPerYear, true));
        // LOG::debug('groupedItems: '); LOG::debug(var_export($groupedItems, true));

        // return $groupedItems;
        return $sellsPerYear;
    }

}

