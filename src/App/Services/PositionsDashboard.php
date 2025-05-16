<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Account;

class PositionsDashboard
{
    /**
     * Execute the job.
     *
     * @param $extraSymbols array(MSFT, MA, NFLX, ...)
     *
     * @return array (items => array(positionData))
     */
    public function handle(array $extraSymbols = array()): ?array
    {
        /*
        $accounts = Account::with('currency')->get();
        $accountsDictionary = [];
        foreach ($accounts as $account) {
            $accountsDictionary[$account->id] = $account;
        }
        */
        $return = [
            'groupedItems' => [],
            'accountData'  => [],
            'quotes'       => [],
        ];

        $trades = Trade::with('accountModel', 'tradeCurrencyModel')
            ->where('status', 'OPEN')
            ->orderBy('timestamp')
            ->get();
        $symbols = []; // for getting quotes for trades
        $positions = []; // positions grouped by account & symbol
        $accountData = []; // currency & totals grouped by account
        $exchangeRateData = []; // exchange rates used for positions

        // Compute purchase data
        foreach ($trades as $trade) {
            $accountId = $trade->accountModel->id;
            $accountData[$accountId] = [
                'accountModel'     => $trade->accountModel,
            ];
            $exchangeRateIndex = $trade->accountModel->currency->iso_code .
                $trade->tradeCurrencyModel->iso_code; // EURUSD
            if (empty($exchangeRateData[$exchangeRateIndex])
                && $trade->accountModel->currency->iso_code !=
                        $trade->tradeCurrencyModel->iso_code
            ) {
                $exchangeRateData[$exchangeRateIndex] = [
                    'account_currency' => $trade->accountModel->currency->iso_code,
                    'trade_currency'   => $trade->tradeCurrencyModel->iso_code,
                ];
            }

            $symbol = $trade->symbol;
            // if ($symbol == 'EZJ.L') {
            //     LOG::debug("trade");
            //     LOG::debug(var_export($trade->toArray(), true));
            // }
            $symbols[$symbol] = 1;

            if (empty($positions[$accountId])) {
                $positions[$accountId] = [];
            }
            if (empty($positions[$accountId][$symbol])) {
                $positions[$accountId][$symbol] = [
                    'accountModel'              => $trade->accountModel,
                    'tradeCurrencyModel'        => $trade->tradeCurrencyModel,
                    'quantity'                  => 0,
                    'cost_in_account_currency'  => 0,
                    'cost_in_trade_currency'    => 0,
                    'quantity2'                 => 0,
                    'cost2_in_account_currency' => 0,
                    'cost2_in_trade_currency'   => 0,
                    'trades'                    => [],
                    'unit_price'                => $trade->unit_price,
                ];
            } else {
                // Check if trade_currency changed
                if ($trade->tradeCurrencyModel->iso_code !=
                    $positions[$accountId][$symbol]['tradeCurrencyModel']->iso_code
                ) {
                    LOG::error("Inconsistent trade currency for accountId: "
                        . "$accountId, symbol: $symbol");
                    return $return;
                }
            }

            //NOTE We use the inversed exchange rate
            $principleAmount = 1 /
                $trade->exchange_rate * $trade->quantity * $trade->unit_price;
            $principleAmountInTradeCurrency = $trade->quantity * $trade->unit_price;

            switch($trade->action) {
                case 'BUY':
                    $positions[$accountId][$symbol]['quantity'] +=
                        $trade->quantity;
                    $positions[$accountId][$symbol]['cost_in_account_currency'] +=
                        $principleAmount + $trade->fee;
                    $positions[$accountId][$symbol]['cost_in_trade_currency'] +=
                        $principleAmountInTradeCurrency +
                        ($trade->fee * $trade->exchange_rate);

                    // We compute cost2 that won't be affected by the sell actions
                    // The other cost has gains factored in
                    // (if you sold half your stocks for double the value,
                    // the remaining cost becomes 0)
                    $positions[$accountId][$symbol]['quantity2'] +=
                        $trade->quantity;
                    $positions[$accountId][$symbol]['cost2_in_account_currency'] +=
                        $principleAmount + $trade->fee;
                    $positions[$accountId][$symbol]['cost2_in_trade_currency'] +=
                        $principleAmountInTradeCurrency +
                        ($trade->fee * $trade->exchange_rate);

                    break;
                case 'SELL':
                    $positions[$accountId][$symbol]['quantity'] -=
                        $trade->quantity;
                    $positions[$accountId][$symbol]['cost_in_account_currency'] -=
                        $principleAmount - $trade->fee;
                    $positions[$accountId][$symbol]['cost_in_trade_currency'] -=
                        $principleAmountInTradeCurrency -
                        ($trade->fee * $trade->exchange_rate);

                    break;
                default:
                    LOG::warning("Unknown trade action " . $trade->action);
            }

            $positions[$accountId][$symbol]['trades'][] = $trade;
        }

        $financeUtils = new FinanceUtils();

        // LOG::debug("exchangeRateData 131: " . print_r($exchangeRateData, true));
        $exchangeRateData = $financeUtils->getExchangeRates($exchangeRateData);

        $quotes = $financeUtils->getQuotes(
            array_merge(array_keys($symbols), $extraSymbols));

        $currenciesMapping = config('general.currencies_mapping');

        // LOG::debug("positions 150: " . print_r($positions, true));
        // LOG::debug("quotes 151: " . print_r($quotes, true));

        // Add market data
        $items = [];
        foreach ($positions as $accountId => $accountPositions) {
            if (empty($items[$accountId])) {
                $items[$accountId] = [];
            }
            if (empty($totals[$accountId])) {
                $accountData[$accountId] = array_merge($accountData[$accountId], [
                    'total_change'                 => 0,
                    'total_change_formatted'       => '',
                    'total_cost'                   => 0,
                    'total_cost_formatted'         => '',
                    'total_market_value'           => 0,
                    'total_market_value_formatted' => '',
                ]);
            }

            foreach ($accountPositions as $symbol => $position) {
                $isUnlisted = FinanceAPI::isUnlisted($symbol);

                // if (empty($quotes[$symbol])) {
                //     LOG::error("Missing quote data for symbol $symbol");
                //     continue;
                // }

                $tradeCurrency = !$isUnlisted ? $quotes[$symbol]['currency']
                    : $position['tradeCurrencyModel']->iso_code;
                if (!empty($currenciesMapping[$tradeCurrency])) {
                    $tradeCurrency = $currenciesMapping[$tradeCurrency];
                }
                if ($tradeCurrency != $position['tradeCurrencyModel']->iso_code) {
                    LOG::error("Inconsistent quote trade currency for accountId: "
                        . "$accountId, symbol $symbol! tradeCurrency: "
                        . $tradeCurrency . ", positionTradeCurrency: "
                        . $position['tradeCurrencyModel']->iso_code);
                    return $return;
                }

                $exchangeRate = 1;
                if ($position['tradeCurrencyModel']->iso_code !=
                    $position['accountModel']->currency->iso_code
                ) {
                    $exchangeRateIndex =
                        $position['accountModel']->currency->iso_code
                        . $position['tradeCurrencyModel']->iso_code;

                    if (empty($exchangeRateData[$exchangeRateIndex]) ||
                       empty($exchangeRateData[$exchangeRateIndex]['exchange_rate'])
                    ) {
                        LOG::error("Exchange rate not found for account_currency: "
                            . $position['accountModel']->currency->iso_code
                            . " / trade_currency: "
                            . $position['tradeCurrencyModel']->iso_code
                            . ", exchangeRateData: "
                            . print_r($exchangeRateData, true));
                        return $return;
                    }
                    $exchangeRate =
                        $exchangeRateData[$exchangeRateIndex]['exchange_rate'];
                }

                // We compute cost2 that won't be affected by the sell actions
                // cost2 and quantity2 only measure BUY actions
                $position['cost2_in_account_currency'] = $position['quantity'] *
                    $position['cost2_in_account_currency'] / $position['quantity2'];
                $position['cost2_in_trade_currency'] = $position['quantity'] *
                    $position['cost2_in_trade_currency'] / $position['quantity2'];

                #NOTE comparing floats
                $hasCost2 = round($position['cost_in_account_currency'], 4) !=
                            round($position['cost2_in_account_currency'], 4);

                $price = !$isUnlisted ? $quotes[$symbol]['price']
                    : $position['unit_price'];
                $dayChange = !$isUnlisted ? $quotes[$symbol]['day_change'] : 0;
                $dayChangePercentage = !$isUnlisted
                    ? $quotes[$symbol]['day_change_percentage']
                    : 0;
                $symbolName = !$isUnlisted ? $quotes[$symbol]['name'] : $symbol;
                $timestamp = !$isUnlisted ? $quotes[$symbol]['quote_timestamp']
                    : (new \DateTime());
                $marketUtils = !$isUnlisted ? $quotes[$symbol]['marketUtils']
                    : null;

                if ($isUnlisted) {
                    $unlistedFMV = config('trades.unlisted_fmv');
                    if (!empty($unlistedFMV[$symbol])) {
                        if (!empty($unlistedFMV[$symbol]['price'])) {
                            $price = $unlistedFMV[$symbol]['price'];
                        }
                        if (!empty($unlistedFMV[$symbol]['timestamp'])) {
                            $timestamp = new \DateTime(
                                $unlistedFMV[$symbol]['timestamp']);
                        }
                    }
                }

                $marketValueInAccountCurrency = $price
                    * $position['quantity'] / $exchangeRate;
                $orverallChangeInAccountCurrency = $marketValueInAccountCurrency
                    - $position['cost_in_account_currency'];
                $orverallChange2InAccountCurrency = $marketValueInAccountCurrency
                    - $position['cost2_in_account_currency'];

                $items[$accountId][] = [
                    'accountModel'                         =>
                        $position['accountModel'],
                    'tradeCurrencyModel'                   =>
                        $position['tradeCurrencyModel'],
                    'symbol'                               => $symbol,
                    'symbol_name'                          => $symbolName,
                    'quantity'                             => $position['quantity'],
                    'cost_in_account_currency'             =>
                        MoneyFormat::get_formatted_balance(
                            $position['accountModel']->currency->display_code,
                            $position['cost_in_account_currency']),
                    'cost2_in_account_currency'            => !$hasCost2 ? '' :
                        MoneyFormat::get_formatted_balance(
                            $position['accountModel']->currency->display_code,
                            $position['cost2_in_account_currency']),
                    'market_value_in_account_currency'     =>
                        MoneyFormat::get_formatted_balance(
                            $position['accountModel']->currency->display_code,
                            $marketValueInAccountCurrency),
                    'exchange_rate'                        => $exchangeRate,
                    'average_unit_cost_in_trade_currency'  =>
                        !$position['quantity'] ? '' :
                            MoneyFormat::get_formatted_balance(
                                $position['tradeCurrencyModel']->display_code,
                                $position['cost_in_trade_currency']
                                / $position['quantity']),
                    'average_unit_cost2_in_trade_currency'  =>
                        !$position['quantity'] || !$hasCost2 ? '' :
                            MoneyFormat::get_formatted_balance(
                                $position['tradeCurrencyModel']->display_code,
                                $position['cost2_in_trade_currency']
                                / $position['quantity']),
                    'current_unit_price_in_trade_currency' =>
                        MoneyFormat::get_formatted_balance(
                            $position['tradeCurrencyModel']->display_code,
                            $price),
                    'quote_timestamp'                      =>
                        $timestamp
                            ->format(trans('myfinance2::general.datetime-format')),
                    'day_change_in_account_currency'       =>
                        !$position['quantity'] ? '' :
                            MoneyFormat::get_formatted_gain(
                                $position['accountModel']->currency->display_code,
                                $position['quantity']
                                * $dayChange
                                / $exchangeRate),
                    'day_change_in_account_currency_unformatted' =>
                        !$position['quantity'] ? 0 :
                                $position['quantity']
                                * $dayChange
                                / $exchangeRate,
                    'day_change_in_percentage'             =>
                        !$position['quantity'] ? '' :
                            MoneyFormat::get_formatted_gain_percentage(
                                $dayChangePercentage),
                    'day_change_in_percentage_unformatted' =>
                        !$position['quantity'] ? 0 : $dayChangePercentage,
                    'overall_change_in_account_currency'   =>
                        MoneyFormat::get_formatted_gain(
                            $position['accountModel']->currency->display_code,
                            $orverallChangeInAccountCurrency),
                    'overall_change_in_account_currency_unformatted' =>
                        $orverallChangeInAccountCurrency,
                    'overall_change2_in_account_currency'   =>
                        !$hasCost2 ? '' :
                            MoneyFormat::get_formatted_gain(
                            $position['accountModel']->currency->display_code,
                            $orverallChange2InAccountCurrency),
                    'overall_change_in_percentage'         =>
                        !$position['quantity'] ? '' :
                            MoneyFormat::get_formatted_gain_percentage(
                                -100 + $marketValueInAccountCurrency * 100
                                / $position['cost_in_account_currency']),
                    'overall_change_in_percentage_unformatted' =>
                        !$position['quantity'] ? 0 :
                            -100 + $marketValueInAccountCurrency * 100
                            / $position['cost_in_account_currency'],
                    'overall_change2_in_percentage'         =>
                        !$position['quantity'] || !$hasCost2 ? '' :
                            MoneyFormat::get_formatted_gain_percentage(
                                -100 + $marketValueInAccountCurrency * 100
                                / $position['cost2_in_account_currency']),

                    'marketUtils' => $marketUtils,
                    'trades'      => $position['trades'],
                ];

                $accountData[$accountId]['total_change'] +=
                    $orverallChangeInAccountCurrency;
                $accountData[$accountId]['total_cost'] +=
                    $position['cost_in_account_currency'];
                $accountData[$accountId]['total_market_value'] +=
                    $marketValueInAccountCurrency;
            }
            $accountData[$accountId]['total_change_formatted']       =
                MoneyFormat::get_formatted_gain(
                    $position['accountModel']->currency->display_code,
                    $accountData[$accountId]['total_change']);
            $accountData[$accountId]['total_cost_formatted']         =
                MoneyFormat::get_formatted_balance(
                    $position['accountModel']->currency->display_code,
                    $accountData[$accountId]['total_cost']);
            $accountData[$accountId]['total_market_value_formatted'] =
                MoneyFormat::get_formatted_balance(
                    $position['accountModel']->currency->display_code,
                    $accountData[$accountId]['total_market_value']);

            $accountData[$accountId]['cash'] = new CashBalancesUtils($accountId);
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
    public function getGainsPerYear(): ?array
    {
        $trades = Trade::with('accountModel', 'tradeCurrencyModel')
            ->orderBy('timestamp')->get();
        $groupedItems = [];

        // Initialize sells per year
        $firstTradingYear = date('Y');
        $sellsPerYear = [];

        foreach ($trades as $trade) {
            $accountId = $trade->accountModel->id;
            $symbol = $trade->symbol;
            if (empty($groupedItems[$accountId])) {
                $groupedItems[$accountId] = [];
            }
            if (empty($groupedItems[$accountId][$symbol])) {
                $groupedItems[$accountId][$symbol] = [
                    'accountModel' => $trade->accountModel,
                    'total_gain_in_account_currency' => 0,
                    'price_per_share' => 0,
                    'quantity' => 0,
                ];
            }

            $year = $trade->timestamp->format('Y');
            $previousTotalAmount =
                $groupedItems[$accountId][$symbol]['price_per_share']
                * $groupedItems[$accountId][$symbol]['quantity'];
            $previousPricePerShare =
                $groupedItems[$accountId][$symbol]['price_per_share'];
            $previousQuantity = $groupedItems[$accountId][$symbol]['quantity'];
            $currentQuantity = $trade->quantity;

            //NOTE We use the inversed exchange rate
            $principleAmount = 1 / $trade->exchange_rate
                * $trade->quantity * $trade->unit_price;

            //NOTE The signs are inversed compared to above
            // (here we compute gain, while above we compute cost)
            switch($trade->action) {
                case 'BUY':
                    $groupedItems[$accountId][$symbol]['price_per_share'] =
                        ($previousTotalAmount + $principleAmount
                        + $trade->fee) / ($previousQuantity + $currentQuantity);
                    $groupedItems[$accountId][$symbol]['quantity'] +=
                        $currentQuantity;

                    break;
                case 'SELL':

                    if ($previousQuantity - $currentQuantity == 0) {
                        $groupedItems[$accountId][$symbol]['price_per_share'] = 0;
                    } else if ($previousQuantity - $currentQuantity < 0) {
                        LOG::error("Inconsistent quantity for accountId: "
                            . "$accountId, symbol: $symbol");
                        return [];
                    } else {
                        //NOTE We don't need to recompute price per share on sell
                        // $groupedItems[$accountId][$symbol]['price_per_share'] =
                        //  ($previousTotalAmount - $principleAmount + $trade->fee)
                        //  / ($previousQuantity - $currentQuantity);
                    }

                    $currentGain = $principleAmount -
                        ($currentQuantity * $previousPricePerShare) - $trade->fee;
                    $groupedItems[$accountId][$symbol]['quantity'] -=
                        $currentQuantity;
                    $groupedItems[$accountId][$symbol]
                        ['total_gain_in_account_currency'] += $currentGain;

                    // Populate sells per year
                    if ($year < $firstTradingYear) {
                        $firstTradingYear = $year;
                    }
                    if (empty($sellsPerYear[$year])) {
                        $sellsPerYear[$year] = [];
                    }
                    if (empty($sellsPerYear[$year][$accountId])) {
                        $sellsPerYear[$year][$accountId] = [];
                    }
                    if (empty($sellsPerYear[$year][$accountId][$symbol])) {
                        $sellsPerYear[$year][$accountId][$symbol] = [
                            'accountModel' => $trade->accountModel,
                            'total_gain_in_account_currency' => 0,
                        ];
                    }
                    $sellsPerYear[$year][$accountId][$symbol]['total_gain_in_account_currency'] =
                        $groupedItems[$accountId][$symbol]['total_gain_in_account_currency'];

                    for ($i = $firstTradingYear; $i < $year; $i++) {
                        // Reduce the gain from past years
                        if (!empty($sellsPerYear[$i][$accountId][$symbol]['total_gain_in_account_currency'])
                        ) {
                            $sellsPerYear[$year][$accountId][$symbol]['total_gain_in_account_currency'] -=
                                $sellsPerYear[$i][$accountId][$symbol]['total_gain_in_account_currency'];
                        }
                    }

                    break;
                default:
                    LOG::warning("Unknown trade action " . $trade->action);
            }
            /*
            if ($symbol == 'EZJ.L') {
                LOG::debug("trade $year $account $symbol: ");
                LOG::debug(var_export($groupedItems[$accountId][$symbol], true));
                LOG::debug(var_export($trade->toArray(), true));
            }
            */
        }
        // LOG::debug('sellsPerYear: ');
        // LOG::debug(var_export($sellsPerYear, true));
        // LOG::debug('groupedItems: ');
        // LOG::debug(var_export($groupedItems, true));

        // return $groupedItems;
        return $sellsPerYear;
    }

}

