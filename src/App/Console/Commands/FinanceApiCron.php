<?php

namespace ovidiuro\myfinance2\App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Finance API Cron Command
 *
 * Handles various finance-related cron operations:
 * - Quotes refresh (FinanceApiCronQuotesTrait)
 * - Exchange rates refresh (FinanceApiCronQuotesTrait)
 * - Historical data fetching (FinanceApiCronQuotesTrait)
 * - Account overview and charts (FinanceApiCronChartsTrait)
 * - Returns calculation (FinanceApiCronReturnsTrait)
 */
class FinanceApiCron extends Command
{
    use FinanceApiCronQuotesTrait;
    use FinanceApiCronChartsTrait;
    use FinanceApiCronReturnsTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:finance-api-cron
        {--historical}
        {--historical-account-overview}
        {--refresh-returns}
        {--force}
        {--start=}
        {--end=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finance API Cron';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Disable user scope for CLI context - all queries will work without auth()->user()
        AssignedToUserScope::disable();

        $lock = Cache::lock('finance-api-cron', 55); // seconds

        if (!$lock->get()) {
            Log::info('Already running, skipping...');
            return Command::SUCCESS;
        }

        $start = $this->option('start');
        $end = $this->option('end');
        $historical = $this->option('historical');
        $historicalAccountOverview = $this->option('historical-account-overview');
        $refreshReturns = $this->option('refresh-returns');

        if (!$start && ($historical || $historicalAccountOverview)) {
            Log::error('Missing option --start');
            return Command::FAILURE;
        }
        if (!$end) {
            $end = date(trans('myfinance2::general.date-format'));
        }

        try {
            if ($historical) {
                $this->fetchHistorical($start, $end);
                return Command::SUCCESS;
            }

            if ($historicalAccountOverview) {
                $this->runHistoricalAccountOverview($start, $end);
                return Command::SUCCESS;
            }

            if ($refreshReturns) {
                $this->refreshReturns();
                return Command::SUCCESS;
            }

            // Default: minutely cron
            $this->refreshQuotes();
            $this->refreshExchangeRates();
            $this->refreshAccountOverview();
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
