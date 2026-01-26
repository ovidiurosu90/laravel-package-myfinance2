<?php

namespace ovidiuro\myfinance2\App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Global scope that filters all queries by the authenticated user's ID.
 *
 * SECURITY: This scope ensures users can only access their own data.
 * It can only be disabled in CLI context (cron jobs, artisan commands).
 * Defense-in-depth: Even if disabled, web requests with authenticated users
 * will still have the scope enforced.
 */
class AssignedToUserScope implements Scope
{
    private static bool $_enabled = true;

    /**
     * Disable the scope. Only allowed in CLI context (e.g., cron jobs).
     * When disabled, queries must filter by user_id explicitly.
     *
     * SECURITY: This method will throw an exception if called outside CLI context.
     *
     * @throws RuntimeException if called outside CLI context
     */
    public static function disable(): void
    {
        if (php_sapi_name() !== 'cli') {
            throw new RuntimeException('AssignedToUserScope can only be disabled in CLI context');
        }
        self::$_enabled = false;
    }

    /**
     * Re-enable the scope.
     */
    public static function enable(): void
    {
        self::$_enabled = true;
    }

    /**
     * Check if the scope is currently enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$_enabled;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * SECURITY: Defense-in-depth implementation:
     * - If enabled: Always filter by authenticated user's ID
     * - If disabled in CLI: Allow all data (for cron jobs)
     * - If disabled but in web context with auth user: ENFORCE scope anyway and log critical error
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Normal path: scope is enabled, filter by user_id
        if (self::$_enabled) {
            $builder->where('user_id', auth()->user()->id);
            return;
        }

        // Scope is disabled - verify we're in a safe context
        $isCli = php_sapi_name() === 'cli';
        $hasAuthUser = auth()->check();

        // DEFENSE IN DEPTH: If scope is disabled but we're in web context
        // with an authenticated user, this is a security anomaly.
        // Enforce the scope anyway and log a critical error.
        if (!$isCli && $hasAuthUser) {
            Log::critical(
                'SECURITY: AssignedToUserScope was disabled in web context with authenticated user! '
                . 'Enforcing scope anyway. Model: ' . get_class($model)
                . ', User ID: ' . auth()->user()->id
            );
            $builder->where('user_id', auth()->user()->id);
            return;
        }

        // Safe: CLI context with scope disabled (cron jobs, artisan commands)
        // No filtering applied - caller is responsible for data access control
    }
}

