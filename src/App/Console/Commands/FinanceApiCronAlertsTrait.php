<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\AlertService;

/**
 * Trait for price alert evaluation.
 *
 * Runs on a configurable interval (default: every 5 min) with a 20-second
 * execution budget. Uses FinanceAPI cache warm from refreshQuotes() run earlier
 * in the same cron pass.
 */
trait FinanceApiCronAlertsTrait
{
    /**
     * Evaluate active price alerts for all users.
     * Skipped if called too soon (respects eval_interval_minutes config).
     *
     * @return void
     */
    public function evaluateAlerts(): void
    {
        if (!config('alerts.enabled', true)) {
            return;
        }

        $intervalMinutes = (int) config('alerts.eval_interval_minutes', 5);
        $cacheKey = 'finance-api-cron:alerts:last-eval';
        $lastEvalTime = Cache::get($cacheKey, 0);

        if ((time() - $lastEvalTime) < $intervalMinutes * 60) {
            return; // Not enough time has passed
        }

        Cache::put($cacheKey, time(), 3600);

        Log::info('START app:finance-api-cron evaluateAlerts()');

        $service = new AlertService();
        $userIds = $service->getAllUserIdsWithActiveAlerts();

        if (empty($userIds)) {
            Log::info('END app:finance-api-cron evaluateAlerts() => no active alerts');
            return;
        }

        $totalStats = ['processed' => 0, 'triggered' => 0, 'skipped' => 0, 'deferred' => 0, 'time_ms' => 0];

        foreach ($userIds as $userId) {
            $stats = $service->evaluateAlerts((int) $userId);
            foreach ($totalStats as $key => $_) {
                $totalStats[$key] += $stats[$key];
            }
        }

        Log::info(
            'END app:finance-api-cron evaluateAlerts() => '
            . count($userIds) . ' users, '
            . $totalStats['processed'] . ' processed, '
            . $totalStats['triggered'] . ' triggered, '
            . $totalStats['skipped'] . ' skipped, '
            . $totalStats['deferred'] . ' deferred, '
            . $totalStats['time_ms'] . 'ms'
        );
    }
}
