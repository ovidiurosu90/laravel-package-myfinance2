<?php

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\MoversService;

/**
 * Trait for Biggest Movers refresh operations.
 * All four periods (today, weekly, monthly, yearly) are refreshed every minute
 * by the minutely cron. Live prices from the FinanceAPI cache (already warm from
 * refreshQuotes()) are shared across all four period computations in one pass.
 */
trait FinanceApiCronMoversTrait
{
    /**
     * Refresh all movers (all four periods) for every user with open positions.
     * Called after refreshAccountOverview() in the minutely finance-api-cron.
     */
    public function refreshMovers(): void
    {
        Log::info('START app:finance-api-cron refreshMovers()');

        $service = new MoversService();
        $userIds = $service->getAllUserIds();

        foreach ($userIds as $userId) {
            $service->refreshAllMovers($userId);
        }

        Log::info('END app:finance-api-cron refreshMovers() => '
            . count($userIds) . ' users refreshed!');
    }
}
