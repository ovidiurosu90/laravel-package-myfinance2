<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\AlertService;

/**
 * Auto-generate price alert suggestions from open BUY positions.
 *
 * Strategy: for each open position, suggest a PRICE_ABOVE alert (threshold)% below
 * the 52-week high, skipping symbols that already have an ACTIVE/PAUSED PRICE_ABOVE alert.
 */
class SuggestAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:suggest-alerts
        {--user-id= : Process a specific user by ID}
        {--all-users : Process all users with open BUY positions}
        {--dry-run : Preview suggestions without creating alerts}
        {--threshold= : Override suggestion threshold % (default: config value)}
        {--symbols= : Comma-separated list of symbols to process (e.g. AMD,AMZN,META)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-generate PRICE_ABOVE alert suggestions from open positions (smart lookback: 2Y if held 2+ years, else 1Y)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        AssignedToUserScope::disable();

        $userId = $this->option('user-id');
        $allUsers = $this->option('all-users');
        $dryRun = $this->option('dry-run');
        $thresholdRaw = $this->option('threshold');
        $symbolsRaw = $this->option('symbols');

        $threshold = $thresholdRaw !== null ? (float) $thresholdRaw : null;
        $filterSymbols = $symbolsRaw
            ? array_values(array_filter(array_map('trim', explode(',', $symbolsRaw))))
            : null;

        if (!$userId && !$allUsers) {
            $this->error('Provide --user-id=N or --all-users');
            return Command::FAILURE;
        }

        $userIds = $userId
            ? [(int) $userId]
            : $this->_getAllUsersWithOpenPositions();

        if (empty($userIds)) {
            $this->info('No users with open positions found.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No alerts will be created.');
        }

        $logContext = ($dryRun ? ' (dry-run)' : '')
            . ($threshold !== null ? " threshold={$threshold}%" : '')
            . ($filterSymbols !== null ? ' symbols=' . implode(',', $filterSymbols) : '');

        Log::info('START finance:suggest-alerts' . $logContext . ' => ' . count($userIds) . ' user(s)');

        $service = new AlertService();
        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($userIds as $id) {
            $stats = $service->suggestAlerts((int) $id, $dryRun, $threshold, $filterSymbols);
            $totalCreated += $stats['created'];
            $totalSkipped += $stats['skipped'];

            if ($stats['created'] > 0) {
                $verb = $dryRun ? 'Would create' : 'Created';
                $this->line("  User #{$id}: {$verb} {$stats['created']} alert(s)"
                    . ' → ' . implode(', ', $stats['symbols']));
            }
        }

        $verb = $dryRun ? 'Would create' : 'Created';
        $this->info("{$verb} {$totalCreated} alert(s), skipped {$totalSkipped}.");

        Log::info('END finance:suggest-alerts' . $logContext . " => created={$totalCreated} skipped={$totalSkipped}");

        return Command::SUCCESS;
    }

    /**
     * Get all user IDs that have at least one open BUY trade.
     *
     * @return array
     */
    private function _getAllUsersWithOpenPositions(): array
    {
        return Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->distinct()
            ->pluck('user_id')
            ->toArray();
    }
}
