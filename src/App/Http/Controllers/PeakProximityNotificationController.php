<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Models\PeakProximityNotification;
use ovidiuro\myfinance2\App\Services\PeakProximityAlertService;

/**
 * Notification history for the peak-proximity exit-hint alerts. Read-only audit log, optionally
 * filtered to one symbol (?symbol=AMD) from the per-symbol "view history" link on the management page.
 * Deleting a row that was sent today re-arms that symbol for the same day's run.
 */
class PeakProximityNotificationController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the notification history log, newest first, optionally narrowed to one symbol.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $symbol = $request->query('symbol');

        $query = PeakProximityNotification::where('user_id', auth()->user()->id)
            ->orderBy('sent_at', 'desc');

        if ($symbol) {
            $query->where('symbol', $symbol);
        }

        $items = $query->limit(500)->get();

        return view('myfinance2::peakproximityalerts.crud.history', [
            'items'  => $items,
            'symbol' => $symbol,
        ]);
    }

    /**
     * Delete a single notification record. Removing today's record re-arms the symbol for today.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        $notification = PeakProximityNotification::where('id', $id)
            ->where('user_id', auth()->user()->id)
            ->firstOrFail();

        $notification->delete();

        return redirect()->route('myfinance2::peak-proximity-alerts.history')
            ->with('success', "Notification #{$id} deleted; the symbol can alert again today.");
    }

    /**
     * Clear all of today's SENT/FAILED notification records for the logged-in user, re-arming every
     * symbol that already alerted today so the next run (or the Re-run button) can email them again.
     * Older history is left intact. Scoped to auth()->id().
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearToday(): \Illuminate\Http\RedirectResponse
    {
        $userId = (int) auth()->user()->id;

        $deleted = PeakProximityNotification::where('user_id', $userId)
            ->where('sent_at', '>=', now()->startOfDay())
            ->delete();

        return redirect()->route('myfinance2::peak-proximity-alerts.history')
            ->with('success', "{$deleted} of today's record(s) cleared; those symbols can alert again today.");
    }

    /**
     * Re-run the peak-proximity alert engine now, for the logged-in user only. Evaluates the user's
     * enabled symbols against the live watchlist dashboard and sends any emails that are due, exactly
     * as the daily cron would for this one user. Scoped to auth()->id() (the watchlist dashboard and
     * the engine read auth()->id() and the per-user scope, which the authenticated request resolves).
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function rerun(): \Illuminate\Http\RedirectResponse
    {
        if (!config('alerts.peak_proximity.enabled', true)) {
            return redirect()->route('myfinance2::peak-proximity-alerts.history')
                ->with('error', 'Peak-proximity alerts are globally disabled (alerts.peak_proximity.enabled).');
        }

        $userId = (int) auth()->user()->id;

        try {
            $stats = (new PeakProximityAlertService())->evaluateForUser($userId);
        } catch (\Throwable $e) {
            return redirect()->route('myfinance2::peak-proximity-alerts.history')
                ->with('error', 'Re-run failed: ' . $e->getMessage());
        }

        return redirect()->route('myfinance2::peak-proximity-alerts.history')
            ->with('success', "Re-run complete: emailed {$stats['triggered']}, info {$stats['info']},"
                . " skipped {$stats['skipped']}, failed {$stats['failed']}.");
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
            return redirect()->route('myfinance2::peak-proximity-alerts.history')
                ->with('error', 'Invalid bulk action request.');
        }

        $affected = PeakProximityNotification::whereIn('id', $ids)
            ->where('user_id', auth()->user()->id)
            ->delete();

        return redirect()->route('myfinance2::peak-proximity-alerts.history')
            ->with('success', "{$affected} notification record(s) deleted.");
    }
}
