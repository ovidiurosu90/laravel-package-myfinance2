<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models\Concerns;

use ovidiuro\myfinance2\App\Services\CategorizationService;
use ovidiuro\myfinance2\App\Services\DipBuyingPlanService;
use ovidiuro\myfinance2\App\Services\DrawdownService;
use ovidiuro\myfinance2\App\Services\SymbolPerformanceService;

/**
 * Clears the owning user's symbol-performance and drawdown caches whenever a model that feeds
 * those computations changes (a trade, dividend, or stock split). Without this, a newly entered
 * trade would not appear in the /watchlist-symbols performance rows until the hourly
 * --refresh-symbol-performance cron rebuild or the 2h TTL expiry.
 *
 * Scope is deliberately narrow: only the per-user performance/drawdown/categorization caches are
 * cleared. The symbol-level sector, analyst and RSI caches (which are expensive to refetch and are
 * unaffected by a user's trade) are left untouched, as is the 2-minute live quote cache.
 */
trait InvalidatesSymbolPerformanceCache
{
    public static function bootInvalidatesSymbolPerformanceCache(): void
    {
        $flush = static function ($model): void
        {
            $userId = (int) ($model->user_id ?? 0);
            if ($userId <= 0)
            {
                return;
            }

            SymbolPerformanceService::clearCache($userId);
            DrawdownService::clearCache($userId);
            // The dip-buying plan reads the deployed-so-far measure from trades, so a trade change
            // must invalidate its cached snapshot too.
            DipBuyingPlanService::clearCache($userId);
            // Categorization is currently recomputed live (uncached); clearing it here is a no-op
            // today but keeps the invalidation correct if that cache is ever re-enabled.
            CategorizationService::clearCache($userId);
        };

        // saved covers both create and update; deleted/restored cover soft-delete lifecycle.
        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }
}
