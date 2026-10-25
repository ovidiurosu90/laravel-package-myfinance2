<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Services\OrderTierSnapshots;
use ovidiuro\myfinance2\App\Services\OrderTierWarnings;

/**
 * The tier block at the top of the order create / edit form, loaded by AJAX when the symbol and action are set and
 * refreshed when the quantity or limit price change: the warnings (serious, then soft), the tier reading (live, or
 * the one saved with the order, locked) and, on edit, how today's reading differs. Also returns the serious codes
 * still to be confirmed, so the form asks before it saves.
 */
class OrderTierReadingController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request): JsonResponse
    {
        $symbol = strtoupper(trim((string) $request->query('symbol', '')));
        $action = strtoupper(trim((string) $request->query('action', '')));
        if ($symbol === '' || !in_array($action, ['BUY', 'SELL'], true)) {
            return response()->json(['html' => '', 'missing' => [], 'confirm_html' => '']);
        }
        $order    = $request->filled('order_id') ? Order::with('tierSnapshot')->find((int) $request->query('order_id'))
            : null;
        $userId   = (int) auth()->id();
        $quantity = self::number($request->query('quantity'));
        $price    = self::number($request->query('limit_price'));

        $snapshots = OrderTierSnapshots::make();
        try {
            $checked = $snapshots->checkFor($order, $userId, $symbol, $action, $quantity, $price);
            // On a kept reading, today's reading next to it ("now" chip, "since you created it" note).
            $now = $checked['kept'] !== null
                ? $snapshots->check()->live($userId, $symbol, $action, $quantity, $price) : null;
        } catch (\Throwable $e) {
            Log::warning('OrderTierReadingController: tier reading unavailable for ' . $symbol . ': '
                . $e->getMessage());
            return response()->json([
                'html'         => view('myfinance2::orders.partials.tier-reading-error', ['symbol' => $symbol])
                    ->render(),
                'missing'      => [],
                'confirm_html' => '',
            ]);
        }
        $missing = OrderTierSnapshots::missing($checked['check'], [], $checked['kept']);
        $data    = [
            'symbol'  => $symbol,
            'action'  => $action,
            'order'   => $order,
            'check'   => $checked['check'],
            'kept'    => $checked['kept'],
            'now'     => $now,
            'missing' => $missing,
        ];

        return response()->json([
            'html'         => view('myfinance2::orders.partials.tier-reading', $data)->render(),
            'missing'      => $missing,
            'confirm_html' => view('myfinance2::orders.partials.tier-confirm-list', $data + [
                'quantity' => $quantity, 'price' => $price,
                'warnings' => array_values(array_filter(
                    OrderTierWarnings::serious($checked['check']['warnings'] ?? []),
                    fn ($w) => in_array($w['code'], $missing, true)
                )),
            ])->render(),
        ]);
    }

    /**
     * A form number, or null when empty or not numeric.
     */
    public static function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
