<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\CashBalance;
use ovidiuro\myfinance2\App\Services\CashBalanceFormFields;
use ovidiuro\myfinance2\App\Services\CashBalancesDashboard;
use ovidiuro\myfinance2\App\Http\Requests\StoreCashBalance;
use ovidiuro\myfinance2\App\Http\Requests\UpdateCashBalance;

class CashBalancesController extends MyFinance2Controller
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
        $service = new CashBalancesDashboard();
        $items = $service->handle(); // array (symbol => array(quote_data))
        return view('myfinance2::cashbalances.crud.dashboard', ['items' => $items]);
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
        $service = new CashBalanceFormFields();
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::cashbalances.crud.create', $data);
    }

    /**
     * Store a newly created item.
     *
     * @param StoreCashBalance $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCashBalance $request)
    {
        $data = $request->fillData();
        $item = CashBalance::create($data);

        return redirect()->route('myfinance2::cash-balances.index')->with('success',
             trans('myfinance2::general.flash-messages.item-created',
                ['type' => 'Cash Balance', 'id' => $item->id]));
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
        $service = new CashBalanceFormFields($id);
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::cashbalances.crud.edit', $data);
    }

    /**
     * Update the specified item.
     *
     * @param UpdateCashBalance $request
     * @param int               $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCashBalance $request, $id)
    {
        $data = $request->fillData($id);
        $item = CashBalance::findOrFail($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::cash-balances.index')->with('success',
            trans('myfinance2::general.flash-messages.item-updated',
                ['type' => 'Cash Balance', 'id' => $item->id]));
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
        $item = CashBalance::findOrFail($id);
        $item->delete();

        return redirect(route('myfinance2::cash-balances.index'))->with('success',
            trans('myfinance2::general.flash-messages.item-deleted',
                ['type' => 'Cash Balance', 'id' => $item->id]));
    }
}

