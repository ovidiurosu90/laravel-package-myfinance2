<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use ovidiuro\myfinance2\App\Models\DipBuyingNotification;
use ovidiuro\myfinance2\App\Models\DipBuyingSetting;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\DipBuyingBacktestService;
use ovidiuro\myfinance2\App\Services\DipBuyingPlanService;
use ovidiuro\myfinance2\Mail\DipBuyingAlert;

/**
 * Daily Dip Buying Plan email after the European close.
 *
 * Opt-in (OFF by default): only users who enabled the feature AND the email channel on
 * /dip-buying-alerts are processed. For each, the shared DipBuyingPlanService computes the plan and
 * the email fires only on a state change, throttled to one per day: the drawdown band deepens, the
 * verdict crosses from on-plan to behind, or the stall backstop activates. There is deliberately no
 * "the trend turned" email, because re-entry timing cannot be timed.
 */
class DipBuyingAlerts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:dip-buying-alerts
        {--user-id= : Process a specific user by ID}
        {--all-users : Process all users with the feature + email enabled}
        {--dry-run : Preview without sending or recording}';

    /**
     * @var string
     */
    protected $description = 'Email a Dip Buying Plan alert when the drawdown band deepens, you fall behind plan, or the stall backstop activates';

    public function handle(): int
    {
        if (!config('alerts.dip_buying.enabled', true)) {
            $this->info('Dip Buying Plan alerts are disabled (alerts.dip_buying.enabled).');
            return Command::SUCCESS;
        }

        $userId   = $this->option('user-id');
        $allUsers = $this->option('all-users');
        $dryRun   = (bool) $this->option('dry-run');

        if (!$userId && !$allUsers) {
            $this->error('Provide --user-id=N or --all-users');
            return Command::FAILURE;
        }

        $userIds = $userId ? [(int) $userId] : $this->_userIdsWithEmailEnabled();

        if (empty($userIds)) {
            $this->info('No users with Dip Buying Plan email alerts enabled.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No emails will be sent and no records will be created.');
        }

        Log::info('START finance:dip-buying-alerts' . ($dryRun ? ' (dry-run)' : '')
            . ' => ' . count($userIds) . ' user(s)');

        $engine = new DipBuyingPlanService();
        $sent   = 0;
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
                $result = $this->_evaluate($engine, (int) $id, $dryRun);
            } catch (\Throwable $e) {
                Log::error("finance:dip-buying-alerts: user #{$id} failed: " . $e->getMessage());
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

        Log::info("END finance:dip-buying-alerts => sent={$sent} skipped={$skipped} failed={$failed}");

        return Command::SUCCESS;
    }

    /**
     * Evaluate and (unless dry-run) send the alert for one user. Returns 'sent' or 'skipped'.
     *
     * @param DipBuyingPlanService $engine
     * @param int                  $userId
     * @param bool                 $dryRun
     *
     * @return string
     */
    private function _evaluate(DipBuyingPlanService $engine, int $userId, bool $dryRun): string
    {
        $plan = $engine->buildForUser($userId);
        if ($plan === null) {
            return 'skipped';
        }

        if ($this->_sentToday($userId)) {
            return 'skipped'; // one email per day
        }

        $last    = $this->_lastNotification($userId);
        $trigger = $this->_resolveTrigger($plan, $last);
        if ($trigger === null) {
            return 'skipped';
        }

        if ($dryRun) {
            Log::info("DipBuyingAlerts: [dry-run] user {$userId} trigger={$trigger}"
                . " dd={$plan['effective_dd_pct']}% verdict={$plan['verdict']}");
            return 'sent';
        }

        return $this->_send($plan, $trigger, $userId) ? 'sent' : 'skipped';
    }

    /**
     * Which of the three triggers fires, or null when nothing changed. New users (no prior alert)
     * fire on the first actionable state (behind, or the stall backstop being active).
     *
     * @param array                          $plan
     * @param DipBuyingNotification|null     $last
     *
     * @return string|null  band_deepened | crossed_behind | stall
     */
    private function _resolveTrigger(array $plan, ?DipBuyingNotification $last): ?string
    {
        $targetPct   = (float) $plan['target_pct'];
        $verdict     = $plan['verdict'];
        $stallActive = (bool) ($plan['stall_active'] ?? false);

        if ($last === null) {
            if ($stallActive) {
                return 'stall';
            }
            return $verdict === DipBuyingPlanService::VERDICT_BEHIND ? 'crossed_behind' : null;
        }

        if ($targetPct > (float) $last->target_pct + 1e-9) {
            return 'band_deepened';
        }
        if ($verdict === DipBuyingPlanService::VERDICT_BEHIND
            && $last->verdict !== DipBuyingPlanService::VERDICT_BEHIND
        ) {
            return 'crossed_behind';
        }
        if ($stallActive) {
            return 'stall';
        }

        return null;
    }

    /**
     * Send the email and record the audit/throttle row (record as SENT first, mark FAILED on error,
     * mirroring the peak-proximity alert flow).
     *
     * @param array  $plan
     * @param string $trigger
     * @param int    $userId
     *
     * @return bool
     */
    private function _send(array $plan, string $trigger, int $userId): bool
    {
        $emailTo = config('alerts.dip_buying.email_to')
            ?: config('alerts.email_to')
            ?: User::find($userId)?->email;

        if (empty($emailTo)) {
            Log::warning("DipBuyingAlerts: no email address for user {$userId}, skipping.");
            return false;
        }

        $notification = DipBuyingNotification::create([
            'user_id'               => $userId,
            'effective_dd_pct'      => $plan['effective_dd_pct'],
            'vusa_dd_pct'           => $plan['vusa_dd_pct'] ?? null,
            'portfolio_dd_pct'      => $plan['portfolio_dd_pct'] ?? null,
            'driver'                => $plan['driver'] ?? null,
            'target_pct'            => (int) round($plan['target_pct']),
            'deployed_pct'          => $plan['deployed_pct'],
            'deployed_eur'          => $plan['deployed_eur'],
            'pool_amount_eur'       => $plan['pool_amount_eur'],
            'suggested_tranche_eur' => $plan['suggested_tranche_eur'],
            'verdict'               => $plan['verdict'],
            'trigger'               => $trigger,
            'sent_at'               => now(),
            'status'                => 'SENT',
        ]);

        // The regime breakdown and current-episode block let the email mirror the /positions panel,
        // from the one shared computation the controller also uses (DipBuyingBacktestService::panelDetail).
        // Never fatal: an alert with the core plan still goes out if the richer figures cannot be built.
        $regime    = [];
        $current   = null;
        $firstBand = null;
        try {
            $detail    = (new DipBuyingBacktestService())->panelDetail($userId, $plan);
            $regime    = $detail['regime'];
            $current   = $detail['current'];
            $firstBand = $detail['firstBand'];
        } catch (\Throwable $e) {
            Log::warning("DipBuyingAlerts: panel detail build failed for user {$userId}: " . $e->getMessage());
        }

        try {
            Mail::to($emailTo)->send(new DipBuyingAlert($plan, $trigger, $regime, $current, $firstBand));
        } catch (\Throwable $e) {
            Log::error("DipBuyingAlerts: email send failed for user {$userId}: " . $e->getMessage());
            $notification->update(['status' => 'FAILED', 'error_message' => substr($e->getMessage(), 0, 500)]);
            return false;
        }

        Log::info("DipBuyingAlerts: alert sent to {$emailTo} for user {$userId} (trigger={$trigger})");
        return true;
    }

    /**
     * Whether a SENT alert already went out today for this user.
     */
    private function _sentToday(int $userId): bool
    {
        return DipBuyingNotification::where('user_id', $userId)
            ->where('status', 'SENT')
            ->where('sent_at', '>=', now()->startOfDay())
            ->exists();
    }

    /**
     * The most recent SENT notification for this user, or null.
     */
    private function _lastNotification(int $userId): ?DipBuyingNotification
    {
        return DipBuyingNotification::where('user_id', $userId)
            ->where('status', 'SENT')
            ->orderBy('sent_at', 'desc')
            ->first();
    }

    /**
     * User IDs with the feature enabled, the email channel on, a positive pool, and at least one
     * trade (so the deployed-so-far measure is meaningful). Mirrors the opt-in narrowing the
     * peak-proximity command does before the heavy pass.
     *
     * @return array<int, int>
     */
    private function _userIdsWithEmailEnabled(): array
    {
        $enabled = DipBuyingSetting::where('status', DipBuyingSetting::ENABLED)
            ->where('email_enabled', true)
            ->where('pool_amount_eur', '>', 0)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (empty($enabled)) {
            return [];
        }

        AssignedToUserScope::disable();
        $withTrades = Trade::distinct()->pluck('user_id')->map(fn ($id) => (int) $id)->toArray();
        AssignedToUserScope::enable();

        return array_values(array_intersect($enabled, $withTrades));
    }
}
