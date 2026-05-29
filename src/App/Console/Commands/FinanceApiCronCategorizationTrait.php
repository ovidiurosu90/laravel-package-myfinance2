<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\CategorizationService;

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
}
