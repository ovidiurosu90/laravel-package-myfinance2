<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\LedgerTransaction;
use ovidiuro\myfinance2\App\Services\LedgerTransactionFormFields;
use ovidiuro\myfinance2\App\Http\Requests\StoreLedgerTransaction;
use ovidiuro\myfinance2\App\Http\Requests\UpdateLedgerTransaction;

class LedgerTransactionsController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the ledger transactions dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $items = LedgerTransaction::all();
        return view('myfinance2::ledger.crud.transactions.dashboard', ['items' => $items]);
    }

    /**
     * Show the form for creating a new ledger transaction.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $service = new LedgerTransactionFormFields();

        $parentId = null;
        if ($request->has('parent_id')) {
            $parentId = $request->parent_id;
        }
        $data = $service->handle($parentId); // associative array having form fields as keys

        return view('myfinance2::ledger.crud.transactions.create', $data);
    }

    /**
     * Store a newly created ledger transaction.
     *
     * @param \App\Http\Requests\StoreLedgerTransaction $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLedgerTransaction $request)
    {
        $data = $request->fillData();
        $item = LedgerTransaction::create($data);

        return redirect()->route('myfinance2::ledger-transactions.index')
            ->with('success', trans('myfinance2::general.flash-messages.item-created',
                ['type' => 'Ledger Transaction', 'id' => $item->id]));
    }

    /**
     * Edit the specified ledger transaction.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $service = new LedgerTransactionFormFields($id);
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::ledger.crud.transactions.edit', $data);
    }

    /**
     * Update the specified ledger transaction.
     *
     * @param \App\Http\Requests\UpdateLedgerTransaction $request
     * @param int                                        $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateLedgerTransaction $request, $id)
    {
        $data = $request->fillData($id);
        $item = LedgerTransaction::findOrFail($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::ledger-transactions.index')
            ->with('success', trans('myfinance2::general.flash-messages.item-updated',
                ['type' => 'Ledger Transaction', 'id' => $item->id]));
    }

    /**
     * Remove the specified ledger transaction.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $item = LedgerTransaction::findOrFail($id);
        $item->delete();

        return redirect(route('myfinance2::ledger-transactions.index'))
                ->with('success', trans('myfinance2::general.flash-messages.item-deleted',
                    ['type' => 'Transaction', 'id' => $item->id]));
    }
}

