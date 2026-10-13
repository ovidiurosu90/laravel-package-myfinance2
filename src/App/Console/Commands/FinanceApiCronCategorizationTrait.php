<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\CategorizationService;
use ovidiuro\myfinance2\App\Services\TierHistoryRecorder;

trait FinanceApiCronCategorizationTrait
{
    /**
     * Pre-compute and cache the categorization (tier, quadrant, drawdown) for all
     * users. Called after refreshSymbolPerformance() so the performance and
     * drawdown snapshots it depends on are already cached.
     *
     * Delegates to CategorizationService so the cron and the on-demand dashboard
     * path produce byte-for-byte identical results from the same cache key.
     */
    public function refreshCategorization(): void
    {
        Log::info('START app:finance-api-cron refreshCategorization()');

        try {
            $userIds   = DB::table('users')->pluck('id');
            $service   = new CategorizationService();
            $processed = 0;

            foreach ($userIds as $userId) {
                $service->rebuild((int) $userId);
                $processed++;
                Log::info("Categorization built for user {$userId}");
                $this->_recordRecentTierHistory((int) $userId);
            }

            Log::info(
                "END app:finance-api-cron refreshCategorization() => "
                . "{$processed} users processed"
            );
        } catch (\Exception $e) {
            Log::error(
                'Failed to refresh categorization: ' . $e->getMessage()
                . ' | ' . $e->getFile() . ':' . $e->getLine()
            );
        }
    }

    /**
     * Rewrite the daily tier history of the last few weekdays (today included). Idempotent, so running
     * it every hour is safe, and it picks up price corrections from the daily historical refresh.
     * Isolated per user so one failure does not stop the categorization of the others.
     */
    private function _recordRecentTierHistory(int $userId): void
    {
        try {
            $started = microtime(true);
            $today   = Carbon::today();
            $from    = TierHistoryRecorder::weekdaysBefore(
                $today, (int) config('watchlistsymbols.tier_history.recent_rewrite_days', 7)
            );
            $summary = (new TierHistoryRecorder())->recordRange(
                $userId, $from, $today, null, TierHistoryRecorder::SOURCE_CRON
            );
            Log::info("Tier history recorded for user {$userId}: {$summary['rows']} rows over "
                . "{$summary['days']} weekdays in " . round(microtime(true) - $started, 2) . 's');
        } catch (\Throwable $e) {
            Log::error(
                "Failed to record tier history for user {$userId}: " . $e->getMessage()
                . ' | ' . $e->getFile() . ':' . $e->getLine()
            );
        }
    }
}
