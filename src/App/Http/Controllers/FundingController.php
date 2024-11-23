<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\FundingDashboard;

class FundingController extends MyFinance2Controller
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
        $service = new FundingDashboard();
        $data = $service->handle(); // returns an associative array with items & balances
        return view('myfinance2::funding.dashboard', $data);
    }
}

