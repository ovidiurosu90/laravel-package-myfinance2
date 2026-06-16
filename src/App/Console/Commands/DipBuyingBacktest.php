<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use ovidiuro\myfinance2\App\Services\DipBuyingBacktestService;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Self-validation backtest for the Dip Buying Plan (spec section 5).
 *
 * Replays a user's own Trade history through the shared ladder engine over a window (default Jan
 * 2025 to now) and prints the per-episode actual-vs-guided comparison plus the stay-invested and
 * monthly-DCA baselines. Becomes the user (auth()->setUser) so the overview chart series resolve,
 * then runs read-only. Same engine and report as the /dip-buying-alerts/backtest page.
 */
class DipBuyingBacktest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finance:dip-buying-backtest
        {--user-id= : User to backtest (required)}
        {--from= : Window start date Y-m-d (default 2025-01-01)}
        {--pool= : Override the configured pool size in EUR}';

    /**
     * @var string
     */
    protected $description = 'Replay your trades through the Dip Buying ladder and compare to what the ladder would have done';

    public function handle(): int
    {
        $userId = $this->option('user-id');
        if (!$userId) {
            $this->error('Provide --user-id=N');
            return Command::FAILURE;
        }

        $user = User::find((int) $userId);
        if (!$user) {
            $this->error("User #{$userId} not found.");
            return Command::FAILURE;
        }

        $from = $this->option('from') ?: null;
        $pool = $this->option('pool') !== null ? (float) $this->option('pool') : null;

        auth()->setUser($user);
        try {
            $report = (new DipBuyingBacktestService())->build((int) $userId, $from, $pool);
        } finally {
            auth()->forgetGuards();
        }

        $this->_print($report);

        return Command::SUCCESS;
    }

    /**
     * @param array $report
     *
     * @return void
     */
    private function _print(array $report): void
    {
        $poolNote = $report['pool_source'] === 'override'
            ? 'pool EUR ' . MoneyFormat::get_formatted_number_plain((float) $report['pool_eur'], 0)
            : 'pool = your cash at each episode start';
        $this->info("Self-validation backtest . {$report['from']} -> {$report['to']} . {$poolNote}");
        $this->line('');

        if (empty($report['episodes'])) {
            $this->warn($report['headline']);
            return;
        }

        foreach ($report['episodes'] as $i => $ep) {
            $n    = $i + 1;
            $a    = $ep['assessment'];
            $pool = MoneyFormat::get_formatted_number_plain((float) $ep['pool_eur'], 0);
            $tag  = ['good' => 'ON TRACK', 'average' => 'COULD IMPROVE', 'bad' => 'MISTAKE'][$a['status']] ?? '';

            $this->line("Episode {$n}   peak {$ep['peak_date']}  ->  low {$ep['low_date']}  "
                . "(-{$ep['max_dd']}%)  pool EUR {$pool}  [{$tag}]");

            $exhaust = $ep['actual']['exhaustion_dd'] !== null ? "-{$ep['actual']['exhaustion_dd']}%" : 'n/a';
            $reserve = MoneyFormat::get_formatted_number_plain((float) $ep['guided']['reserve_kept_eur'], 0);
            $delta   = ($a['entry_dd_delta'] >= 0 ? '+' : '') . $a['entry_dd_delta'];
            $gap     = ($a['deploy_gap_pct'] >= 0 ? '+' : '') . $a['deploy_gap_pct'];

            $this->line("  Actual:   deployed {$ep['actual']['deployed_pct']}% "
                . "(all-in at {$exhaust}), avg entry drawdown -{$ep['actual']['avg_entry_dd']}%");
            $this->line("  Guided:   target {$ep['guided']['target_pct']}%, "
                . "avg entry drawdown -{$ep['guided']['avg_entry_dd']}%, reserve kept EUR {$reserve}");
            $this->line("  How far:  entry depth {$delta} drawdown pts, deployed vs target {$gap} pts of pool");
            $this->line("            {$a['headline']}");
            $this->line('');
        }

        $b = $report['baselines'];
        $this->line('Baselines (the bars to clear)');
        $this->line('  Stay fully invested ...  ' . $this->_pct($b['stay_invested_pct']));
        $this->line('  Monthly DCA ...........  ' . $this->_pct($b['dca_pct']));
        $this->line('  Guided ladder .........  ' . $this->_pct($b['guided_pct']));
        $this->line('');
        $this->info($report['headline']);
        $this->comment($report['note']);
    }

    /**
     * @param float|null $value
     *
     * @return string
     */
    private function _pct(?float $value): string
    {
        if ($value === null) {
            return 'n/a';
        }

        return ($value >= 0 ? '+' : '') . MoneyFormat::get_formatted_pct($value) . '%';
    }
}
