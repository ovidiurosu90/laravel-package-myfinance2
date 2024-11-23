<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\TradeFormFields;
use ovidiuro\myfinance2\App\Http\Requests\StoreTrade;
use ovidiuro\myfinance2\App\Http\Requests\UpdateTrade;
use ovidiuro\myfinance2\App\Http\Requests\CloseTrades;

class TradesController extends MyFinance2Controller
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
        $items = Trade::all();
        return view('myfinance2::trades.crud.dashboard', ['items' => $items]);
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
        $service = new TradeFormFields();
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::trades.crud.create', $data);
    }

    /**
     * Store a newly created item.
     *
     * @param \App\Http\Requests\StoreTrade $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreTrade $request)
    {
        $data = $request->fillData();
        $item = Trade::create($data);

        return redirect()->route('myfinance2::trades.index')
            ->with('success', trans('myfinance2::general.flash-messages.item-created',
                ['type' => 'Trade', 'id' => $item->id]));
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
        $service = new TradeFormFields($id);
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::trades.crud.edit', $data);
    }

    /**
     * Update the specified item.
     *
     * @param \App\Http\Requests\UpdateTrade $request
     * @param int                            $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTrade $request, $id)
    {
        $data = $request->fillData($id);
        $item = Trade::findOrFail($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::trades.index')
            ->with('success', trans('myfinance2::general.flash-messages.item-updated',
                ['type' => 'Trade', 'id' => $item->id]));
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
        $item = Trade::findOrFail($id);
        $item->delete();

        return redirect(route('myfinance2::trades.index'))
                ->with('success', trans('myfinance2::general.flash-messages.item-deleted',
                    ['type' => 'Trade', 'id' => $item->id]));
    }

    /**
     * Close the specifid item.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function close($id)
    {
        $item = Trade::findOrFail($id);
        $item->status = 'CLOSED';
        $item->save();

        return redirect(route('myfinance2::trades.index'))
                ->with('success', trans('myfinance2::trades.flash-messages.trade-closed',
                    ['id' => $item->id]));
    }

    /**
     * Close all that match the parameters in the request.
     *
     * @param \App\Http\Requests\CloseTrades $request
     *
     * @return \Illuminate\Http\Response
     */
    public function closeSymbol(CloseTrades $request)
    {
        $numUpdated = Trade::where('account', $request->account)
            ->where('account_currency', $request->account_currency)
            ->where('symbol', $request->symbol)
            ->where('status', 'OPEN')
            ->update(['status' => 'CLOSED']);

        return redirect(url('/positions'))
                ->with('success', trans_choice('trades.flash-messages.trades-closed', $numUpdated));
    }
}

