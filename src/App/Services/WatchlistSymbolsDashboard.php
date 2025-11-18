<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\Positions;

use Illuminate\Support\Facades\Log;

class WatchlistSymbolsDashboard
{
    /**
     * Execute the job.
     *
     * @return array (item1, item2, ...)
     */
    public function handle()
    {
        $currencyUtilsService = new CurrencyUtils(true);
        $watchlistSymbols = WatchlistSymbol::all();
        $watchlistSymbolsDictionary = [];
        foreach ($watchlistSymbols as $watchlistSymbol) {
            $watchlistSymbolsDictionary[$watchlistSymbol->symbol] = $watchlistSymbol;
        }

        $positionsService = new Positions();
        $positionsService->setExtraSymbols(array_keys($watchlistSymbolsDictionary));
        $positionsData = $positionsService->handle();
        if (empty($positionsData['quotes'])) {
            return [];
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

