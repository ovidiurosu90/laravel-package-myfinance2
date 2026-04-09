<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\DB;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Models\Trade;

/**
 * Applies a recorded stock split to the current user's trades and active alerts.
 *
 * Executed inside a DB transaction on split save. For a split of ratio N:1:
 *   - Trade quantity  × N
 *   - Trade unit_price ÷ N
 *   - Alert target_price ÷ N
 *   - All stats_historical rows for the symbol are deleted so the cron re-fetches
 *     Yahoo's split-adjusted historical prices on its next run.
 *
 * Returns a summary array:
 * [
 *   'trades_updated'  => int,
 *   'alerts_adjusted' => int,
 *   'changed_trades'  => array[],
 *   'changed_alerts'  => array[],
 * ]
 */
class ApplySplitService
{
    /**
     * Apply the split to the current user's trades and active price alerts.
     * Runs inside a single DB transaction.
     *
     * @param StockSplit $split
     *
     * @return array{trades_updated: int, alerts_adjusted: int, changed_trades: array, changed_alerts: array}
     */
    public function apply(StockSplit $split): array
    {
        $summary = [
            'trades_updated'  => 0,
            'alerts_adjusted' => 0,
            'changed_trades'  => [],
            'changed_alerts'  => [],
        ];

        DB::connection(config('myfinance2.db_connection'))->transaction(
            function () use ($split, &$summary)
            {
                $tradeResult  = $this->_applyToTrades($split);
                $alertResult  = $this->_applyToAlerts($split);
                $summary      = array_merge($tradeResult, $alertResult);

                $split->trades_updated   = $summary['trades_updated'];
                $split->alerts_adjusted  = $summary['alerts_adjusted'];
                $split->save();

                $this->_clearHistoricalStats($split->symbol);
            }
        );

        return $summary;
    }

    /**
     * Update ALL trades for the split symbol with timestamp <= split_date (current user scope).
     * Both OPEN and CLOSED trades are adjusted — the returns calculation uses
     * market prices from stats_historical (which Yahoo retroactively adjusts after a split),
     * so trade quantities and prices must be on the same split-adjusted scale.
     * Multiplies quantity by ratio, divides unit_price.
     * Appends a split annotation to the trade description.
     *
     * @param StockSplit $split
     *
     * @return array{trades_updated: int, changed_trades: array}
     */
    private function _applyToTrades(StockSplit $split): array
    {
        $ratio      = (int) $split->ratio_numerator;
        $label      = $split->getRatioLabel();
        $date       = $split->split_date->format('Y-m-d');
        $annotation = $this->_buildAnnotation($label, $date);

        $trades = Trade::where('symbol', $split->symbol)
            ->whereDate('timestamp', '<=', $date)
            ->with(['accountModel', 'tradeCurrencyModel'])
            ->get();

        $changedTrades = [];
        foreach ($trades as $trade) {
            $oldQty   = (string) $trade->quantity;
            $oldPrice = (string) $trade->unit_price;
            $newQty   = $this->_computeNewQuantity($oldQty, $ratio);
            $newPrice = $this->_computeNewPrice($oldPrice, $ratio);

            $changedTrades[] = [
                'id'           => $trade->id,
                'account'      => $trade->accountModel?->name ?? '—',
                'date'         => $trade->timestamp?->format('Y-m-d') ?? '',
                'action'       => $trade->action,
                'old_quantity' => (float) $oldQty,
                'new_quantity' => (float) $newQty,
                'old_price'    => (float) $oldPrice,
                'new_price'    => (float) $newPrice,
                'currency'     => $trade->tradeCurrencyModel?->iso_code ?? '',
            ];

            $trade->quantity    = $newQty;
            $trade->unit_price  = $newPrice;
            $trade->description = trim(($trade->description ?? '') . ' ' . $annotation);
            $trade->save();
        }

        return ['trades_updated' => $trades->count(), 'changed_trades' => $changedTrades];
    }

    /**
     * Update all ACTIVE price alerts for the split symbol (current user scope).
     * Divides target_price by ratio.
     * Appends a split annotation to the alert notes.
     *
     * @param StockSplit $split
     *
     * @return array{alerts_adjusted: int, changed_alerts: array}
     */
    private function _applyToAlerts(StockSplit $split): array
    {
        $ratio      = (int) $split->ratio_numerator;
        $label      = $split->getRatioLabel();
        $date       = $split->split_date->format('Y-m-d');
        $annotation = $this->_buildAnnotation($label, $date);

        $alerts = PriceAlert::where('symbol', $split->symbol)
            ->where('status', 'ACTIVE')
            ->get();

        $changedAlerts = [];
        foreach ($alerts as $alert) {
            $oldPrice = (string) $alert->target_price;
            $newPrice = $this->_computeNewAlertPrice($oldPrice, $ratio);

            $changedAlerts[] = [
                'id'               => $alert->id,
                'alert_type'       => $alert->alert_type,
                'old_target_price' => (float) $oldPrice,
                'new_target_price' => (float) $newPrice,
            ];

            $alert->target_price = $newPrice;
            $alert->notes        = trim(($alert->notes ?? '') . ' ' . $annotation);
            $alert->save();
        }

        return ['alerts_adjusted' => $alerts->count(), 'changed_alerts' => $changedAlerts];
    }

    /**
     * New quantity = old quantity × ratio (8 decimal places via bcmath).
     *
     * @param string $quantity
     * @param int    $ratio
     *
     * @return string
     */
    private function _computeNewQuantity(string $quantity, int $ratio): string
    {
        return bcmul($quantity, (string) $ratio, 8);
    }

    /**
     * New trade unit price = old price ÷ ratio (4 decimal places via bcmath).
     *
     * @param string $price
     * @param int    $ratio
     *
     * @return string
     */
    private function _computeNewPrice(string $price, int $ratio): string
    {
        return bcdiv($price, (string) $ratio, 4);
    }

    /**
     * New alert target price = old price ÷ ratio (6 decimal places via bcmath).
     *
     * @param string $price
     * @param int    $ratio
     *
     * @return string
     */
    private function _computeNewAlertPrice(string $price, int $ratio): string
    {
        return bcdiv($price, (string) $ratio, 6);
    }

    /**
     * Delete all stats_historical rows for the symbol.
     * Yahoo Finance retroactively adjusts historical prices after a split, so cached
     * pre-split prices become stale. Clearing them forces the cron to re-fetch
     * the correct split-adjusted values on its next run.
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
     * Build the split annotation appended to trade descriptions and alert notes.
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
