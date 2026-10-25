<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\TradeTierSnapshots;

/**
 * The tier reading at the top of the trade create / edit form, loaded by AJAX when the symbol, action and timestamp
 * are set: the reading the trade is saved with (the order's it fills, the trade's own, or a new one: live for today,
 * rebuilt from the stored history for a past date), read-only, and one line with its verdict. No banner and no
 * confirmation: the trade has already happened.
 */
class TradeTierReadingController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request): JsonResponse
    {
        $symbol    = strtoupper(trim((string) $request->query('symbol', '')));
        $action    = strtoupper(trim((string) $request->query('action', '')));
        $timestamp = trim((string) $request->query('timestamp', ''));
        if ($symbol === '' || !in_array($action, ['BUY', 'SELL'], true) || !self::_validDate($timestamp)) {
            return response()->json(['html' => '']);
        }
        $trade = $request->filled('trade_id') ? Trade::with('tierSnapshot')->find((int) $request->query('trade_id'))
            : null;
        $order = $request->filled('order_id') ? Order::with('tierSnapshot')->find((int) $request->query('order_id'))
            : null;

        try {
            $reading = TradeTierSnapshots::make()->readingFor(
                $trade, $order, (int) auth()->id(), $symbol, $action, $timestamp,
                OrderTierReadingController::number($request->query('quantity')),
                OrderTierReadingController::number($request->query('unit_price'))
            );
        } catch (\Throwable $e) {
            Log::warning('TradeTierReadingController: tier reading unavailable for ' . $symbol . ': '
                . $e->getMessage());
            return response()->json(['html' => view('myfinance2::orders.partials.tier-reading-error', [
                'symbol' => $symbol,
            ])->render()]);
        }

        return response()->json(['html' => view('myfinance2::trades.partials.tier-reading', $reading + [
            'symbol' => $symbol,
            'trade'  => $trade,
        ])->render()]);
    }

    private static function _validDate(string $timestamp): bool
    {
        if ($timestamp === '') {
            return false;
        }
        try {
            Carbon::parse($timestamp);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
