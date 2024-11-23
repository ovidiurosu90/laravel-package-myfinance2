<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\FundingDashboard;
use ovidiuro\myfinance2\App\Services\PositionsDashboard;
use ovidiuro\myfinance2\App\Services\DividendsDashboard;

class HomeController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the dashboard
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $fundingService = new FundingDashboard();
        $fundingData = $fundingService->handle(); // items & balances
        $currencyExchangesData = $fundingService->getCurrencyExchanges();

        $positionsService = new PositionsDashboard();
        $positionsData = $positionsService->handle(); // items grouped by account and account data
        $gainsPerYear = $positionsService->getGainsPerYear(); // items grouped by year, account, symbol

        $dividendsService = new DividendsDashboard();
        $dividendsData = $dividendsService->handle(); // array with items grouped by account, symbol

        return view('myfinance2::home.dashboard', [
            'balances'          => $fundingData['balances'],
            'openPositions'     => $positionsData['accountData'],
            'gainsPerYear'      => $gainsPerYear,
            'dividends'         => $dividendsData,
            'currencyExchanges' => $currencyExchangesData['currency_exchanges'],
            'currencyBalances'  => $currencyExchangesData['currency_balances'],

            'debitCurrencies'   => config('general.ledger_currencies'),
            'creditCurrencies'  => config('general.ledger_currencies'),
        ]);
    }
}

