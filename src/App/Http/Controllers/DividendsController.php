<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Dividend;
use ovidiuro\myfinance2\App\Services\DividendFormFields;
use ovidiuro\myfinance2\App\Http\Requests\StoreDividend;
use ovidiuro\myfinance2\App\Http\Requests\UpdateDividend;

class DividendsController extends MyFinance2Controller
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
        $items = Dividend::with('accountModel', 'dividendCurrencyModel')->get();
        return view('myfinance2::dividends.crud.dashboard', ['items' => $items]);
    }

    /**
     * Show the form for creating an item.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $service = new DividendFormFields();
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::dividends.crud.create', $data);
    }

    /**
     * Store a newly created item.
     *
     * @param StoreDividend $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDividend $request)
    {
        $data = $request->fillData();
        $item = Dividend::create($data);

        return redirect()->route('myfinance2::dividends.index')->with('success',
            trans('myfinance2::general.flash-messages.item-created',
                ['type' => 'Dividend', 'id' => $item->id]));
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
        $service = new DividendFormFields($id);
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::dividends.crud.edit', $data);
    }

    /**
     * Update the specified item.
     *
     * @param UpdateDividend $request
     * @param int            $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDividend $request, $id)
    {
        $data = $request->fillData($id);
        $item = Dividend::findOrFail($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::dividends.index')->with('success',
            trans('myfinance2::general.flash-messages.item-updated',
                ['type' => 'Dividend', 'id' => $item->id]));
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
        $item = Dividend::findOrFail($id);
        $item->delete();

        return redirect(route('myfinance2::dividends.index'))->with('success',
            trans('myfinance2::general.flash-messages.item-deleted',
                ['type' => 'Dividend', 'id' => $item->id]));
    }
}

