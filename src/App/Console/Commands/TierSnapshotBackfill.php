<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

use ovidiuro\myfinance2\App\Services\TierSnapshotBackfill as Backfill;
use ovidiuro\myfinance2\App\Services\AppUser;

/**
 * Gives the orders and trades made before tier readings were saved a reading rebuilt once from the stored tier history,
 * then locked (TierSnapshotBackfill). Rows that have a reading are left alone; --force replaces one, by id only.
 * --dry-run lists what it would do without writing.
 */
class TierSnapshotBackfill extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:tier-snapshot-backfill
        {--user-id= : User whose orders and trades to backfill (required)}
        {--only=all : all, orders or trades}
        {--id= : Only this order or trade id (with --only=orders or --only=trades)}
        {--force : Replace an existing reading (with --id only)}
        {--dry-run : List what would be done without writing}
        {--details : List every row}';

    /**
     * @var string
     */
    protected $description = 'Rebuild once, from the stored tier history, the tier reading of orders and trades made'
        . ' before readings were saved';

    public function handle(): int
    {
        $userId = (int) $this->option('user-id');
        $user   = $userId > 0 ? AppUser::find($userId) : null;
        $only   = (string) $this->option('only');
        $id     = $this->option('id') !== null ? (int) $this->option('id') : null;
        if ($user === null) {
            $this->error('Provide --user-id=N of an existing user.');
            return Command::FAILURE;
        }
        if (!in_array($only, [Backfill::ONLY_ALL, Backfill::ONLY_ORDERS, Backfill::ONLY_TRADES], true)) {
            $this->error('--only must be all, orders or trades.');
            return Command::FAILURE;
        }
        if ($id !== null && $only === Backfill::ONLY_ALL) {
            $this->error('--id needs --only=orders or --only=trades.');
            return Command::FAILURE;
        }
        if ($this->option('force') && $id === null) {
            $this->error('--force replaces a saved reading, so it needs --id (one row at a time).');
            return Command::FAILURE;
        }

        // The new rows take their owner from the signed-in user (MyFinance2Model); nothing is saved on the user.
        Auth::setUser($user);
        $dryRun = (bool) $this->option('dry-run');
        $rows   = Backfill::make()->run($userId, $only, $id, (bool) $this->option('force'), $dryRun);

        if ($this->option('details') || $id !== null) {
            $this->table(['Kind', 'Id', 'Date', 'Action', 'Symbol', 'Result', 'Source', 'Verdict'], array_map(
                fn ($r) => [$r['kind'], $r['id'], $r['date'], $r['action'], $r['symbol'], $r['result'],
                    $r['source'] ?? '-', $r['verdict'] ?? '-'],
                $rows
            ));
        }
        $this->_summary($rows, $dryRun);

        return Command::SUCCESS;
    }

    private function _summary(array $rows, bool $dryRun): void
    {
        $counts = [];
        foreach ($rows as $row) {
            $result = preg_replace('/ #\d+$/', '', $row['result']);
            $key    = $row['kind'] . '|' . $result . '|' . ($row['source'] ?? '-') . '|' . ($row['verdict'] ?? '-');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        ksort($counts);
        $this->info(($dryRun ? 'Dry run, nothing written: ' : '') . count($rows) . ' rows.');
        $this->table(['Kind', 'Result', 'Source', 'Verdict', 'Rows'], array_map(
            fn ($key, $n) => array_merge(explode('|', $key), [$n]), array_keys($counts), $counts
        ));
    }
}
