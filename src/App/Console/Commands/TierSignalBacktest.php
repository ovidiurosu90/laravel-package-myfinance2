<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\TierSignalBacktest as Backtest;
use ovidiuro\myfinance2\App\Services\TierSignalBacktestService;

/**
 * Signal backtest (phase 4): replays the tier signals (rare high, rare low, deteriorating) and the range-bound (TRIM /
 * ADD / BUY) and TAKE PROFIT readings on a user's stored daily tier history without look-ahead and prints, per signal,
 * how the symbol did against VUSA.AS 21 / 63 / 126 trading days after each episode began, next to the baseline of every
 * evaluated symbol-day. --grid runs every rare share x consistency cell of the plan. Read-only; the same engine and
 * report as the /peak-proximity-alerts/backtest page.
 */
class TierSignalBacktest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:tier-signal-backtest
        {--user-id= : User to backtest (required)}
        {--from= : First day evaluated, Y-m-d (default: the tier history start)}
        {--rare-share= : Rare share % (default: config)}
        {--consistency= : Consistency % behind the settled tier (default: config)}
        {--grid : Run every rare share (10, 20, 30) x consistency (50, 60) cell}';

    /**
     * @var string
     */
    protected $description = 'Backtest the tier signals and the range-bound / TAKE PROFIT readings: how symbols did'
        . ' against VUSA.AS after each';

    public function handle(): int
    {
        $userId = (int) $this->option('user-id');
        if ($userId <= 0 || !User::find($userId)) {
            $this->error('Provide --user-id=N of an existing user.');
            return Command::FAILURE;
        }
        $from    = $this->option('from') ?: null;
        $service = new TierSignalBacktestService();

        if ($this->option('grid')) {
            $reports = $service->grid($userId, $from);
            foreach ($reports as $report) {
                $this->_print($report);
            }
            $this->_printGrid($reports);
            return Command::SUCCESS;
        }

        $rare = $this->option('rare-share');
        $cons = $this->option('consistency');
        $this->_print($service->report(
            $userId, $from, $rare !== null ? (float) $rare : null, $cons !== null ? (float) $cons : null, true
        ));

        return Command::SUCCESS;
    }

    private function _print(array $report): void
    {
        $this->line('');
        $this->info(sprintf(
            'Rare share %s %%, consistency %s %% . %s -> %s . %d symbol-days, %d symbols',
            $this->_num($report['rare_share']), $this->_num($report['consistency']), $report['from'],
            $report['to'] ?? 'n/a', $report['days'], $report['symbols']
        ));
        if (!empty($report['missing_prices'])) {
            $this->warn('No closes, left out: ' . implode(', ', $report['missing_prices']));
        }

        $headers = ['Signal', 'Group', 'Episodes', 'Median days'];
        foreach ($report['horizons'] as $h) {
            $headers[] = "{$h}d: n . worked . median / mean";
        }
        $rows = [];
        foreach ($report['summary'] as $row) {
            $line = [$row['signal'], $row['group'], $row['episodes'], $this->_num($row['median_days'])];
            foreach ($report['horizons'] as $h) {
                $s      = $row['horizons'][$h];
                $line[] = $s['n'] === 0 ? '-' : sprintf(
                    '%d . %s%% . %s / %s', $s['n'], $this->_num($s['hit_rate']), $this->_pts($s['median_rel']),
                    $this->_pts($s['avg_rel'])
                );
            }
            $rows[] = $line;
        }
        $base = ['Baseline', 'every day', '', ''];
        foreach ($report['horizons'] as $h) {
            $b      = $report['baseline'][$h];
            $base[] = $b['n'] === 0 ? '-' : sprintf(
                '%d . trailed %s%% . %s / %s', $b['n'], $this->_num($b['under_rate']), $this->_pts($b['median_rel']),
                $this->_pts($b['avg_rel'])
            );
        }
        $rows[] = $base;
        $this->table($headers, $rows);
        $this->comment('Worked: a rare high or a deterioration trailed VUSA.AS, a rare low beat it. Returns are'
            . ' points against VUSA.AS. One path, overlapping episodes: a sanity check, not proof.');
    }

    /**
     * The grid at the longest horizon: per cell and signal (all episodes), worked % and the median.
     */
    private function _printGrid(array $reports): void
    {
        $h       = max(Backtest::HORIZONS);
        $signals = [];
        foreach ($reports as $report) {
            foreach ($report['summary'] as $row) {
                if ($row['group'] === Backtest::GROUP_ALL) {
                    $signals[$row['signal']] = true;
                }
            }
        }
        $signals = array_keys($signals);
        sort($signals);

        $rows = [];
        foreach ($reports as $report) {
            $line = [$this->_num($report['rare_share']) . ' / ' . $this->_num($report['consistency'])];
            foreach ($signals as $signal) {
                $s      = $report['summary'][$signal . '|' . Backtest::GROUP_ALL]['horizons'][$h] ?? null;
                $line[] = $s === null || $s['n'] === 0 ? '-'
                    : sprintf('%s%% . %s', $this->_num($s['hit_rate']), $this->_pts($s['median_rel']));
            }
            $line[] = $this->_num($report['baseline'][$h]['under_rate']) . '%';
            $rows[] = $line;
        }
        $this->line('');
        $this->info("Grid at {$h} trading days: worked % . median points");
        $this->table(array_merge(['Rare share / consistency'], $signals, ['Baseline trailed']), $rows);
    }

    private function _num(?float $value): string
    {
        return $value === null ? 'n/a' : MoneyFormat::get_formatted_number_plain($value, 0);
    }

    private function _pts(?float $value): string
    {
        return $value === null ? 'n/a' : ($value > 0 ? '+' : '') . MoneyFormat::get_formatted_number_plain($value, 1);
    }
}
