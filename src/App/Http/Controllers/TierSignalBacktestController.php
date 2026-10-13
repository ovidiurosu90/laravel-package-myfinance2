<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Services\TierSignalBacktestService;

/**
 * Signal backtest report (phase 4): how symbols did against VUSA.AS after each tier signal (rare high, rare low,
 * deteriorating), replayed on the user's stored tier history without look-ahead. One parameter cell at a time
 * (?rare_share=, ?consistency=, ?from=; ?refresh=1 skips the hour-long cache); the command
 * finance:tier-signal-backtest --grid prints every cell.
 */
class TierSignalBacktestController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $rare = $request->query('rare_share');
        $cons = $request->query('consistency');
        $from = $request->query('from');

        $report = (new TierSignalBacktestService())->report(
            (int) auth()->user()->id,
            is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null,
            in_array((int) $rare, TierSignalBacktestService::GRID_RARE_SHARE, true) ? (float) $rare : null,
            in_array((int) $cons, TierSignalBacktestService::GRID_CONSISTENCY, true) ? (float) $cons : null,
            (bool) $request->query('refresh')
        );

        return view('myfinance2::peakproximityalerts.crud.backtest', ['report' => $report]);
    }
}
