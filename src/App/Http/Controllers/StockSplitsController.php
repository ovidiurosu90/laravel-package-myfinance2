<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Services\ApplySplitService;
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
     * Store a new split and apply it to open trades and active alerts.
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
