<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Services\DipBuyingBacktestService;

/**
 * Self-validation backtest report for the Dip Buying Plan (spec section 5): replays the user's own
 * trades through the shared ladder engine and shows, per drawdown episode, what they actually did
 * vs what the ladder would have done, alongside the stay-invested and monthly-DCA baselines.
 */
class DipBuyingBacktestController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the backtest report. Optional ?from=Y-m-d, ?pool=N, ?min_drop=N and ?drop_mode= query
     * overrides (min_drop is the minimum drawdown that counts as a drop, drop_mode is the axis it is
     * measured on: effective, change or vusa).
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId  = (int) auth()->user()->id;
        $from    = $request->query('from');
        $pool    = $request->query('pool');
        $minDrop = $request->query('min_drop');
        $mode    = $request->query('drop_mode');

        $service   = new DipBuyingBacktestService();
        $minDropF  = $minDrop !== null && is_numeric($minDrop) ? (float) $minDrop : null;
        $report    = $service->build(
            $userId,
            $from ?: null,
            $pool !== null && is_numeric($pool) ? (float) $pool : null,
            $minDropF,
            $mode ?: null
        );
        $dipChart = $service->chartContext($userId, $from ?: null, $minDropF, $mode ?: null);

        return view('myfinance2::dipbuyingalerts.crud.backtest', [
            'report'   => $report,
            'dipChart' => $dipChart,
        ]);
    }
}
