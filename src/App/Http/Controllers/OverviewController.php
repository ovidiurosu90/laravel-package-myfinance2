<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use ovidiuro\myfinance2\App\Services\CurrencyUtils;
use ovidiuro\myfinance2\App\Services\OverviewDashboard;
use ovidiuro\myfinance2\App\Services\Returns\ReturnsOverview;

class OverviewController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the overview dashboard.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): \Illuminate\Contracts\View\View
    {
        $overviewService = new OverviewDashboard();
        $currencyUtilsService = new CurrencyUtils(true);
        $returnsOverviewService = new ReturnsOverview();

        $data = $overviewService->handle();
        $data['currencyUtilsService'] = $currencyUtilsService;
        $data['overviewData'] = $returnsOverviewService->handle(
            Auth::user()->id
        );

        return view('myfinance2::overview.dashboard', $data);
    }
}

