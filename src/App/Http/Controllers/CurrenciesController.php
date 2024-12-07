<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Services\CurrencyFormFields;
use ovidiuro\myfinance2\App\Http\Requests\StoreCurrency;
use ovidiuro\myfinance2\App\Http\Requests\UpdateCurrency;

class CurrenciesController extends MyFinance2Controller
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
        $items = Currency::all();
        return view('myfinance2::currencies.crud.dashboard', ['items' => $items]);
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
        $service = new CurrencyFormFields();
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::currencies.crud.create', $data);
    }

    /**
     * Store a newly created item.
     *
     * @param \App\Http\Requests\StoreCurrency $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCurrency $request)
    {
        $data = $request->fillData();
        $item = Currency::create($data);

        return redirect()->route('myfinance2::currencies.index')
            ->with('success', trans('myfinance2::general.flash-messages.item-created',
                ['type' => 'Currency', 'id' => $item->id]));
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
        $service = new CurrencyFormFields($id);
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::currencies.crud.edit', $data);
    }

    /**
     * Update the specified item.
     *
     * @param \App\Http\Requests\UpdateCurrency $request
     * @param int                            $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCurrency $request, $id)
    {
        $data = $request->fillData($id);
        $item = Currency::findOrFail($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::currencies.index')
            ->with('success', trans('myfinance2::general.flash-messages.item-updated',
                ['type' => 'Currency', 'id' => $item->id]));
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
        $item = Currency::findOrFail($id);
        $item->delete();

        return redirect(route('myfinance2::currencies.index'))
                ->with('success', trans('myfinance2::general.flash-messages.item-deleted',
                    ['type' => 'Currency', 'id' => $item->id]));
    }
}

