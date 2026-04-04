<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\FinanceUtils;
use ovidiuro\myfinance2\App\Services\OrderFormFields;
use ovidiuro\myfinance2\App\Http\Requests\StoreOrder;
use ovidiuro\myfinance2\App\Http\Requests\UpdateOrder;

class OrdersController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the orders list.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $view = $request->query('view', 'active');

        $items = Order::with('accountModel', 'tradeCurrencyModel')->get();

        $trades = Trade::with('accountModel', 'tradeCurrencyModel')
            ->where('status', 'OPEN')
            ->whereDoesntHave('linkedOrders')
            ->orderBy('timestamp', 'desc')
            ->get();

        return view('myfinance2::orders.crud.dashboard', [
            'items'  => $items,
            'view'   => $view,
            'trades' => $trades,
        ]);
    }

    /**
     * Show the form for creating an order.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $service = new OrderFormFields();
        $data = $service->handle();

        $symbolPrefill = $request->query('symbol');
        $duplicateOrders = collect();

        if ($symbolPrefill) {
            $data['symbol'] = $symbolPrefill;
            $data['status'] = 'PLACED';
            $data['placed_at'] = now()->format('Y-m-d H:i:s');

            $existingTrade = Trade::where('symbol', $symbolPrefill)
                ->where('status', 'OPEN')
                ->first();
            if ($existingTrade) {
                $data['account_id'] = $existingTrade->account_id;
            }

            $duplicateOrders = Order::where('symbol', $symbolPrefill)
                ->whereIn('status', ['DRAFT', 'PLACED'])
                ->get();
        }

        $data['symbolPrefill'] = $symbolPrefill;
        $data['duplicateOrders'] = $duplicateOrders;

        $actionPrefill = $request->query('action');
        if ($actionPrefill && in_array(strtoupper($actionPrefill), ['BUY', 'SELL'], true)) {
            $data['action_prefill'] = strtoupper($actionPrefill);
        }

        $alertId = $request->query('alert_id');
        if ($alertId && is_numeric($alertId) && empty($data['description'])) {
            $data['description'] = 'Price Alert #' . (int) $alertId;
        }

        $data['projectedGain'] = null;
        $data['projectedGainPriceLabel'] = null;
        if ($symbolPrefill) {
            $data['projectedGain'] = $this->_buildProjectedGainAtCurrentPrice($symbolPrefill);
            $data['projectedGainPriceLabel'] = 'current market price';
        }

        return view('myfinance2::orders.crud.create', $data);
    }

    /**
     * Store a newly created order.
     *
     * @param StoreOrder $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreOrder $request)
    {
        $data = $request->fillData();
        $item = Order::create($data);

        return redirect()->route('myfinance2::orders.index')->with('success',
            trans('myfinance2::general.flash-messages.item-created',
                ['type' => 'Order', 'id' => $item->id]));
    }

    /**
     * Show the form for editing an order.
     *
     * @param Request $request
     * @param int     $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, int $id)
    {
        $item = Order::findOrFail($id);

        if (!$item->isEditable()) {
            return redirect()->route('myfinance2::orders.index')->with('error',
                trans('myfinance2::orders.flash-messages.not-editable',
                    ['id' => $id, 'status' => $item->status]));
        }

        $service = new OrderFormFields($id);
        $data = $service->handle();

        $data['projectedGain'] = $this->_buildProjectedGain($item);
        $data['projectedGainPriceLabel'] = 'limit price';

        return view('myfinance2::orders.crud.edit', $data);
    }

    /**
     * Update the specified order.
     *
     * @param UpdateOrder $request
     * @param int         $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateOrder $request, int $id)
    {
        $item = Order::findOrFail($id);

        if (!$item->isEditable()) {
            return redirect()->route('myfinance2::orders.index')->with('error',
                trans('myfinance2::orders.flash-messages.not-editable',
                    ['id' => $id, 'status' => $item->status]));
        }

        $data = $request->fillData($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::orders.index')->with('success',
            trans('myfinance2::general.flash-messages.item-updated',
                ['type' => 'Order', 'id' => $item->id]));
    }

    /**
     * Remove the specified order.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $item = Order::findOrFail($id);
        $item->delete();

        return redirect(route('myfinance2::orders.index'))->with('success',
            trans('myfinance2::general.flash-messages.item-deleted',
                ['type' => 'Order', 'id' => $item->id]));
    }

    /**
     * Transition DRAFT → PLACED.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function place(int $id)
    {
        $item = Order::findOrFail($id);

        if ($item->status !== 'DRAFT') {
            return redirect()->route('myfinance2::orders.index')->with('error',
                trans('myfinance2::orders.flash-messages.invalid-transition',
                    ['id' => $id, 'status' => $item->status]));
        }

        if (empty($item->account_id) || empty($item->quantity) || empty($item->limit_price)) {
            return redirect()->route('myfinance2::orders.edit', $id)->with('error',
                trans('myfinance2::orders.flash-messages.fill-required-fields', ['id' => $id]));
        }

        $item->status = 'PLACED';
        $item->placed_at = now();
        $item->save();

        return redirect()->route('myfinance2::orders.index')->with('success',
            trans('myfinance2::orders.flash-messages.order-placed', ['id' => $item->id]));
    }

    /**
     * Transition PLACED → FILLED.
     *
     * @param Request $request
     * @param int     $id
     *
     * @return \Illuminate\Http\Response
     */
    public function fill(Request $request, int $id)
    {
        $item = Order::findOrFail($id);

        if ($item->status !== 'PLACED') {
            return redirect()->route('myfinance2::orders.index')->with('error',
                trans('myfinance2::orders.flash-messages.invalid-transition',
                    ['id' => $id, 'status' => $item->status]));
        }

        $item->status = 'FILLED';
        $item->filled_at = now();

        if ($request->trade_id) {
            $item->trade_id = (int) $request->trade_id;
        }

        $item->save();

        if ($request->create_trade == '1') {
            $params = http_build_query([
                'symbol'            => $item->symbol,
                'action'            => $item->action,
                'quantity'          => $item->getCleanQuantity(),
                'unit_price'        => $item->limit_price,
                'account_id'        => $item->account_id,
                'trade_currency_id' => $item->trade_currency_id,
                'exchange_rate'     => $item->exchange_rate,
                'timestamp'         => $item->filled_at
                                        ? $item->filled_at->format('Y-m-d H:i:s')
                                        : now()->format('Y-m-d H:i:s'),
                'description'       => $item->description,
                'order_id'          => $item->id,
            ]);

            return redirect(url('/trades/create?' . $params))->with('success',
                trans('myfinance2::orders.flash-messages.order-filled', ['id' => $item->id]));
        }

        return redirect()->route('myfinance2::orders.index', ['view' => 'all'])->with('success',
            trans('myfinance2::orders.flash-messages.order-filled', ['id' => $item->id]));
    }

    /**
     * Transition PLACED → EXPIRED.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function expire(int $id)
    {
        $item = Order::findOrFail($id);

        if ($item->status !== 'PLACED') {
            return redirect()->route('myfinance2::orders.index')->with('error',
                trans('myfinance2::orders.flash-messages.invalid-transition',
                    ['id' => $id, 'status' => $item->status]));
        }

        $item->status     = 'EXPIRED';
        $item->expired_at = ($item->placed_at ?? now())->endOfDay();
        $item->trade_id   = null;
        $item->save();

        return redirect()->route('myfinance2::orders.index', ['view' => 'all'])->with('success',
            trans('myfinance2::orders.flash-messages.order-expired', ['id' => $item->id]));
    }

    /**
     * Cancel a non-terminal order (DRAFT or PLACED → CANCELLED).
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function cancel(int $id)
    {
        $item = Order::findOrFail($id);

        if ($item->isTerminal()) {
            return redirect()->route('myfinance2::orders.index')->with('error',
                trans('myfinance2::orders.flash-messages.invalid-transition',
                    ['id' => $id, 'status' => $item->status]));
        }

        $item->status   = 'CANCELLED';
        $item->trade_id = null;
        $item->save();

        return redirect()->route('myfinance2::orders.index', ['view' => 'all'])->with('success',
            trans('myfinance2::orders.flash-messages.order-cancelled', ['id' => $item->id]));
    }

    /**
     * Reopen a terminal order back to PLACED (FILLED/EXPIRED/CANCELLED → PLACED).
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function reopen(int $id)
    {
        $item = Order::findOrFail($id);

        if (!$item->isTerminal()) {
            return redirect()->route('myfinance2::orders.index')->with('error',
                trans('myfinance2::orders.flash-messages.invalid-transition',
                    ['id' => $id, 'status' => $item->status]));
        }

        $item->status     = 'PLACED';
        $item->filled_at  = null;
        $item->expired_at = null;
        $item->save();

        return redirect()->route('myfinance2::orders.index')->with('success',
            trans('myfinance2::orders.flash-messages.order-reopened', ['id' => $item->id]));
    }

    /**
     * Link a trade to this order.
     *
     * @param Request $request
     * @param int     $id
     *
     * @return \Illuminate\Http\Response
     */
    public function linkTrade(Request $request, int $id)
    {
        $request->validate(['trade_id' => 'required|integer']);

        $item = Order::findOrFail($id);
        $item->trade_id = (int) $request->trade_id;
        $item->save();

        return redirect()->route('myfinance2::orders.index')->with('success',
            trans('myfinance2::orders.flash-messages.trade-linked',
                ['id' => $item->id, 'trade_id' => $item->trade_id]));
    }

    /**
     * Unlink the trade from this order.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function unlinkTrade(int $id)
    {
        $item = Order::findOrFail($id);
        $item->trade_id = null;
        $item->save();

        return redirect()->route('myfinance2::orders.index')->with('success',
            trans('myfinance2::orders.flash-messages.trade-unlinked', ['id' => $item->id]));
    }

    /**
     * Compute projected gain/loss for a SELL order at its limit_price.
     * Returns an array with gain info, or null if not applicable.
     *
     * @param Order $order
     *
     * @return array|null
     */
    private function _buildProjectedGain(Order $order): ?array
    {
        if ($order->action !== 'SELL' || (float) $order->limit_price <= 0) {
            return null;
        }

        return $this->_computeProjectedGain($order->symbol, (float) $order->limit_price);
    }

    /**
     * Compute projected gain/loss at the current market price (for create prefill).
     * Returns an array with gain info, or null if not applicable.
     *
     * @param string $symbol
     *
     * @return array|null
     */
    private function _buildProjectedGainAtCurrentPrice(string $symbol): ?array
    {
        $quotes = (new FinanceUtils())->getQuotes([$symbol], null, false);
        $currentPrice = is_array($quotes) ? (float) ($quotes[$symbol]['price'] ?? 0) : 0;

        if ($currentPrice <= 0) {
            return null;
        }

        return $this->_computeProjectedGain($symbol, $currentPrice);
    }

    /**
     * Shared projected gain computation from open BUY positions at a given sell price.
     *
     * @param string $symbol
     * @param float  $sellPrice
     *
     * @return array|null
     */
    private function _computeProjectedGain(string $symbol, float $sellPrice): ?array
    {
        $openTrades = Trade::where('symbol', $symbol)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->get();

        if ($openTrades->isEmpty()) {
            return null;
        }

        $totalQty = 0.0;
        $totalCost = 0.0;
        foreach ($openTrades as $trade) {
            $qty = (float) $trade->quantity;
            $totalQty += $qty;
            $totalCost += $qty * (float) $trade->unit_price;
        }

        if ($totalQty <= 0) {
            return null;
        }

        $avgCost     = $totalCost / $totalQty;
        $gainPerUnit = $sellPrice - $avgCost;
        $totalGain   = $gainPerUnit * $totalQty;
        $gainPct     = $avgCost > 0 ? ($gainPerUnit / $avgCost) * 100.0 : 0.0;

        return [
            'gain_value'   => round($totalGain, 2),
            'gain_pct'     => round($gainPct, 4),
            'avg_cost'     => $avgCost,
            'total_qty'    => $totalQty,
            'has_position' => true,
        ];
    }
}
