<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Services\ApplySplitService;
use ovidiuro\myfinance2\App\Services\RevertSplitService;
use ovidiuro\myfinance2\App\Http\Requests\StoreStockSplit;
use ovidiuro\myfinance2\Mail\SplitApplied;

class StockSplitsController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the list of recorded stock splits.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $items = StockSplit::orderBy('split_date', 'desc')
            ->orderBy('symbol')
            ->get();

        return view('myfinance2::splits.crud.dashboard', [
            'items' => $items,
        ]);
    }

    /**
     * Show the form for recording a new split.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $watchlistSymbols = WatchlistSymbol::orderBy('symbol')->get();

        return view('myfinance2::splits.crud.create', [
            'watchlistSymbols' => $watchlistSymbols,
            'symbol'           => old('symbol', ''),
            'split_date'       => old('split_date', ''),
            'ratio_numerator'  => old('ratio_numerator', ''),
            'ratio_denominator' => old('ratio_denominator', 1),
            'notes'            => old('notes', ''),
        ]);
    }

    /**
     * Store a new split and apply it to all trades and active alerts.
     *
     * @param StoreStockSplit $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreStockSplit $request)
    {
        if ($request->isDuplicate()) {
            return redirect()->route('myfinance2::stock-splits.create')
                ->withInput()
                ->withErrors([
                    'symbol' => trans('myfinance2::splits.flash-messages.duplicate-split', [
                        'symbol' => strtoupper(trim($request->symbol)),
                        'date'   => $request->split_date,
                    ]),
                ]);
        }

        $data  = $request->fillData();
        $split = new StockSplit($data);

        $service = new ApplySplitService();
        $summary = $service->apply($split);

        $this->_sendSplitAppliedEmail($split, $summary);

        $message = trans('myfinance2::splits.flash-messages.split-recorded', [
            'ratio'  => $split->getRatioLabel(),
            'symbol' => $split->symbol,
            'trades' => $summary['trades_updated'],
            'alerts' => $summary['alerts_adjusted'],
        ]);

        return redirect()->route('myfinance2::stock-splits.index')
            ->with('success', $message);
    }

    /**
     * Return a JSON preview of how many trades/alerts will be affected by a revert.
     * Used to populate the confirmation modal before the user commits.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function revertPreview(int $id)
    {
        $split = StockSplit::findOrFail($id);

        if ($split->isReverted()) {
            return response()->json(['error' => 'Already reverted'], 422);
        }

        $service = new RevertSplitService();
        $preview = $service->preview($split);

        return response()->json([
            'trades_found'    => $preview['trades_found'],
            'alerts_found'    => $preview['alerts_found'],
            'trades_original' => $split->trades_updated,
            'alerts_original' => $split->alerts_adjusted,
        ]);
    }

    /**
     * Revert a previously applied split (restores all trades and active alerts).
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function revert(int $id)
    {
        $split = StockSplit::findOrFail($id);

        if ($split->isReverted()) {
            return redirect()->route('myfinance2::stock-splits.index')
                ->with('error', trans('myfinance2::splits.flash-messages.already-reverted'));
        }

        $service = new RevertSplitService();
        $summary = $service->revert($split);

        $message = trans('myfinance2::splits.flash-messages.split-reverted', [
            'ratio'  => $split->getRatioLabel(),
            'symbol' => $split->symbol,
            'trades' => $summary['trades_reverted'],
            'alerts' => $summary['alerts_reverted'],
        ]);

        return redirect()->route('myfinance2::stock-splits.index')
            ->with('success', $message);
    }

    /**
     * Reapply a previously reverted split (re-runs ApplySplitService on the same record).
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function reapply(int $id)
    {
        $split = StockSplit::findOrFail($id);

        if (!$split->isReverted()) {
            return redirect()->route('myfinance2::stock-splits.index')
                ->with('error', trans('myfinance2::splits.flash-messages.not-reverted'));
        }

        $split->reverted_at = null;

        $service = new ApplySplitService();
        $summary = $service->apply($split);

        $this->_sendSplitAppliedEmail($split, $summary);

        $message = trans('myfinance2::splits.flash-messages.split-reapplied', [
            'ratio'  => $split->getRatioLabel(),
            'symbol' => $split->symbol,
            'trades' => $summary['trades_updated'],
            'alerts' => $summary['alerts_adjusted'],
        ]);

        return redirect()->route('myfinance2::stock-splits.index')
            ->with('success', $message);
    }

    /**
     * Send the split-applied notification email.
     *
     * @param StockSplit $split
     * @param array      $summary  Output of ApplySplitService::apply()
     *
     * @return void
     */
    private function _sendSplitAppliedEmail(StockSplit $split, array $summary): void
    {
        $emailTo = config('alerts.email_to') ?: auth()->user()?->email;
        if (empty($emailTo)) {
            return;
        }

        try {
            Mail::to($emailTo)->send(new SplitApplied($split, $summary));
        } catch (\Throwable $e) {
            Log::error('StockSplitsController: split-applied email failed: ' . $e->getMessage());
        }
    }
}
