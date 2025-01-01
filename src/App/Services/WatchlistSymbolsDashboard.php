<?php

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\PositionsDashboard;

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
        $watchlistSymbols = WatchlistSymbol::all();
        $watchlistSymbolsDictionary = [];
        foreach ($watchlistSymbols as $watchlistSymbol) {
            $watchlistSymbolsDictionary[$watchlistSymbol->symbol] = $watchlistSymbol;
        }

        $positionsService = new PositionsDashboard();
        $positionsData = $positionsService->handle(array_keys($watchlistSymbolsDictionary));
        if (empty($positionsData['quotes'])) {
            return [];
        }

        // LOG::debug('watchlistSymbolsDictionary: '); LOG::debug($watchlistSymbolsDictionary);
        $items = $positionsData['quotes'];
        foreach ($items as $symbol => $quoteData) {
            if (empty($watchlistSymbolsDictionary[$symbol])) {
                // We have a trade for a symbol that is not in tnhe watchlist
                $newWatchlistSymbol = WatchlistSymbol::create([
                    'symbol' => $symbol,
                    'description' => 'Automatically created due to existing trades!',
                ]);
                $watchlistSymbolsDictionary[$symbol] = $newWatchlistSymbol;

            }
            $items[$symbol]['item'] = $watchlistSymbolsDictionary[$symbol];
            $items[$symbol]['open_positions'] = [];

        }
        if (empty($positionsData['groupedItems'])) {
            return $items;
        }

        foreach ($positionsData['groupedItems'] as $account => $openPositions) {
            foreach ($openPositions as $openPosition) {
                $items[$openPosition['symbol']]['open_positions'][] = $openPosition;
            }
        }

        // LOG::debug('WatchlistSymbols handle items: '); LOG::debug($items);
        return $items;
    }

}

