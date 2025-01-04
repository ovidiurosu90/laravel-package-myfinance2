<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Services\AccountFormFields;
use ovidiuro\myfinance2\App\Http\Requests\StoreAccount;
use ovidiuro\myfinance2\App\Http\Requests\UpdateAccount;

class AccountsController extends MyFinance2Controller
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
        $items = Account::with('currency')->get();
        return view('myfinance2::accounts.crud.dashboard', ['items' => $items]);
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
        $service = new AccountFormFields();
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::accounts.crud.create', $data);
    }

    /**
     * Store a newly created item.
     *
     * @param \App\Http\Requests\StoreAccount $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAccount $request)
    {
        $data = $request->fillData();
        $item = Account::create($data);

        return redirect()->route('myfinance2::accounts.index')
            ->with('success',
                   trans('myfinance2::general.flash-messages.item-created',
                         ['type' => 'Account', 'id' => $item->id]));
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
        $service = new AccountFormFields($id);
        $data = $service->handle(); // associative array having form fields as keys

        return view('myfinance2::accounts.crud.edit', $data);
    }

    /**
     * Update the specified item.
     *
     * @param \App\Http\Requests\UpdateAccount $request
     * @param int                            $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAccount $request, $id)
    {
        $data = $request->fillData($id);
        $item = Account::findOrFail($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::accounts.index')
            ->with('success',
                   trans('myfinance2::general.flash-messages.item-updated',
                         ['type' => 'Account', 'id' => $item->id]));
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
        $item = Account::findOrFail($id);
        $item->delete();

        return redirect(route('myfinance2::accounts.index'))
            ->with('success',
                   trans('myfinance2::general.flash-messages.item-deleted',
                         ['type' => 'Account', 'id' => $item->id]));
    }
}

