<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Console\Command;

use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\OrderTierWarnings;
use ovidiuro\myfinance2\App\Services\OrderWarningReview as Review;
use ovidiuro\myfinance2\App\Services\AppUser;

/**
 * Calibration of the order warnings (step 0 of the order tier readings): rebuilds the tier reading of every past
 * trade's day, evaluates the order warnings on it and prints, per action and per code, how often it fired and how
 * those decisions did against VUSA.AS 21 / 63 / 126 trading days later, next to every decision of that action; then
 * the level the decision rule suggests and the config to paste (myfinance2.watchlistsymbols.order_warnings.levels).
 * Read-only.
 */
class OrderWarningReview extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:order-warning-review
        {--user-id= : User to review (required)}
        {--from= : First trade day, Y-m-d (default: the tier history start)}
        {--details : List every decision with its tier and warnings}';

    /**
     * @var string
     */
    protected $description = 'Calibrate the order warnings: how past trades that would have raised each warning did'
        . ' against VUSA.AS';

    public function handle(): int
    {
        $userId = (int) $this->option('user-id');
        if ($userId <= 0 || !AppUser::find($userId)) {
            $this->error('Provide --user-id=N of an existing user.');
            return Command::FAILURE;
        }
        $report = (new Review())->run($userId, $this->option('from') ?: null);

        $this->info(sprintf('Order warning review from %s: %d decisions with a reading, %d before the stored history'
            . ' (left out).', $report['from'], count($report['decisions']), $report['none']));
        if ($this->option('details')) {
            $this->_printDetails($report['decisions']);
        }
        $this->_printCodes($report);
        $this->_printSuggested($report['suggested']);

        return Command::SUCCESS;
    }

    private function _printCodes(array $report): void
    {
        $headers = ['Code', 'Action', 'Fired', 'Share'];
        foreach (Review::HORIZONS as $h) {
            $headers[] = "{$h}d: n . right . median";
        }
        $rows = [];
        foreach (['BUY', 'SELL'] as $action) {
            $rows[] = $this->_row('Baseline (every ' . $action . ')', $action, $report['baseline'][$action], null);
            foreach ($report['codes'] as $code => $stats) {
                if ($stats['action'] === $action) {
                    $rows[] = $this->_row($code, $action, $stats, $stats['share_pct']);
                }
            }
        }
        $this->table($headers, $rows);
        $this->comment('Right: a BUY that then beat VUSA.AS, a SELL of a symbol that then trailed it. Median: points'
            . ' against VUSA.AS, signed so that positive means the decision was right. One portfolio, small samples:'
            . ' a sanity check, not proof.');
    }

    private function _row(string $label, string $action, array $stats, ?float $share): array
    {
        $line = [$label, $action, $stats['count'], $share === null ? '' : $this->_num($share, 0) . '%'];
        foreach (Review::HORIZONS as $h) {
            $s      = $stats[$h];
            $line[] = $s['n'] === 0 ? '-' : sprintf('%d . %s%% . %s', $s['n'], $this->_num($s['hit_rate'], 0),
                $this->_pts($s['median']));
        }

        return $line;
    }

    private function _printSuggested(array $suggested): void
    {
        $config  = OrderTierWarnings::fromConfig();
        $rows    = [];
        $snippet = [];
        foreach ($suggested as $code => $s) {
            $rows[]    = [$code, $config->level($code), $s['level'], $s['reason']];
            $snippet[] = sprintf("            '%s' => '%s',", $code, $s['level']);
        }
        $this->line('');
        $this->info('Suggested levels (serious: did clearly worse than the baseline at '
            . max(Review::HORIZONS) . ' days, at least ' . Review::MIN_DECISIONS . ' decisions, fires on under '
            . $this->_num(Review::MAX_SHARE_PCT, 0) . '% of them)');
        $this->table(['Code', 'Configured', 'Suggested', 'Why'], $rows);
        $this->line("Paste into config myfinance2.watchlistsymbols.order_warnings.levels:\n" . implode("\n", $snippet));
    }

    private function _printDetails(array $decisions): void
    {
        $rows = [];
        foreach ($decisions as $d) {
            $rows[] = [
                $d['date'], $d['kind'] . ' #' . $d['id'], $d['action'], $d['symbol'], $d['tier'] ?? '-',
                implode(', ', array_map(fn ($w) => $w['code'] . ($w['level'] === OrderTierWarnings::LEVEL_SOFT
                    ? ' (softened)' : ''), $d['warnings'])) ?: '-',
                $this->_pts($d['excess'][max(Review::HORIZONS)]),
            ];
        }
        $this->table(['Date', 'Decision', 'Action', 'Symbol', 'Tier', 'Warnings', max(Review::HORIZONS) . 'd'], $rows);
    }

    private function _num(?float $value, int $decimals): string
    {
        return $value === null ? 'n/a' : MoneyFormat::get_formatted_number_plain($value, $decimals);
    }

    private function _pts(?float $value): string
    {
        return $value === null ? 'n/a' : ($value > 0 ? '+' : '') . MoneyFormat::get_formatted_number_plain($value, 1);
    }
}
