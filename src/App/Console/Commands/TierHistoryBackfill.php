<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\CategorizationService;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\PerformanceDataset;
use ovidiuro\myfinance2\App\Services\TierAsOfClassifier;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\App\Services\TierHistoryRecorder;
use ovidiuro\myfinance2\App\Services\AppUser;

/**
 * Rebuilds the daily tier history (symbol_tier_history) for a date range: for every trading day, the
 * tier each symbol would have had that day (same rules as the live page, no hysteresis, no
 * override). Re-running over the same range is idempotent. The hourly cron keeps the last few
 * weekdays up to date afterwards.
 *
 * --verify-today compares the as-of(today) basis value with the live categorization and lists the
 * symbols that differ by more than VERIFY_TOLERANCE_PCT points, so the as-of engine can be trusted.
 */
class TierHistoryBackfill extends Command
{
    private const VERIFY_TOLERANCE_PCT = 0.5;

    /**
     * @var string
     */
    protected $signature = 'finance:tier-history-backfill
        {--user-id= : Backfill one user}
        {--all-users : Backfill every user}
        {--from= : First day Y-m-d (default: tier_history.start_date)}
        {--to= : Last day Y-m-d (default: today)}
        {--symbol= : Only this symbol}
        {--dry-run : Compute and print days per tier per symbol, write nothing}
        {--no-fetch : Do not fill missing reference prices from the API cache}
        {--verify-today : Compare the as-of(today) tier basis with the live categorization, write nothing}';

    /**
     * @var string
     */
    protected $description = 'Rebuild the daily tier history (time in tier, settled tier) from stored trades'
        . ' and prices';

    public function handle(): int
    {
        $userIds = $this->_userIds();
        if ($userIds === null) {
            $this->error('Provide --user-id=N or --all-users');
            return Command::FAILURE;
        }

        $from   = Carbon::parse($this->option('from') ?: config('myfinance2.watchlistsymbols.tier_history.start_date'));
        $to     = $this->option('to') ? Carbon::parse($this->option('to')) : Carbon::today();
        $symbol = $this->option('symbol') ?: null;

        // No authenticated user in the CLI, and the eager-loaded relations (accounts, currencies) carry
        // the user scope. Every query here filters by user_id explicitly, as in FinanceApiCron.
        AssignedToUserScope::disable();
        try {
            foreach ($userIds as $userId) {
                if ($this->option('verify-today')) {
                    $this->_verifyToday($userId, $symbol);
                    continue;
                }
                $this->_backfill($userId, $from, $to, $symbol);
            }
        } finally {
            AssignedToUserScope::enable();
        }

        return Command::SUCCESS;
    }

    /**
     * @return int[]|null
     */
    private function _userIds(): ?array
    {
        if ($this->option('user-id')) {
            return [(int) $this->option('user-id')];
        }
        if ($this->option('all-users')) {
            return AppUser::allIds()->map(fn ($id) => (int) $id)->all();
        }

        return null;
    }

    private function _backfill(int $userId, Carbon $from, Carbon $to, ?string $symbol): void
    {
        $dryRun  = (bool) $this->option('dry-run');
        $started = microtime(true);
        $this->info("User {$userId}: {$from->format('Y-m-d')} -> {$to->format('Y-m-d')}"
            . ($symbol ? " ({$symbol})" : '') . ($dryRun ? ' [dry run]' : ''));

        $bar = $this->output->createProgressBar(count(TierHistoryRecorder::weekdays($from, $to)));
        $bar->start();
        $summary = (new TierHistoryRecorder())->recordRange(
            $userId, $from, $to, $symbol, TierHistoryRecorder::SOURCE_BACKFILL,
            $dryRun, !$this->option('no-fetch'), fn () => $bar->advance()
        );
        $bar->finish();
        $this->line('');

        $this->_printTiers($summary['tiers']);
        foreach ($summary['skipped'] as $sym => $dates) {
            $this->warn("  {$sym}: " . count($dates) . ' days skipped (held, no stored price); first '
                . $dates[0] . '. Populate its historical data and re-run.');
        }
        if (!empty($summary['gap_filled'])) {
            $this->comment('  Reference prices filled from the API cache: ' . implode(', ', $summary['gap_filled']));
        }
        $this->info(($dryRun ? 'Would write ' : 'Wrote ') . $summary['rows'] . ' rows over '
            . $summary['days'] . ' days checked in ' . round(microtime(true) - $started, 1) . 's.');
    }

    private function _printTiers(array $tiers): void
    {
        ksort($tiers);
        $rows = [];
        foreach ($tiers as $sym => $counts) {
            $row = [$sym];
            foreach (TierCalculationService::TIERS as $tier) {
                $row[] = $counts[$tier] ?? '';
            }
            $rows[] = $row;
        }
        $this->table(['Symbol', 'Platinum', 'Gold', 'Silver', 'Bronze', 'Rust'], $rows);
    }

    private function _verifyToday(int $userId, ?string $symbol): void
    {
        $today = Carbon::today();
        $data  = new PerformanceDataset($userId, $today, !$this->option('no-fetch'), $symbol);
        $asOf  = (new TierAsOfClassifier())->classifyDay($data, $today, $symbol)['rows'];
        $live  = (new CategorizationService())->build($userId);

        $rows = [];
        foreach ($asOf as $sym => $row) {
            $liveValue = $live[$sym]['basis_value'] ?? null;
            $diff      = ($row['basis_value'] !== null && $liveValue !== null)
                ? abs($row['basis_value'] - $liveValue) : null;
            $sameBasis = $row['basis'] === ($live[$sym]['basis'] ?? null);
            if ($diff !== null && $diff <= self::VERIFY_TOLERANCE_PCT && $sameBasis) {
                continue;
            }
            $rows[] = [
                $sym, $row['basis'], $this->_fmt($row['basis_value']),
                $live[$sym]['basis'] ?? '-', $this->_fmt($liveValue), $this->_fmt($diff),
            ];
        }

        $this->info("User {$userId}: " . count($asOf) . ' symbols compared, ' . count($rows) . ' differ.');
        if (!empty($rows)) {
            $this->table(['Symbol', 'As-of basis', 'As-of %', 'Live basis', 'Live %', 'Diff'], $rows);
            $this->comment('Known reasons: CLOSED trades in an open window (the as-of engine keeps them) and the'
                . ' live price versus the last stored close.');
        }
    }

    private function _fmt(?float $value): string
    {
        return $value !== null ? MoneyFormat::get_formatted_pct($value) : '-';
    }
}
