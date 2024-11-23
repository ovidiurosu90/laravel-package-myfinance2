<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\PositionsDashboard;

class PositionsController extends MyFinance2Controller
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
        $service = new PositionsDashboard();
        $data = $service->handle(); // returns an associative array with items grouped by account and account data
        return view('myfinance2::positions.dashboard', $data);
    }
}

