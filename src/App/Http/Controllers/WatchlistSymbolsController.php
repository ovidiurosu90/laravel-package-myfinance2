<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Services\WatchlistSymbolFormFields;
use ovidiuro\myfinance2\App\Services\WatchlistSymbolsDashboard;
use ovidiuro\myfinance2\App\Http\Requests\StoreWatchlistSymbol;
use ovidiuro\myfinance2\App\Http\Requests\UpdateWatchlistSymbol;

class WatchlistSymbolsController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $service = new WatchlistSymbolsDashboard();
        $items = $service->handle(); // returns an associative array (symbol => array(quote_data))
        return view('myfinance2::watchlistsymbols.crud.dashboard', ['items' => $items]);
    }

    /**
     * Show the form for creating an item.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $service = new WatchlistSymbolFormFields();
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::watchlistsymbols.crud.create', $data);
    }

    /**
     * Store a newly created item.
     *
     * @param \App\Http\Requests\StoreWatchlistSymbol $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWatchlistSymbol $request)
    {
        $data = $request->fillData();
        $item = WatchlistSymbol::create($data);

        return redirect()->route('myfinance2::watchlist-symbols.index')
            ->with('success', trans('myfinance2::general.flash-messages.item-created',
                ['type' => 'Watchlist Symbol', 'id' => $item->id]));
    }

    /**
     * Edit the specified item.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $service = new WatchlistSymbolFormFields($id);
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::watchlistsymbols.crud.edit', $data);
    }

    /**
     * Update the specified item.
     *
     * @param \App\Http\Requests\UpdateWatchlistSymbol $request
     * @param int                            $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWatchlistSymbol $request, $id)
    {
        $data = $request->fillData($id);
        $item = WatchlistSymbol::findOrFail($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::watchlist-symbols.index')
            ->with('success', trans('myfinance2::general.flash-messages.item-updated',
                ['type' => 'Watchlist Symbol', 'id' => $item->id]));
    }

    /**
     * Remove the specified item.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $item = WatchlistSymbol::findOrFail($id);
        $item->delete();

        return redirect(route('myfinance2::watchlist-symbols.index'))
                ->with('success', trans('myfinance2::general.flash-messages.item-deleted',
                    ['type' => 'Watchlist Symbol', 'id' => $item->id]));
    }
}

