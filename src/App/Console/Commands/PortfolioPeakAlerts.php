<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\PortfolioPeakAlertService;

/**
 * Portfolio Peak Alerts email: fires when the portfolio's EUR gain or return on cost is within N%
 * of its rolling 6M/1Y/2Y high, a portfolio-level "consider a full exit / rebalance" hint.
 *
 * Opt-in (OFF by default): only users who enabled the feature AND the email channel on
 * /portfolio-peak-alerts are processed. The per-user opt-in guard lives in the service's
 * evaluateForUser(), so the --user-id path cannot email a user who never opted in. Run hourly from
 * cron like the other alert commands; the per-day guard in the service caps it at one email per
 * user per day, so an hourly cadence just surfaces a trigger sooner.
 */
class PortfolioPeakAlerts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:portfolio-peak-alerts
        {--user-id= : Process a specific user by ID}
        {--all-users : Process all users with email enabled}
        {--dry-run : Preview without sending or recording}';

    /**
     * @var string
     */
    protected $description = 'Email an alert when the portfolio EUR gain or return % is near its '
        . '6M/1Y/2Y high';

    public function handle(): int
    {
        if (!config('alerts.portfolio_peak.enabled', true)) {
            $this->info('Portfolio Peak alerts are disabled (alerts.portfolio_peak.enabled).');
            return Command::SUCCESS;
        }

        $userId   = $this->option('user-id');
        $allUsers = $this->option('all-users');
        $dryRun   = (bool) $this->option('dry-run');

        if (!$userId && !$allUsers) {
            $this->error('Provide --user-id=N or --all-users');
            return Command::FAILURE;
        }

        $service = new PortfolioPeakAlertService();
        $userIds = $userId ? [(int) $userId] : $service->getUserIdsWithEmailEnabled();

        if (empty($userIds)) {
            $this->info('No users with Portfolio Peak email alerts enabled.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No emails will be sent and no records will be created.');
        }

        Log::info('START finance:portfolio-peak-alerts' . ($dryRun ? ' (dry-run)' : '')
            . ' => ' . count($userIds) . ' user(s)');

        $sent    = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($userIds as $id) {
            $user = User::find($id);
            if (!$user) {
                $this->warn("  User #{$id}: not found, skipping.");
                continue;
            }

            auth()->setUser($user);

            try {
                $result = $service->evaluateForUser((int) $id, $dryRun);
            } catch (\Throwable $e) {
                Log::error("finance:portfolio-peak-alerts: user #{$id} failed: " . $e->getMessage());
                $this->warn("  User #{$id}: failed ({$e->getMessage()}), skipping.");
                $failed++;
                continue;
            } finally {
                auth()->forgetGuards();
            }

            if ($result === 'sent') {
                $sent++;
                $verb = $dryRun ? 'Would alert' : 'Alerted';
                $this->line("  User #{$id}: {$verb}.");
            } else {
                $skipped++;
            }
        }

        $verb = $dryRun ? 'Would alert' : 'Alerted';
        $this->info("{$verb} {$sent} user(s); skipped {$skipped}, failed {$failed}.");

        Log::info("END finance:portfolio-peak-alerts => sent={$sent} skipped={$skipped} failed={$failed}");

        return Command::SUCCESS;
    }
}
