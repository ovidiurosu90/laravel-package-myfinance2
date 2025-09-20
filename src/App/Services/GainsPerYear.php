<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

class GainsPerYear
{
    /**
     * @return array(year => array(account => array(symbol => array(totalsData))))
     */
    public function handle(): ?array
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

            $previousTotalAmount =
                $groupedItems[$accountId][$symbol]['price_per_share']
                * $groupedItems[$accountId][$symbol]['quantity'];
            $previousPricePerShare =
                $groupedItems[$accountId][$symbol]['price_per_share'];
            $previousQuantity = $groupedItems[$accountId][$symbol]['quantity'];

            $currentYear = $trade->timestamp->format('Y');
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
                        //   ($previousTotalAmount - $principleAmount + $trade->fee)
                        //    / ($previousQuantity - $currentQuantity);
                    }

                    $currentGain = $principleAmount -
                        ($currentQuantity * $previousPricePerShare) - $trade->fee;
                    $groupedItems[$accountId][$symbol]['quantity']
                        -= $currentQuantity;
                    $groupedItems[$accountId][$symbol]
                        ['total_gain_in_account_currency'] += $currentGain;

                    // Populate sells per year
                    if ($currentYear < $firstTradingYear) {
                        $firstTradingYear = $currentYear;
                    }
                    if (empty($sellsPerYear[$currentYear])) {
                        $sellsPerYear[$currentYear] = [];
                    }
                    if (empty($sellsPerYear[$currentYear][$accountId])) {
                        $sellsPerYear[$currentYear][$accountId] = [];
                    }
                    if (empty($sellsPerYear[$currentYear][$accountId][$symbol])) {
                        $sellsPerYear[$currentYear][$accountId][$symbol] = [
                            'accountModel' => $trade->accountModel,
                            'total_gain_in_account_currency' => 0,
                        ];
                    }

                    $sellsPerYear[$currentYear][$accountId][$symbol]
                        ['total_gain_in_account_currency'] =
                            $groupedItems[$accountId][$symbol]
                                ['total_gain_in_account_currency'];

                    for ($i = $firstTradingYear; $i < $currentYear; $i++) {
                        // Reduce the gain from past years
                        if (!empty($sellsPerYear[$i][$accountId][$symbol]
                                        ['total_gain_in_account_currency'])
                        ) {
                            $sellsPerYear[$currentYear][$accountId][$symbol]
                                ['total_gain_in_account_currency'] -=
                                    $sellsPerYear[$i][$accountId][$symbol]
                                        ['total_gain_in_account_currency'];
                        }
                    }

                    break;
                default:
                    LOG::warning("Unknown trade action " . $trade->action);
            }
            /*
            if ($symbol == 'EZJ.L') {
                LOG::debug("trade $currentYear $accountId $symbol: ");
                LOG::debug(var_export($groupedItems[$accountId][$symbol], true));
                LOG::debug(var_export($trade->toArray(), true));
            }
            */
        }
        /*
        LOG::debug('sellsPerYear: ');
        LOG::debug(var_export($sellsPerYear, true));
        LOG::debug('groupedItems: ');
        LOG::debug(var_export($groupedItems, true));
        */

        // return $groupedItems;
        return $sellsPerYear;
    }

}

