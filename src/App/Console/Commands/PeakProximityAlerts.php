<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\PeakProximityAlertService;

/**
 * Daily exit-hint email when an open position is within N% of its 3M / 6M / 1Y / 2Y peak.
 *
 * Alerts are opt-in (OFF by default): only symbols a user enabled on /peak-proximity-alerts fire.
 * The --all-users path processes just the users who enabled at least one symbol (and hold open
 * positions); --user-id processes that user, with the per-symbol enabled check still applied. For
 * each processed user the command becomes that user (auth()->setUser), so the watchlist dashboard's
 * user scope and auth()->id() resolve correctly, then evaluates the per-symbol peak proximity.
 * Throttled to one email per symbol per day.
 */
class PeakProximityAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:peak-proximity-alerts
        {--user-id= : Process a specific user by ID}
        {--all-users : Process all users with open positions}
        {--threshold= : Override proximity threshold % (default: config, 5)}
        {--symbols= : Comma-separated symbols to limit to (e.g. AMD,ETH-EUR)}
        {--dry-run : Preview without sending or recording}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email exit-hint alerts when an open position is within N% of its 3M/6M/1Y/2Y peak';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('alerts.peak_proximity.enabled', true)) {
            $this->info('Peak-proximity alerts are disabled (alerts.peak_proximity.enabled).');
            return Command::SUCCESS;
        }

        $userId       = $this->option('user-id');
        $allUsers     = $this->option('all-users');
        $dryRun       = (bool) $this->option('dry-run');
        $thresholdRaw = $this->option('threshold');
        $symbolsRaw   = $this->option('symbols');

        $threshold = $thresholdRaw !== null ? (float) $thresholdRaw : null;
        $filterSymbols = $symbolsRaw
            ? array_values(array_filter(array_map('trim', explode(',', $symbolsRaw))))
            : null;

        if (!$userId && !$allUsers) {
            $this->error('Provide --user-id=N or --all-users');
            return Command::FAILURE;
        }

        $service = new PeakProximityAlertService();

        $userIds = $userId
            ? [(int) $userId]
            : $service->getUserIdsWithEnabledAlerts();

        if (empty($userIds)) {
            $this->info('No users with enabled peak-proximity alerts found.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No emails will be sent and no records will be created.');
        }

        $logContext = ($dryRun ? ' (dry-run)' : '')
            . ($threshold !== null ? " threshold={$threshold}%" : '')
            . ($filterSymbols !== null ? ' symbols=' . implode(',', $filterSymbols) : '');

        Log::info('START finance:peak-proximity-alerts' . $logContext . ' => ' . count($userIds) . ' user(s)');

        $totals = ['processed' => 0, 'triggered' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($userIds as $id) {
            $user = User::find($id);
            if (!$user) {
                $this->warn("  User #{$id}: not found, skipping.");
                continue;
            }

            // Become this user (read-only) so the scope + auth()->id() resolve. Never ->save().
            auth()->setUser($user);

            try {
                $stats = $service->evaluateForUser((int) $id, $dryRun, $threshold, $filterSymbols);
            } catch (\Throwable $e) {
                // Isolate per-user failures (e.g. a live-quote error in the dashboard) so one user
                // does not abort the whole --all-users run; log and move on to the next user.
                Log::error("finance:peak-proximity-alerts: user #{$id} failed: " . $e->getMessage());
                $this->warn("  User #{$id}: failed ({$e->getMessage()}), skipping.");
                continue;
            } finally {
                auth()->forgetGuards();
            }

            foreach (['processed', 'triggered', 'skipped', 'failed'] as $key) {
                $totals[$key] += $stats[$key];
            }

            if ($stats['triggered'] > 0) {
                $verb = $dryRun ? 'Would alert' : 'Alerted';
                $this->line("  User #{$id}: {$verb} {$stats['triggered']} symbol(s)"
                    . ' => ' . implode(', ', $stats['symbols']));
            }
        }

        $verb = $dryRun ? 'Would alert' : 'Alerted';
        $this->info("{$verb} {$totals['triggered']} symbol(s); processed {$totals['processed']},"
            . " skipped {$totals['skipped']}, failed {$totals['failed']}.");

        Log::info('END finance:peak-proximity-alerts' . $logContext
            . " => processed={$totals['processed']} triggered={$totals['triggered']}"
            . " skipped={$totals['skipped']} failed={$totals['failed']}");

        return Command::SUCCESS;
    }
}
