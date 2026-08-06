<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\DipBuyingBacktestService;
use ovidiuro\myfinance2\App\Services\DipBuyingPlanService;
use ovidiuro\myfinance2\App\Services\DipBuyingPresenter;
use ovidiuro\myfinance2\App\Services\MoversService;
use ovidiuro\myfinance2\App\Services\Positions;
use ovidiuro\myfinance2\App\Services\PositionsReconciliationService;
use ovidiuro\myfinance2\App\Services\StaleQuoteService;

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

        // Movers data is only shown for the current date (not historical views)
        $data['moversData'] = empty($dateInput)
            ? (new MoversService())->getMovers(auth()->id())
            : null;

        // Stale-data safety net: warn when a market is open but its live prices have stopped
        // advancing (frozen Yahoo feed). Only meaningful on the live view; the historical view
        // deliberately shows dated closes. detect() never throws, so this cannot break the page.
        $data['staleQuotes'] = empty($dateInput)
            ? (new StaleQuoteService())->detect($data['quotes'] ?? [])
            : [];

        // Dip Buying Plan panel: only on the live view, and only when the user has opted in (the
        // engine returns null otherwise). The whole panel reads one render-ready view model built in
        // the BE (DipBuyingPresenter), shared with the daily email. Failures must never break /positions.
        $data['dipPresent']   = null;
        $data['dipCurrent']   = null;
        $data['dipFirstBand'] = null;
        if (empty($dateInput)) {
            try {
                $userId = (int) auth()->id();
                $plan   = (new DipBuyingPlanService())->buildForUser($userId);
                if ($plan !== null) {
                    // The regime breakdown, current episode and first band come from one shared
                    // computation, so the panel, the /dip-buying-alerts/backtest page and the email
                    // can never show different figures.
                    $detail = (new DipBuyingBacktestService())->panelDetail($userId, $plan);

                    $data['dipPresent'] = DipBuyingPresenter::make(
                        $plan, $detail['current'], $detail['firstBand'], $detail['regime']
                    );
                    // The shared current-episode card is included with the raw episode + first band.
                    $data['dipCurrent']   = $detail['current'];
                    $data['dipFirstBand'] = $detail['firstBand'];
                }
            } catch (\Throwable $e) {
                Log::warning('Dip Buying Plan panel skipped: ' . $e->getMessage());
            }
        }

        // Reconciliation safety net: cross-check the live position rows against the shown
        // account and User Overview summaries, only on the live view (the historical view does
        // not persist stats). Must never break /positions, so failures are swallowed.
        $data['reconAlerts'] = [];
        if (empty($dateInput)) {
            try {
                $userId = auth()->id() !== null ? (int) auth()->id() : null;
                $data['reconAlerts'] = (new PositionsReconciliationService())->reconcile(
                    $data['groupedItems'] ?? [],
                    $data['accountData'] ?? [],
                    $userId
                );
            } catch (\Throwable $e) {
                Log::warning('Positions reconciliation panel skipped: ' . $e->getMessage());
            }
        }

        return view('myfinance2::positions.dashboard', $data);
    }
}

