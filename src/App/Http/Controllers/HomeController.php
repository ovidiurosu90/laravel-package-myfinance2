<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Services\FundingDashboard;
use ovidiuro\myfinance2\App\Services\Positions;
use ovidiuro\myfinance2\App\Services\DividendsDashboard;
use ovidiuro\myfinance2\App\Services\GainsPerYear;
use ovidiuro\myfinance2\App\Services\CurrencyUtils;

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
        $positionsService = new Positions();
        $dividendsService = new DividendsDashboard();
        $gainsPerYearService = new GainsPerYear();
        $currencyUtilsService = new CurrencyUtils(true);

        $fundingData = $fundingService->handle(); // items & balances
        $currencyExchangesData = $fundingService->getCurrencyExchanges();

        // items grouped by account and account data
        $positionsData = $positionsService->handle();

        // array with items grouped by account, symbol
        $dividendsData = $dividendsService->handle();

        // items grouped by year, account, symbol
        $gainsPerYear = $gainsPerYearService->handle();

        $ledgerCurrencies = Currency::where('is_ledger_currency', 1)->get();

        return view('myfinance2::home.dashboard', [
            'balances'          => $fundingData['balances'],
            'accounts'          => $fundingData['accounts'],
            'openPositions'     => $positionsData['accountData'],
            'dividends'         => $dividendsData,
            'gainsPerYear'      => $gainsPerYear,
            'currencyExchanges' => $currencyExchangesData['currency_exchanges'],
            'currencyBalances'  => $currencyExchangesData['currency_balances'],
            'ledgerCurrencies'  => $ledgerCurrencies,
            'currencyUtilsService' => $currencyUtilsService,
        ]);
    }
}

