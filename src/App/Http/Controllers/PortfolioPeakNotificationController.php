<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Models\PortfolioPeakNotification;

/**
 * Notification history for the Portfolio Peak Alert emails. Read-only append-only audit log; rows
 * can be deleted individually or in bulk (mirrors DipBuyingNotificationController).
 */
class PortfolioPeakNotificationController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the email history, newest first.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $items = PortfolioPeakNotification::where('user_id', auth()->user()->id)
            ->orderBy('sent_at', 'desc')
            ->limit(500)
            ->get();

        return view('myfinance2::portfoliopeakalerts.crud.history', [
            'items' => $items,
        ]);
    }

    /**
     * Delete a single notification record.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $notification = PortfolioPeakNotification::where('id', $id)
            ->where('user_id', auth()->user()->id)
            ->firstOrFail();

        $notification->delete();

        return redirect()->route('myfinance2::portfolio-peak-alerts.history')
            ->with('success', "Notification #{$id} deleted.");
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
            return redirect()->route('myfinance2::portfolio-peak-alerts.history')
                ->with('error', 'Invalid bulk action request.');
        }

        $affected = PortfolioPeakNotification::whereIn('id', $ids)
            ->where('user_id', auth()->user()->id)
            ->delete();

        return redirect()->route('myfinance2::portfolio-peak-alerts.history')
            ->with('success', "{$affected} notification record(s) deleted.");
    }
}
