<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Models\PriceAlertNotification;

class PriceAlertNotificationController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Delete multiple notification records (bulk action).
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkAction(Request $request): \Illuminate\Http\RedirectResponse
    {
        $action = $request->input('action');
        $ids    = array_filter(array_map('intval', (array) $request->input('ids', [])));

        if (empty($ids) || $action !== 'delete') {
            return redirect()->route('myfinance2::price-alerts.history')
                ->with('error', 'Invalid bulk action request.');
        }

        $affected = PriceAlertNotification::whereIn('id', $ids)
            ->where('user_id', auth()->user()->id)
            ->delete();

        return redirect()->route('myfinance2::price-alerts.history')
            ->with('success', "{$affected} notification record(s) deleted.");
    }

    /**
     * Delete a notification record.
     * Removing today's record allows the alert to re-trigger on the same day.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $notification = PriceAlertNotification::where('id', $id)
            ->where('user_id', auth()->user()->id)
            ->firstOrFail();

        $notification->delete();

        return redirect()->route('myfinance2::price-alerts.history')
            ->with('success', "Notification #{$id} deleted — alert can now re-trigger today.");
    }

    /**
     * Show the notification history log.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $alertId = $request->query('alert_id');

        $query = PriceAlertNotification::with('priceAlertModel.tradeCurrencyModel')
            ->where('user_id', auth()->user()->id)
            ->orderBy('sent_at', 'desc');

        if ($alertId) {
            $query->where('price_alert_id', (int) $alertId);
        }

        $items = $query->limit(500)->get();

        return view('myfinance2::alerts.crud.history', [
            'items'   => $items,
            'alertId' => $alertId,
        ]);
    }
}
