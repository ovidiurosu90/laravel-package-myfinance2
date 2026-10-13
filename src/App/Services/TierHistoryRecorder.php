<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use ovidiuro\myfinance2\App\Models\SymbolTierHistory;

/**
 * Writes the daily tier history (symbol_tier_history): one row per trading day and rated symbol, as
 * TierAsOfClassifier reconstructs it. Used by finance:tier-history-backfill (a long range, once) and
 * by the hourly cron (the last few weekdays, rewritten every run). Upserts on (user, symbol, date),
 * so a re-run over the same range changes nothing unless the inputs changed, and removes rows of the
 * range that no longer apply (a holiday, or a day that became unrated).
 *
 * Weekdays are walked; a symbol gets a row only on days its exchange has a close, so weekends and
 * holidays are left out.
 *
 * Written through the query builder: the cron has no authenticated user for the base model's
 * creating hook (same reason as CategorizationService::_persistTierStates()).
 */
final class TierHistoryRecorder
{
    public const SOURCE_BACKFILL = 'backfill';
    public const SOURCE_CRON     = 'cron';

    private const CHUNK_SIZE = 1000;

    /**
     * @param callable|null $onDay Called after each weekday with (Carbon $day), for a progress bar.
     *
     * @return array{days: int, rows: int, skipped: array<string, string[]>, tiers: array<string, array<string, int>>,
     *               gap_filled: string[]}
     *         tiers: symbol => tier => weekdays, the per-symbol summary the dry run prints.
     */
    public function recordRange(
        int $userId,
        Carbon $from,
        Carbon $to,
        ?string $symbol,
        string $source,
        bool $dryRun = false,
        bool $fetchGaps = true,
        ?callable $onDay = null
    ): array
    {
        $data       = new PerformanceDataset($userId, $from, $fetchGaps, $symbol);
        $classifier = new TierAsOfClassifier();
        $summary    = ['days' => 0, 'rows' => 0, 'skipped' => [], 'tiers' => [], 'gap_filled' => []];
        $buffer     = [];

        foreach (self::weekdays($from, $to) as $day) {
            $result = $classifier->classifyDay($data, $day, $symbol);
            $date   = $day->format('Y-m-d');
            if (!$dryRun) {
                $this->_deleteStale($userId, $date, array_keys($result['rows']), $symbol);
            }
            foreach ($result['rows'] as $sym => $row) {
                $tierKey = $row['tier'] ?? 'NONE';
                $summary['tiers'][$sym][$tierKey] = ($summary['tiers'][$sym][$tierKey] ?? 0) + 1;
                $buffer[] = $this->_dbRow($userId, $sym, $date, $row, $source);
            }
            foreach ($result['skipped'] as $sym => $reason) {
                $summary['skipped'][$sym][] = $date;
            }
            if (!$dryRun && count($buffer) >= self::CHUNK_SIZE) {
                $summary['rows'] += $this->_flush($buffer);
                $buffer = [];
            }
            $summary['days']++;
            if ($onDay !== null) {
                $onDay($day);
            }
        }

        if ($dryRun) {
            $summary['rows'] = count($buffer) + $summary['rows'];
        } elseif (!empty($buffer)) {
            $summary['rows'] += $this->_flush($buffer);
        }
        $summary['gap_filled'] = $data->gapFilledSymbols();

        return $summary;
    }

    /**
     * Monday to Friday between two dates, inclusive. Exchange holidays are among them; recordRange() stores
     * no row for a day without a close.
     *
     * @return Carbon[]
     */
    public static function weekdays(Carbon $from, Carbon $to): array
    {
        $days = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            if (!$day->isWeekend()) {
                $days[] = $day->copy();
            }
        }

        return $days;
    }

    /**
     * The weekday $count weekdays before $day (the cron's rewrite window start).
     */
    public static function weekdaysBefore(Carbon $day, int $count): Carbon
    {
        $start = $day->copy()->startOfDay();
        for ($i = 0; $i < $count; ) {
            $start->subDay();
            if (!$start->isWeekend()) {
                $i++;
            }
        }

        return $start;
    }

    private function _dbRow(int $userId, string $symbol, string $date, array $row, string $source): array
    {
        $now = now();

        return [
            'user_id'     => $userId,
            'symbol'      => $symbol,
            'date'        => $date,
            'basis'       => $row['basis'],
            'basis_value' => $row['basis_value'],
            'market_value' => $row['market_value'],
            'own_value'   => $row['own_value'],
            'holding_value' => $row['holding_value'],
            'holding_since' => $row['holding_since'],
            'tier'        => $row['tier'],
            'is_owned'    => $row['is_owned'] ? 1 : 0,
            'source'      => $source,
            'created_at'  => $now,
            'updated_at'  => $now,
            'deleted_at'  => null,
        ];
    }

    /**
     * Hard-delete the day's rows that this run did not produce (derived data, so nothing is lost).
     *
     * @param string[] $keep symbols with a row on $date
     */
    private function _deleteStale(int $userId, string $date, array $keep, ?string $symbol): void
    {
        $model = new SymbolTierHistory();
        DB::connection($model->getConnectionName())
            ->table($model->getTable())
            ->where('user_id', $userId)
            ->where('date', $date)
            ->when($symbol !== null, fn ($q) => $q->where('symbol', $symbol))
            ->when(!empty($keep), fn ($q) => $q->whereNotIn('symbol', $keep))
            ->delete();
    }

    private function _flush(array $rows): int
    {
        $model = new SymbolTierHistory();
        DB::connection($model->getConnectionName())
            ->table($model->getTable())
            ->upsert(
                $rows,
                ['user_id', 'symbol', 'date'],
                [
                    'basis', 'basis_value', 'market_value', 'own_value', 'holding_value', 'holding_since',
                    'tier', 'is_owned', 'source', 'updated_at', 'deleted_at',
                ]
            );

        return count($rows);
    }
}
