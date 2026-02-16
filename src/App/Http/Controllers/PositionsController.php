<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\Positions;

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
        $service = new Positions();

        $date = null;
        $dateInput = request()->input('date');
        if (!empty($dateInput)) {
            try {
                $date = new \DateTime($dateInput . ' 23:59:59');
                $service->setIncludeClosedTrades(true);
                $service->setPersistStats(false);
            } catch (\Exception $e) {
                Log::warning('Invalid date parameter for positions: ' . $dateInput);
            }
        }

        // array with items grouped by account and account data
        $data = $service->handle($date);
        $data['selectedDate'] = $dateInput;

        return view('myfinance2::positions.dashboard', $data);
    }
}

