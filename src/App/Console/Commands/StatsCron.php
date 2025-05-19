<?php

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Models\StatToday;

class StatsCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:stats-cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stats Cron';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->cleanupStatsToday();
    }

    public function cleanupStatsToday()
    {
        Log::info('START app:stats-cron cleanupStatsToday()');
        $connection = config('myfinance2.db_connection');
        $table = (new StatToday())->getTable();
        $deleted = \DB::connection($connection)->delete("
            DELETE FROM `$table`
            WHERE DATE(`timestamp`) <> CURDATE();
        ");

        $message = 'END app:stats-cron cleanupStatsToday() => '
            . $deleted . ' deleted rows!';

        Log::info($message);
    }
}

