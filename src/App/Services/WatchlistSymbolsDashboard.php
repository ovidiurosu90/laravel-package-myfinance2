<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\WatchlistSymbol;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Services\CategorizationService;
use ovidiuro\myfinance2\App\Services\PortfolioHealthScore;
use ovidiuro\myfinance2\App\Services\QuadrantChartBuilder;
use ovidiuro\myfinance2\App\Services\WatchlistTableMetaBuilder;
use ovidiuro\myfinance2\App\Services\PeakExitPnlBuilder;
use ovidiuro\myfinance2\App\Services\Positions;
use ovidiuro\myfinance2\App\Services\SymbolPerformanceService;
use ovidiuro\myfinance2\App\Services\TechnicalIndicatorsService;
use ovidiuro\myfinance2\App\Services\FinanceUtils;

class WatchlistSymbolsDashboard
{
    /**
     * Build the watchlist symbols dashboard data.
     *
     * Returns ['items' => (symbol => quoteData), 'health_score' => array, 'quadrant' => array].
     * Each item includes:
     *   - 'is_on_watchlist'  (bool)
     *   - 'performance'      (array) from SymbolPerformanceService
     *   - 'categorization'   (array) tier, quadrant, action, drawdown, exit_zone
     */
    public function handle(): array
    {
        $currencyUtilsService = new CurrencyUtils(true);
        $watchlistSymbols     = WatchlistSymbol::all();
        $watchlistSymbolsDictionary = [];
        foreach ($watchlistSymbols as $watchlistSymbol) {
            $watchlistSymbolsDictionary[$watchlistSymbol->symbol] = $watchlistSymbol;
        }

        $positionsService = new Positions();
        $positionsService->setExtraSymbols(array_keys($watchlistSymbolsDictionary));
        $positionsService->setPersistStats(false);
        $positionsData = $positionsService->handle();
        if (empty($positionsData['quotes'])) {
            return ['items' => [], 'health_score' => null, 'quadrant' => null];
        }

        $openOrders = Order::whereIn('status', ['DRAFT', 'PLACED'])->get();
        $openOrdersBySymbol = [];
        foreach ($openOrders as $order) {
            $openOrdersBySymbol[$order->symbol][] = $order;
        }

        $quoteSymbols = array_keys($positionsData['quotes']);
        $activeAlertsBySymbol = PriceAlert::activeBySymbols($quoteSymbols);

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
            $items[$symbol]['item']           = $watchlistSymbolsDictionary[$symbol];
            $items[$symbol]['is_on_watchlist'] = $isOnWatchlist;
            $items[$symbol]['open_positions']  = [];
            $items[$symbol]['open_orders']     = $openOrdersBySymbol[$symbol] ?? [];
            $items[$symbol]['active_alerts']   = $activeAlertsBySymbol[$symbol] ?? [];
            $items[$symbol]['stock_splits']    = $stockSplitsBySymbol[$symbol] ?? [];
            $items[$symbol]['base_value']      = null;
        }

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

        if (!empty($positionsData['groupedItems'])) {
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
        }

        [$items, $performanceBySymbol] = $this->_attachPerformance(
            $items, $watchlistSymbolsDictionary, $currencyUtilsService, $liveEurRates
        );
        [$items, $categorization] = $this->_attachCategorization(
            $items, $liveEurRates, $performanceBySymbol
        );
        $healthScore = (new PortfolioHealthScore())->build(
            $categorization,
            $positionsData['groupedItems'] ?? [],
            $items
        );
        $items = (new TechnicalIndicatorsService())->attachIndicators($items);
        $items = (new WatchlistTableMetaBuilder())->attach($items, $liveEurRates);
        // Adds the per-period "P&L if sold at this window's peak" to each owned symbol's table_meta;
        // runs after categorization (needs the per-period peak) and table_meta (extends its array).
        $items = (new PeakExitPnlBuilder())->attach($items, $liveEurRates);
        $quadrant = (new QuadrantChartBuilder())->build($items);

        return ['items' => $items, 'health_score' => $healthScore, 'quadrant' => $quadrant];
    }

    private function _attachPerformance(
        array $items,
        array $watchlistSymbolsDictionary,
        CurrencyUtils $currencyUtilsService,
        array $liveEurRates = []
    ): array
    {
        $userId              = auth()->id();
        $performanceService  = new SymbolPerformanceService();
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
            $perf                      = $performanceBySymbol[$symbol] ?? ['has_data' => false];
            $perf['sector']            = (new FinanceAPI())->getCachedSector($symbol);
            $items[$symbol]['performance'] = $perf;
        }

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
                $placeholder                        = new WatchlistSymbol();
                $placeholder->symbol               = $symbol;
                $items[$symbol]                    = $quotes[$symbol];
                $items[$symbol]['tradeCurrencyModel'] =
                    $currencyUtilsService->getCurrencyByIsoCode($quotes[$symbol]['currency']);
                $items[$symbol]['item']            = $placeholder;
                $items[$symbol]['is_on_watchlist'] = false;
                $items[$symbol]['open_positions']  = [];
                $items[$symbol]['open_orders']     = [];
                $items[$symbol]['active_alerts']   = [];
                $items[$symbol]['stock_splits']    = [];
                $items[$symbol]['base_value']      = null;
                $perf                              = $performanceBySymbol[$symbol];
                $perf['sector']                    = (new FinanceAPI())->getCachedSector($symbol);
                $items[$symbol]['performance']     = $perf;
            }
        }

        return [$items, $performanceBySymbol];
    }

    private function _attachCategorization(
        array $items,
        array $eurRates = [],
        array $performance = []
    ): array
    {
        // Hand the live-priced performance to the classifier so the tier basis matches the
        // gain figures shown in the table, instead of an independently fetched cache snapshot.
        $livePerformance = [];
        foreach ($items as $symbol => $quoteData) {
            if (!empty($quoteData['performance']['has_data'])) {
                $livePerformance[$symbol] = $quoteData['performance'];
            }
        }

        // Position-derived returns (market value minus cost) so owned symbols with no
        // performance-service return, such as unlisted holdings valued by a manual FMV,
        // can still be rated instead of falling through to Unrated.
        $positionReturns = $this->_buildPositionReturns($items, $eurRates);

        // Latest live prices (pre/post-market aware) in both native and EUR, so the quadrant's
        // per-window market return and peak proximity reflect the current price, not the last close.
        $liveEurPrices = [];
        foreach ($items as $symbol => $quoteData) {
            if (empty($quoteData['price']) || empty($quoteData['currency'])) {
                continue;
            }
            // Keep the native price even when the EUR rate is unavailable (e.g. a foreign-currency
            // watchlist symbol with no held position to supply the FX rate); peak proximity is
            // computed FX-free in the native currency, so the overlay can still refresh it.
            $eurRate = $eurRates[$quoteData['currency']] ?? null;
            $liveEurPrices[$symbol] = [
                'native' => (float) $quoteData['price'],
                'eur'    => $eurRate !== null ? (float) $quoteData['price'] * (float) $eurRate : null,
            ];
        }

        $categorization = (new CategorizationService())
            ->forUser(auth()->id(), $livePerformance, $positionReturns, $performance, $liveEurPrices);

        foreach ($items as $symbol => $quoteData) {
            $items[$symbol]['categorization'] = $categorization[$symbol] ?? null;
            // Unrealized gain on currently held shares (market value minus cost2), in EUR. This is
            // the "if sold now" figure; it differs from the performance card's lifetime total gain,
            // which also folds in dividends and realized gains. Null when the symbol is not held.
            $items[$symbol]['unrealized_gain_eur'] = $positionReturns[$symbol]['gain_eur'] ?? null;
            $items[$symbol]['unrealized_gain_pct'] = $positionReturns[$symbol]['raw_pct'] ?? null;
            // Today's move on the held shares: the day's P&L in EUR and the symbol's daily price
            // change %. Surfaced in the peak-proximity email to explain "why now".
            $items[$symbol]['today_gain_eur'] = $positionReturns[$symbol]['today_gain_eur'] ?? null;
            $items[$symbol]['today_gain_pct'] = $positionReturns[$symbol]['today_gain_pct'] ?? null;
        }

        return [$items, $categorization];
    }

    /**
     * Build a symbol => ['raw_pct' => ?float, 'days' => int] map from open positions.
     * The percentage cancels currency for single-currency holdings; EUR conversion only
     * matters when a symbol is held across accounts in different currencies.
     */
    private function _buildPositionReturns(array $items, array $eurRates): array
    {
        $returns = [];
        foreach ($items as $symbol => $quoteData) {
            $positions = $quoteData['open_positions'] ?? [];
            if (empty($positions)) {
                continue;
            }

            $mvalue    = 0.0;
            $cost      = 0.0;
            $todayEur  = 0.0;
            $todayPct  = null;
            $earliest  = null;
            foreach ($positions as $position) {
                $currency  = $position['accountModel']->currency->iso_code ?? 'EUR';
                $rate      = (float) ($eurRates[$currency] ?? 1.0);
                $mvalue   += (float) ($position['market_value_in_account_currency'] ?? 0.0) * $rate;
                $cost     += (float) ($position['cost2_in_account_currency'] ?? 0.0) * $rate;
                $todayEur += (float) ($position['day_change_in_account_currency'] ?? 0.0) * $rate;
                // Same daily price move across every account holding the symbol; keep the first seen.
                if ($todayPct === null && ($position['day_change_percentage'] ?? null) !== null) {
                    $todayPct = (float) $position['day_change_percentage'];
                }
                foreach ($position['trades'] ?? [] as $trade) {
                    if ($earliest === null || $trade->timestamp < $earliest) {
                        $earliest = $trade->timestamp;
                    }
                }
            }

            $returns[$symbol] = [
                'raw_pct'        => $cost > 0.0 ? ($mvalue - $cost) / $cost * 100.0 : null,
                'gain_eur'       => round($mvalue - $cost, 2),
                'today_gain_eur' => round($todayEur, 2),
                'today_gain_pct' => $todayPct,
                'days'           => $earliest !== null ? (int) $earliest->diffInDays(now()) : 0,
            ];
        }

        return $returns;
    }
}
