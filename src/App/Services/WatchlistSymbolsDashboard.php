<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Services\Positions;
use ovidiuro\myfinance2\App\Services\SymbolPerformanceService;
use ovidiuro\myfinance2\App\Services\TechnicalIndicatorsService;
use ovidiuro\myfinance2\App\Services\FinanceUtils;

use Illuminate\Support\Facades\Log;

class WatchlistSymbolsDashboard
{
    /**
     * Execute the job.
     *
     * Returns an array of symbol => quoteData where each item includes:
     *   - 'is_on_watchlist' (bool) — false for traded symbols not on the watchlist
     *   - 'performance'     (array) — from SymbolPerformanceService
     *
     * @return array (symbol => quoteData)
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
            $isOnWatchlist = isset($watchlistSymbolsDictionary[$symbol]);
            if (!$isOnWatchlist) {
                $placeholder = new WatchlistSymbol();
                $placeholder->symbol = $symbol;
                $watchlistSymbolsDictionary[$symbol] = $placeholder;
            }
            $items[$symbol]['tradeCurrencyModel'] =
                $currencyUtilsService->getCurrencyByIsoCode($quoteData['currency']);
            $items[$symbol]['item'] = $watchlistSymbolsDictionary[$symbol];
            $items[$symbol]['is_on_watchlist'] = $isOnWatchlist;
            $items[$symbol]['open_positions'] = [];
            $items[$symbol]['open_orders'] = $openOrdersBySymbol[$symbol] ?? [];
            $items[$symbol]['active_alerts'] = $activeAlertsBySymbol[$symbol] ?? [];
            $items[$symbol]['stock_splits']  = $stockSplitsBySymbol[$symbol] ?? [];
            $items[$symbol]['base_value'] = null;
        }
        // Build live EUR rates from the already-fetched exchange rate data so that
        // applyLivePrices() uses the same rate source as the open positions card.
        $liveEurRates = ['EUR' => 1.0];
        foreach ($positionsData['exchangeRateData'] ?? [] as $data) {
            if (($data['account_currency'] ?? null) === 'EUR'
                && !empty($data['exchange_rate'])
                && $data['exchange_rate'] > 0
            ) {
                $liveEurRates[$data['trade_currency']] = 1.0 / (float) $data['exchange_rate'];
            }
        }
        if (isset($liveEurRates['GBP'])) {
            $liveEurRates['GBp'] = $liveEurRates['GBP'] / 100.0;
            $liveEurRates['GBX'] = $liveEurRates['GBP'] / 100.0;
        }

        if (empty($positionsData['groupedItems'])) {
            $items = $this->_attachPerformance(
                $items, $watchlistSymbolsDictionary, $currencyUtilsService, $liveEurRates
            );
            return (new TechnicalIndicatorsService())->attachIndicators($items);
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

        $items = $this->_attachPerformance(
            $items, $watchlistSymbolsDictionary, $currencyUtilsService, $liveEurRates
        );

        return (new TechnicalIndicatorsService())->attachIndicators($items);
    }

    private function _attachPerformance(
        array $items,
        array $watchlistSymbolsDictionary,
        CurrencyUtils $currencyUtilsService,
        array $liveEurRates = []
    ): array
    {
        $userId = auth()->id();
        $performanceService = new SymbolPerformanceService();
        $performanceBySymbol = $performanceService->handle($userId);

        $liveQuotes = [];
        foreach ($items as $symbol => $quoteData) {
            if (!empty($quoteData['price']) && !empty($quoteData['currency'])) {
                $liveQuotes[$symbol] = [
                    'price'    => (float) $quoteData['price'],
                    'currency' => $quoteData['currency'],
                ];
            }
        }
        if (!empty($liveQuotes)) {
            $performanceService->applyLivePrices($performanceBySymbol, $liveQuotes, $liveEurRates);
        }

        foreach ($items as $symbol => $quoteData) {
            $perf = $performanceBySymbol[$symbol] ?? ['has_data' => false];
            $perf['sector'] = (new FinanceAPI())->getCachedSector($symbol);
            $items[$symbol]['performance'] = $perf;
        }

        // Add fully-exited symbols that have performance data but no open position
        // and are not on the watchlist (those are already included via extraSymbols).
        $exitedSymbols = array_diff(
            array_keys($performanceBySymbol),
            array_keys($items),
            array_keys($watchlistSymbolsDictionary)
        );

        if (!empty($exitedSymbols)) {
            $quotes = (new FinanceUtils())->getQuotes(array_values($exitedSymbols)) ?? [];
            foreach ($exitedSymbols as $symbol) {
                if (empty($quotes[$symbol])) {
                    continue;
                }
                $placeholder = new WatchlistSymbol();
                $placeholder->symbol = $symbol;
                $items[$symbol] = $quotes[$symbol];
                $items[$symbol]['tradeCurrencyModel'] =
                    $currencyUtilsService->getCurrencyByIsoCode($quotes[$symbol]['currency']);
                $items[$symbol]['item']            = $placeholder;
                $items[$symbol]['is_on_watchlist'] = false;
                $items[$symbol]['open_positions']  = [];
                $items[$symbol]['open_orders']     = [];
                $items[$symbol]['active_alerts']   = [];
                $items[$symbol]['stock_splits']    = [];
                $items[$symbol]['base_value']      = null;
                $perf = $performanceBySymbol[$symbol];
                $perf['sector'] = (new FinanceAPI())->getCachedSector($symbol);
                $items[$symbol]['performance']     = $perf;
            }
        }

        return $items;
    }
}
