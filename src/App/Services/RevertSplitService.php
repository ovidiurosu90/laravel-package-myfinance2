<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Models\Trade;

/**
 * Reverts a previously applied stock split for the current user's trades and active alerts.
 *
 * Locates records that still carry the split annotation in their description/notes
 * and reverses the mathematical adjustments made by ApplySplitService.
 *
 * For a split of ratio N:1:
 *   - Trade quantity  ÷ N  (reverses the ×N applied during the split)
 *   - Trade unit_price × N  (reverses the ÷N applied during the split)
 *   - Alert target_price × N  (reverses the ÷N applied during the split)
 *
 * Returns a summary array:
 * [
 *   'trades_reverted' => int,
 *   'alerts_reverted' => int,
 * ]
 */
class RevertSplitService
{
    /**
     * Revert the split's effect on OPEN trades and ACTIVE price alerts.
     * Runs inside a single DB transaction. Marks the split as reverted upon success.
     *
     * @param StockSplit $split
     *
     * @return array{trades_reverted: int, alerts_reverted: int}
     */
    public function revert(StockSplit $split): array
    {
        $summary = [
            'trades_reverted' => 0,
            'alerts_reverted' => 0,
        ];

        DB::connection(config('myfinance2.db_connection'))->transaction(
            function () use ($split, &$summary)
            {
                $summary['trades_reverted'] = $this->_revertTrades($split);
                $summary['alerts_reverted'] = $this->_revertAlerts($split);
                $split->reverted_at         = Carbon::now();
                $split->save();

                $this->_clearHistoricalStats($split->symbol);
            }
        );

        return $summary;
    }

    /**
     * Count how many trades (open and closed) and ACTIVE alerts still carry the split
     * annotation, without making any changes. Used to populate the confirmation modal.
     *
     * @param StockSplit $split
     *
     * @return array{trades_found: int, alerts_found: int}
     */
    public function preview(StockSplit $split): array
    {
        $annotation = $this->_buildAnnotation($split->getRatioLabel(), $split->split_date->format('Y-m-d'));

        return [
            'trades_found' => Trade::where('symbol', $split->symbol)
                ->where('description', 'LIKE', '%' . $annotation . '%')
                ->count(),
            'alerts_found' => PriceAlert::where('symbol', $split->symbol)
                ->where('status', 'ACTIVE')
                ->where('notes', 'LIKE', '%' . $annotation . '%')
                ->count(),
        ];
    }

    /**
     * Revert ALL trades (open and closed) that carry the split annotation in their description.
     * Divides quantity by ratio and multiplies unit_price by ratio (inverse of apply).
     *
     * @param StockSplit $split
     *
     * @return int number of trades reverted
     */
    private function _revertTrades(StockSplit $split): int
    {
        $ratio      = (int) $split->ratio_numerator;
        $annotation = $this->_buildAnnotation($split->getRatioLabel(), $split->split_date->format('Y-m-d'));

        $trades = Trade::where('symbol', $split->symbol)
            ->where('description', 'LIKE', '%' . $annotation . '%')
            ->get();

        foreach ($trades as $trade) {
            $trade->quantity    = bcdiv((string) $trade->quantity, (string) $ratio, 8);
            $trade->unit_price  = bcmul((string) $trade->unit_price, (string) $ratio, 4);
            $trade->description = trim(str_replace($annotation, '', $trade->description ?? ''));
            $trade->save();
        }

        return $trades->count();
    }

    /**
     * Revert ACTIVE price alerts that carry the split annotation in their notes.
     * Multiplies target_price by ratio (inverse of apply).
     *
     * @param StockSplit $split
     *
     * @return int number of alerts reverted
     */
    private function _revertAlerts(StockSplit $split): int
    {
        $ratio      = (int) $split->ratio_numerator;
        $annotation = $this->_buildAnnotation($split->getRatioLabel(), $split->split_date->format('Y-m-d'));

        $alerts = PriceAlert::where('symbol', $split->symbol)
            ->where('status', 'ACTIVE')
            ->where('notes', 'LIKE', '%' . $annotation . '%')
            ->get();

        foreach ($alerts as $alert) {
            $alert->target_price = bcmul((string) $alert->target_price, (string) $ratio, 6);
            $alert->notes        = trim(str_replace($annotation, '', $alert->notes ?? ''));
            $alert->save();
        }

        return $alerts->count();
    }

    /**
     * Delete all stats_historical rows for the symbol so the cron re-fetches
     * Yahoo's current (now un-adjusted) historical prices on its next run.
     *
     * @param string $symbol
     *
     * @return void
     */
    private function _clearHistoricalStats(string $symbol): void
    {
        DB::connection(config('myfinance2.db_connection'))
            ->table((new StatHistorical())->getTable())
            ->where('symbol', $symbol)
            ->delete();
    }

    /**
     * Reconstruct the annotation string that ApplySplitService appended.
     *
     * @param string $label e.g. "25:1"
     * @param string $date  e.g. "2026-04-06"
     *
     * @return string e.g. "[Split 25:1 applied 2026-04-06]"
     */
    private function _buildAnnotation(string $label, string $date): string
    {
        return "[Split {$label} applied {$date}]";
    }
}
