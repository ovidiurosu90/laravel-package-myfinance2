<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\PriceAlertNotification;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\AlertFormFields;
use ovidiuro\myfinance2\App\Services\AlertService;
use ovidiuro\myfinance2\App\Services\FinanceUtils;
use ovidiuro\myfinance2\Mail\PriceAlertCreated;
use ovidiuro\myfinance2\Mail\PriceAlertStateChanged;
use ovidiuro\myfinance2\App\Http\Requests\StoreAlert;
use ovidiuro\myfinance2\App\Http\Requests\UpdateAlert;

class PriceAlertController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the price alerts list.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $view = $request->query('view', 'active');

        $items = PriceAlert::with('tradeCurrencyModel')->get();

        $projectedGains      = $this->_buildProjectedGains($items);
        $recentNotifications = $this->_buildRecentNotifications($items);
        $currentPrices       = $this->_buildCurrentPrices($items);
        $accountNames        = $this->_buildAccountNames($items);

        return view('myfinance2::alerts.crud.dashboard', [
            'items'               => $items,
            'view'                => $view,
            'projectedGains'      => $projectedGains,
            'recentNotifications' => $recentNotifications,
            'currentPrices'       => $currentPrices,
            'accountNames'        => $accountNames,
        ]);
    }

    /**
     * Show the form for creating an alert.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $service = new AlertFormFields();
        $data = $service->handle();

        $symbolPrefill = $request->query('symbol');
        $sourcePrefill = $request->query('source');

        if ($symbolPrefill) {
            $data['symbol'] = $symbolPrefill;

            $alertTypePrefill = $this->_getAlertTypePrefillFromPosition($symbolPrefill);
            if ($alertTypePrefill !== null) {
                $data['alert_type'] = $alertTypePrefill;
            }
        }

        if ($sourcePrefill && in_array($sourcePrefill, ['manual', 'watchlist', 'suggestion_high'], true)) {
            $data['source'] = $sourcePrefill;
        }

        $data['symbolPrefill'] = $symbolPrefill;

        return view('myfinance2::alerts.crud.create', $data);
    }

    /**
     * Store a newly created alert.
     *
     * @param StoreAlert $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAlert $request)
    {
        $data = $request->fillData();
        $item = PriceAlert::create($data);
        $item->load('tradeCurrencyModel');
        $this->_sendCreatedEmail([$item], 'manual');

        return redirect()->route('myfinance2::price-alerts.index')->with('success',
            trans('myfinance2::alerts.flash-messages.item-created', ['id' => $item->id]));
    }

    /**
     * Show the form for editing an alert.
     *
     * @param Request $request
     * @param int     $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, int $id)
    {
        $service = new AlertFormFields($id);
        $data = $service->handle();

        return view('myfinance2::alerts.crud.edit', $data);
    }

    /**
     * Update the specified alert.
     *
     * @param UpdateAlert $request
     * @param int         $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAlert $request, int $id)
    {
        $item = PriceAlert::findOrFail($id);
        $data = $request->fillData($id);
        $item->fill($data);
        $item->save();

        return redirect()->route('myfinance2::price-alerts.index')->with('success',
            trans('myfinance2::alerts.flash-messages.item-updated', ['id' => $item->id]));
    }

    /**
     * Remove the specified alert (hard delete).
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $item = PriceAlert::findOrFail($id);
        $item->forceDelete();

        return redirect()->route('myfinance2::price-alerts.index')->with('success',
            trans('myfinance2::alerts.flash-messages.item-deleted', ['id' => $id]));
    }

    /**
     * Pause an active alert (ACTIVE → PAUSED).
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function pause(int $id)
    {
        $item = PriceAlert::findOrFail($id);

        if ($item->status !== 'ACTIVE') {
            return redirect()->route('myfinance2::price-alerts.index')->with('error',
                trans('myfinance2::alerts.flash-messages.invalid-transition',
                    ['id' => $id, 'status' => $item->status]));
        }

        $item->status = 'PAUSED';
        $item->save();
        $this->_sendStateChangedEmail([$item], 'paused');

        return redirect()->route('myfinance2::price-alerts.index')->with('success',
            trans('myfinance2::alerts.flash-messages.alert-paused', ['id' => $item->id]));
    }

    /**
     * Resume a paused alert (PAUSED → ACTIVE).
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function resume(int $id)
    {
        $item = PriceAlert::findOrFail($id);

        if ($item->status !== 'PAUSED') {
            return redirect()->route('myfinance2::price-alerts.index')->with('error',
                trans('myfinance2::alerts.flash-messages.invalid-transition',
                    ['id' => $id, 'status' => $item->status]));
        }

        $item->status = 'ACTIVE';
        $item->save();
        $this->_sendStateChangedEmail([$item], 'resumed');

        return redirect()->route('myfinance2::price-alerts.index')->with('success',
            trans('myfinance2::alerts.flash-messages.alert-resumed', ['id' => $item->id]));
    }

    /**
     * Execute a bulk action (pause / resume / delete) on selected alerts.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function bulkAction(Request $request)
    {
        $action = $request->input('action');
        $ids    = array_filter(array_map('intval', (array) $request->input('ids', [])));

        if (empty($ids) || !in_array($action, ['pause', 'resume', 'delete'], true)) {
            return redirect()->route('myfinance2::price-alerts.index')
                ->with('error', 'Invalid bulk action request.');
        }

        // AssignedToUserScope ensures only the authenticated user's alerts are returned
        $alerts         = PriceAlert::whereIn('id', $ids)->with('tradeCurrencyModel')->get();
        $affected       = 0;
        $affectedAlerts = [];

        foreach ($alerts as $alert) {
            if ($action === 'delete') {
                $alert->forceDelete();
                $affected++;
            } elseif ($action === 'pause' && $alert->status === 'ACTIVE') {
                $alert->status = 'PAUSED';
                $alert->save();
                $affectedAlerts[] = $alert;
                $affected++;
            } elseif ($action === 'resume' && $alert->status === 'PAUSED') {
                $alert->status = 'ACTIVE';
                $alert->save();
                $affectedAlerts[] = $alert;
                $affected++;
            }
        }

        if (!empty($affectedAlerts)) {
            $emailAction = $action === 'pause' ? 'paused' : 'resumed';
            $this->_sendStateChangedEmail($affectedAlerts, $emailAction);
        }

        $label = match ($action) {
            'pause'  => 'paused',
            'resume' => 'resumed',
            'delete' => 'deleted',
        };

        return redirect()->route('myfinance2::price-alerts.index')
            ->with('success', "{$affected} alert(s) {$label}.");
    }

    /**
     * Run the suggestion engine for the current user.
     * Creates PRICE_ABOVE alerts for open positions that don't already have one.
     *
     * @return \Illuminate\Http\Response
     */
    public function suggest()
    {
        $service = new AlertService();
        $stats = $service->suggestAlerts(auth()->user()->id);

        if (!empty($stats['created_ids'])) {
            $newAlerts = PriceAlert::whereIn('id', $stats['created_ids'])
                ->with('tradeCurrencyModel')
                ->get()
                ->all();
            $this->_sendCreatedEmail($newAlerts, 'suggestion');
        }

        $message = $stats['created'] > 0
            ? trans_choice(
                'myfinance2::alerts.flash-messages.suggestions-created',
                $stats['created'],
                ['count' => $stats['created']]
            )
            : trans('myfinance2::alerts.flash-messages.suggestions-none');

        return redirect()->route('myfinance2::price-alerts.index')->with('success', $message);
    }

    /**
     * Send a "created" lifecycle email (manual or suggestion).
     *
     * @param \ovidiuro\myfinance2\App\Models\PriceAlert[] $alerts
     * @param string $source 'manual' | 'suggestion'
     *
     * @return void
     */
    private function _sendCreatedEmail(array $alerts, string $source): void
    {
        $emailTo = config('alerts.email_to') ?: auth()->user()?->email;

        if (empty($emailTo) || empty($alerts)) {
            return;
        }

        try {
            $accountNames = $this->_buildAccountNames($alerts);
            Mail::to($emailTo)->send(new PriceAlertCreated($alerts, $source, $accountNames));
        } catch (\Throwable $e) {
            Log::warning("PriceAlertController: created email failed ({$source}): " . $e->getMessage());
        }
    }

    /**
     * Send a state-changed email (paused or resumed).
     *
     * @param \ovidiuro\myfinance2\App\Models\PriceAlert[] $alerts
     * @param string $action 'paused' | 'resumed'
     *
     * @return void
     */
    private function _sendStateChangedEmail(array $alerts, string $action): void
    {
        $emailTo = config('alerts.email_to') ?: auth()->user()?->email;

        if (empty($emailTo) || empty($alerts)) {
            return;
        }

        try {
            $accountNames = $this->_buildAccountNames($alerts);
            Mail::to($emailTo)->send(new PriceAlertStateChanged($alerts, $action, $accountNames));
        } catch (\Throwable $e) {
            Log::warning("PriceAlertController: state-changed email failed ({$action}): " . $e->getMessage());
        }
    }

    /**
     * Build projected gains map keyed by alert ID.
     *
     * @param \Illuminate\Database\Eloquent\Collection $alerts
     *
     * @return array
     */
    private function _buildProjectedGains(\Illuminate\Support\Collection $alerts): array
    {
        if ($alerts->isEmpty()) {
            return [];
        }

        // Load all open BUY trades for the alert symbols in one query
        $symbols = $alerts->pluck('symbol')->unique()->toArray();
        $openTrades = Trade::whereIn('symbol', $symbols)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->with('tradeCurrencyModel')
            ->get();

        // Aggregate position data per symbol
        $positions = [];
        foreach ($openTrades as $trade) {
            $sym = $trade->symbol;
            if (!isset($positions[$sym])) {
                $positions[$sym] = ['total_qty' => 0.0, 'total_cost' => 0.0];
            }
            $positions[$sym]['total_qty'] += (float) $trade->quantity;
            $positions[$sym]['total_cost'] += (float) $trade->quantity * (float) $trade->unit_price;
        }

        // Compute projected gain per alert
        $projectedGains = [];
        foreach ($alerts as $alert) {
            $sym = $alert->symbol;
            if (empty($positions[$sym]) || $positions[$sym]['total_qty'] <= 0) {
                $projectedGains[$alert->id] = null;
                continue;
            }

            $pos = $positions[$sym];
            $avgCost = $pos['total_cost'] / $pos['total_qty'];
            $targetPrice = (float) $alert->target_price;
            $gainPerUnit = $targetPrice - $avgCost;
            $totalGain = $gainPerUnit * $pos['total_qty'];
            $gainPct = $avgCost > 0 ? ($gainPerUnit / $avgCost) * 100 : 0.0;

            $projectedGains[$alert->id] = [
                'gain_value'   => $totalGain,
                'gain_pct'     => $gainPct,
                'avg_cost'     => $avgCost,
                'total_qty'    => $pos['total_qty'],
                'has_position' => true,
            ];
        }

        return $projectedGains;
    }

    /**
     * Fetch current market prices for all alert symbols (keyed by symbol).
     * Uses the FinanceAPI 2-min cache so this is cheap when cron has already warmed it.
     *
     * @param \Illuminate\Database\Eloquent\Collection $alerts
     *
     * @return array symbol => float
     */
    private function _buildCurrentPrices(\Illuminate\Support\Collection $alerts): array
    {
        $symbols = $alerts->pluck('symbol')->unique()->values()->toArray();

        if (empty($symbols)) {
            return [];
        }

        $quotes = (new FinanceUtils())->getQuotes($symbols, null, false);

        if (!is_array($quotes)) {
            return [];
        }

        $prices = [];
        foreach ($symbols as $symbol) {
            if (isset($quotes[$symbol]['price'])) {
                $prices[$symbol] = (float) $quotes[$symbol]['price'];
            }
        }

        return $prices;
    }

    /**
     * Build a map of symbol => account names for open BUY positions.
     *
     * @param \Illuminate\Database\Eloquent\Collection $alerts
     *
     * @return array symbol => string[]
     */
    private function _buildAccountNames(\Illuminate\Support\Collection|array $alerts): array
    {
        $symbols = collect($alerts)->pluck('symbol')->unique()->values()->toArray();

        if (empty($symbols)) {
            return [];
        }

        $trades = Trade::whereIn('symbol', $symbols)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->with('accountModel')
            ->get();

        $accountNames = [];
        foreach ($trades as $trade) {
            $sym  = $trade->symbol;
            $name = $trade->accountModel?->name;
            if ($name && !in_array($name, $accountNames[$sym] ?? [], true)) {
                $accountNames[$sym][] = $name;
            }
        }

        return $accountNames;
    }

    /**
     * Determine the suggested alert type for a symbol based on position profitability.
     * Returns 'PRICE_ABOVE' if current price >= avg cost, 'PRICE_BELOW' if below.
     * Returns null if no open position or no current price available.
     *
     * @param string $symbol
     *
     * @return string|null
     */
    private function _getAlertTypePrefillFromPosition(string $symbol): ?string
    {
        $quotes = (new FinanceUtils())->getQuotes([$symbol], null, false);
        $currentPrice = is_array($quotes) ? ($quotes[$symbol]['price'] ?? null) : null;

        if ($currentPrice === null) {
            return null;
        }

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

        $avgCost = $totalCost / $totalQty;
        return (float) $currentPrice >= $avgCost ? 'PRICE_ABOVE' : 'PRICE_BELOW';
    }

    /**
     * Build last-3-trigger notifications map keyed by alert ID.
     *
     * @param \Illuminate\Database\Eloquent\Collection $alerts
     *
     * @return array
     */
    private function _buildRecentNotifications(\Illuminate\Support\Collection $alerts): array
    {
        if ($alerts->isEmpty()) {
            return [];
        }

        $alertIds = $alerts->pluck('id')->toArray();
        $notifications = PriceAlertNotification::whereIn('price_alert_id', $alertIds)
            ->where('status', 'SENT')
            ->orderBy('sent_at', 'desc')
            ->get()
            ->groupBy('price_alert_id')
            ->map(fn ($group) => $group->take(3));

        return $notifications->toArray();
    }
}
