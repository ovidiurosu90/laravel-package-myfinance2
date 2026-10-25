<?php

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\TradeFormFields;
use ovidiuro\myfinance2\App\Services\TradeTierSnapshots;
use ovidiuro\myfinance2\App\Services\TierVerdictSummary;
use ovidiuro\myfinance2\App\Services\TradeVsIndex;
use ovidiuro\myfinance2\App\Services\DisableAutoFxLedgerService;
use ovidiuro\myfinance2\App\Services\LatestEurRates;
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
        $items   = Trade::with('accountModel', 'tradeCurrencyModel', 'linkedOrders', 'tierSnapshot')->get();
        $vsIndex = $this->_vsIndex($items);

        return view('myfinance2::trades.crud.dashboard', [
            'items'       => $items,
            'vsIndex'     => $vsIndex,
            'tierSummary' => TierVerdictSummary::build($items, fn ($trade) => $trade->timestamp, null, $vsIndex),
            'eurRates'    => new LatestEurRates(),
        ]);
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
        $service = new TradeFormFields();
        $data = $service->handle(); // associative array having form fields as keys

        $prefillFields = [
            'symbol', 'action', 'quantity', 'unit_price',
            'account_id', 'trade_currency_id', 'exchange_rate',
            'timestamp', 'description',
        ];
        foreach ($prefillFields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->query($field);
            }
        }

        $data['order_id'] = $request->query('order_id');
        $data['linkedOrder'] = $data['order_id']
            ? Order::with('accountModel', 'tradeCurrencyModel')->find((int) $data['order_id'])
            : null;
        $data['autoFetchFinanceData'] = !empty($data['linkedOrder']);

        $data['showDisableAutoFxSection'] = true;
        $data['disable_auto_fx_rate'] = false;
        $data['paired_account_id'] = null;

        return view('myfinance2::trades.crud.create', $data);
    }

    /**
     * Store a newly created item.
     *
     * @param StoreTrade $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreTrade $request)
    {
        $data  = $request->fillData();
        $order = $request->order_id ? Order::with('tierSnapshot')->find((int) $request->order_id) : null;
        // Before the create: a live reading taken after it would already hold the trade (position, share of the
        // portfolio), and the create clears the cached live reading.
        $tier = $this->_tierReadingFor(null, $order, $data);
        $item = Trade::create($data);

        if ($order) {
            $order->trade_id = $item->id;
            $order->save();
        }
        if ($tier !== null) {
            $this->_tierSnapshots()->save($item, $tier);
        }

        if ($request->boolean('disable_auto_fx_rate') && $request->paired_account_id) {
            $autoFxService = new DisableAutoFxLedgerService();
            $autoFxService->createLedgerTransactions($data, (int) $request->paired_account_id);
        }

        return redirect()->route('myfinance2::trades.index')->with('success',
            trans('myfinance2::general.flash-messages.item-created',
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
     * @param UpdateTrade $request
     * @param int         $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTrade $request, $id)
    {
        $data = $request->fillData($id);
        $item = Trade::with('tierSnapshot')->findOrFail($id);
        // Before the fill: the reading depends on whether the symbol, action or date changed.
        $tier = $this->_tierReadingFor($item, null, $data);
        $item->fill($data);
        $item->save();
        if ($tier !== null) {
            $this->_tierSnapshots()->save($item, $tier);
        }

        return redirect()->route('myfinance2::trades.index')->with('success',
            trans('myfinance2::general.flash-messages.item-updated',
                ['type' => 'Trade', 'id' => $item->id]));
    }

    /**
     * The reading a trade with these values gets, or null when it cannot be taken (a failing reading never blocks
     * the trade). Taken before the trade is saved.
     */
    private function _tierReadingFor(?Trade $trade, ?Order $order, array $data): ?array
    {
        try {
            return $this->_tierSnapshots()->readingFor(
                $trade, $order, (int) auth()->id(), (string) $data['symbol'], (string) $data['action'],
                (string) $data['timestamp'],
                is_numeric($data['quantity'] ?? null) ? (float) $data['quantity'] : null,
                is_numeric($data['unit_price'] ?? null) ? (float) $data['unit_price'] : null
            );
        } catch (\Throwable $e) {
            Log::warning('TradesController: tier reading unavailable for ' . $data['symbol'] . ', saved without one: '
                . $e->getMessage());

            return null;
        }
    }

    /**
     * Each trade against VUSA.AS since it was made (TradeVsIndex); none when the prices cannot be read, so the list
     * always loads.
     */
    private function _vsIndex(\Illuminate\Support\Collection $items): array
    {
        try {
            return (new TradeVsIndex())->forTrades($items);
        } catch (\Throwable $e) {
            Log::warning('TradesController: trades vs index unavailable: ' . $e->getMessage());

            return [];
        }
    }

    private function _tierSnapshots(): TradeTierSnapshots
    {
        return TradeTierSnapshots::make();
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

        return redirect(route('myfinance2::trades.index'))->with('success',
            trans('myfinance2::general.flash-messages.item-deleted',
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

        return redirect(route('myfinance2::trades.index'))->with('success',
            trans('myfinance2::trades.flash-messages.trade-closed',
                ['id' => $item->id]));
    }

    /**
     * Close all that match the parameters in the request.
     *
     * @param CloseTrades $request
     *
     * @return \Illuminate\Http\Response
     */
    public function closeSymbol(CloseTrades $request)
    {
        $numUpdated = Trade::where('account_id', $request->account_id)
            ->where('symbol', $request->symbol)
            ->where('status', 'OPEN')
            ->update(['status' => 'CLOSED']);

        return redirect(url('/positions'))->with('success',
            trans_choice('myfinance2::trades.flash-messages.trades-closed',
                $numUpdated));
    }
}

