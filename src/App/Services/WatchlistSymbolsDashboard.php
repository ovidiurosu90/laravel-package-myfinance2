<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Services\Positions;

use Illuminate\Support\Facades\Log;

class WatchlistSymbolsDashboard
{
    /**
     * Execute the job.
     *
     * @return array (item1, item2, ...)
     */
    public function handle(): array
    {
        $currencyUtilsService = new CurrencyUtils(true);
        $watchlistSymbols = WatchlistSymbol::all();
        $watchlistSymbolsDictionary = [];
        foreach ($watchlistSymbols as $watchlistSymbol) {
            $watchlistSymbolsDictionary[$watchlistSymbol->symbol] = $watchlistSymbol;
        }

        $positionsService = new Positions();
        $positionsService->setExtraSymbols(array_keys($watchlistSymbolsDictionary));
        $positionsService->setPersistStats(false);
        $positionsData = $positionsService->handle();
        if (empty($positionsData['quotes'])) {
            return [];
        }

        $openOrders = Order::whereIn('status', ['DRAFT', 'PLACED'])->get();
        $openOrdersBySymbol = [];
        foreach ($openOrders as $order) {
            $openOrdersBySymbol[$order->symbol][] = $order;
        }

        $quoteSymbols = array_keys($positionsData['quotes']);
        $activeAlerts = PriceAlert::whereIn('symbol', $quoteSymbols)
            ->where('status', 'ACTIVE')
            ->get();
        $activeAlertsBySymbol = [];
        foreach ($activeAlerts as $alert) {
            $activeAlertsBySymbol[$alert->symbol][] = $alert;
        }

        $stockSplits = StockSplit::whereIn('symbol', $quoteSymbols)
            ->orderBy('split_date', 'desc')
            ->get();
        $stockSplitsBySymbol = [];
        foreach ($stockSplits as $split) {
            $stockSplitsBySymbol[$split->symbol][] = $split;
        }

        $items = $positionsData['quotes'];
        foreach ($items as $symbol => $quoteData) {
            if (empty($watchlistSymbolsDictionary[$symbol])) {
                // We have a trade for a symbol that is not in the watchlist
                $watchlistSymbolsDictionary[$symbol] =
                    $this->createWatchlistSymbol($symbol);

            }
            $items[$symbol]['tradeCurrencyModel'] =
                $currencyUtilsService->getCurrencyByIsoCode($quoteData['currency']);
            $items[$symbol]['item'] = $watchlistSymbolsDictionary[$symbol];
            $items[$symbol]['open_positions'] = [];
            $items[$symbol]['open_orders'] = $openOrdersBySymbol[$symbol] ?? [];
            $items[$symbol]['active_alerts'] = $activeAlertsBySymbol[$symbol] ?? [];
            $items[$symbol]['stock_splits']  = $stockSplitsBySymbol[$symbol] ?? [];
            $items[$symbol]['base_value'] = null;
        }
        if (empty($positionsData['groupedItems'])) {
            return $items;
        }

        $averageUnitCosts = [];
        foreach ($positionsData['groupedItems'] as $account => $openPositions) {
            foreach ($openPositions as $openPosition) {
                $isUnlisted = FinanceAPI::isUnlisted($openPosition['symbol']);
                if (empty($items[$openPosition['symbol']]) && $isUnlisted) {
                    continue;
                }
                $items[$openPosition['symbol']]['open_positions'][] = $openPosition;
                $averageUnitCosts[$openPosition['symbol']][] =
                    $openPosition['average_unit_cost_in_trade_currency'];
            }
        }
        foreach ($averageUnitCosts as $symbol => $costs) {
            $items[$symbol]['base_value'] = array_sum($costs) / count($costs);
        }

        // LOG::debug('WatchlistSymbols handle items: '); LOG::debug($items);
        return $items;
    }

    public function createWatchlistSymbol(string $symbol): WatchlistSymbol
    {
        return WatchlistSymbol::create([
            'symbol' => $symbol,
            'description' => 'Automatically created due to existing trades!',
        ]);
    }

}

