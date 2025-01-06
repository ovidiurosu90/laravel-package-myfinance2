<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Dividend;

class DividendsDashboard
{
    /**
     * Execute the job.
     *
     * @return array (accountId => array(symbol => array(totalsData)))
     */
    public function handle()
    {
        $dividends = Dividend::with('accountModel', 'dividendCurrencyModel')->get();
        $groupedItems = [];

        foreach ($dividends as $dividend) {
            $key1 = $dividend->accountModel->id;
            $key2 = $dividend->symbol;
            if (empty($groupedItems[$key1])) {
                $groupedItems[$key1] = [];
            }
            if (empty($groupedItems[$key1][$key2])) {
                $groupedItems[$key1][$key2] = [
                    'accountModel' => $dividend->accountModel,
                    'total_gain_in_account_currency' => 0,
                ];
            }
            //NOTE We use the inversed exchange rate
            $groupedItems[$key1][$key2]['total_gain_in_account_currency'] +=
                $dividend->amount * 1 / $dividend->exchange_rate - $dividend->fee;
        }

        return $groupedItems;
    }
}

