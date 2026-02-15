<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Dividend;

class DividendsPerYear
{
    /**
     * Group dividends by year, account, and symbol.
     *
     * @return array year => accountId => symbol => totalsData
     */
    public function handle(): array
    {
        $dividends = Dividend::with('accountModel', 'dividendCurrencyModel')
            ->orderBy('timestamp')
            ->get();

        $perYear = [];

        foreach ($dividends as $dividend) {
            $year = $dividend->timestamp->format('Y');
            $accountId = $dividend->accountModel->id;
            $symbol = $dividend->symbol;

            if (empty($perYear[$year])) {
                $perYear[$year] = [];
            }
            if (empty($perYear[$year][$accountId])) {
                $perYear[$year][$accountId] = [];
            }
            if (empty($perYear[$year][$accountId][$symbol])) {
                $perYear[$year][$accountId][$symbol] = [
                    'accountModel' => $dividend->accountModel,
                    'total_dividend_in_account_currency' => 0,
                ];
            }

            //NOTE We use the inversed exchange rate
            $perYear[$year][$accountId][$symbol]
                ['total_dividend_in_account_currency'] +=
                    $dividend->amount / $dividend->exchange_rate
                    - $dividend->fee;
        }

        return $perYear;
    }
}

