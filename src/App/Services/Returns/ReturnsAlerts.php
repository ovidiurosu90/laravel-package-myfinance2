<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Models\Trade;

/**
 * Returns Alerts Service
 *
 * Checks year-start (Jan 1) and year-end (Dec 31) positions for symbols that need price overrides.
 * This is used to remind users to define necessary overrides for accurate portfolio valuation.
 *
 * Positions are checked against the api_price_issue_symbols config list. For each matching position,
 * the API price is compared with historical trade prices. If the ratio suggests an unapplied stock split
 * (2x, 4x, 10x, 20x difference), an alert is generated.
 */
class ReturnsAlerts
{
    private array $_apiPriceIssueSymbols;
    private array $_priceOverrides;
    private array $_suppressionKeywords;
    private array $_excludedTradeIds;
    private array $_exchangeRateOverrides;
    private array $_requiredExchangeRatePairs;
    private array $_positionDateOverrides;
    private array $_positionOverrideKeywords;
    private array $_withdrawalsOverrides;
    private array $_depositsOverrides;

    // Common stock split ratios to detect (3:1, 5:1, 10:1, 20:1)
    private const SPLIT_RATIOS = [3, 5, 10, 20];

    // Tolerance for detecting split ratios (e.g., 0.25 = 25% tolerance)
    // Using 25% to account for price movements between the last trade date and valuation date
    private const RATIO_TOLERANCE = 0.25;

    public function __construct()
    {
        $this->_apiPriceIssueSymbols = config('trades.api_price_issue_symbols', []);
        $this->_priceOverrides = config('trades.price_overrides', []);
        $this->_suppressionKeywords = config('trades.api_price_issue_suppression_keywords', []);
        $this->_excludedTradeIds = config('trades.exclude_trades_from_returns', []);
        $this->_exchangeRateOverrides = config('trades.exchange_rate_overrides', []);
        $this->_requiredExchangeRatePairs = config('trades.required_exchange_rate_overrides', []);
        $this->_positionDateOverrides = config('trades.position_date_overrides', []);
        $this->_positionOverrideKeywords = config('trades.position_date_override_keywords', []);
        $this->_withdrawalsOverrides = config('trades.withdrawals_overrides', []);
        $this->_depositsOverrides = config('trades.deposits_overrides', []);
    }

    /**
     * Check positions for missing price overrides
     *
     * @param array $returnsData The data from Returns::handle()
     * @param int $year The year to check
     * @return array Array of alerts, each containing 'type', 'message', 'positions' or 'trades'
     */
    public function check(array $returnsData, int $year): array
    {
        $alerts = [];

        // Check for split adjustment pairs that need exclusion
        $splitAdjustmentAlerts = $this->_checkSplitAdjustmentPairs($year);
        if (!empty($splitAdjustmentAlerts)) {
            $alerts[] = $splitAdjustmentAlerts;
        }

        // Check for missing exchange rate overrides (only for accounts with positions)
        $exchangeRateAlerts = $this->_checkMissingExchangeRateOverrides($year, $returnsData);
        foreach ($exchangeRateAlerts as $alert) {
            $alerts[] = $alert;
        }

        // Check for positions with vested/moved trades that need position_date_overrides
        $positionDateAlerts = $this->_checkMissingPositionDateOverrides($year, $returnsData);
        foreach ($positionDateAlerts as $alert) {
            $alerts[] = $alert;
        }

        // Skip price override checks if no API issue symbols configured
        if (empty($this->_apiPriceIssueSymbols)) {
            return $alerts;
        }

        // Query historical trades from database for symbols with known API issues
        // Only include trades up to Dec 31 of the selected year to avoid post-split prices
        $cutoffDate = "$year-12-31 23:59:59";
        $tradesBySymbol = $this->_fetchHistoricalTradesBySymbol($this->_apiPriceIssueSymbols, $cutoffDate);

        // Check year-start positions (Jan 1)
        $jan1Alerts = $this->_checkPositionsForDate($returnsData, $year, 'jan1', $tradesBySymbol);
        if (!empty($jan1Alerts)) {
            $alerts[] = $jan1Alerts;
        }

        // Check year-end positions (Dec 31)
        $dec31Alerts = $this->_checkPositionsForDate($returnsData, $year, 'dec31', $tradesBySymbol);
        if (!empty($dec31Alerts)) {
            $alerts[] = $dec31Alerts;
        }

        return $alerts;
    }

    /**
     * Check for missing exchange rate overrides per account
     *
     * Each account needs exchange rate overrides for year start/end (either from global or by_account).
     * This alerts when an account is missing a required pair override.
     * Only checks accounts that have positions for the given date.
     *
     * @param int $year The year to check
     * @param array $returnsData The full returns data to check for positions
     */
    private function _checkMissingExchangeRateOverrides(int $year, array $returnsData): array
    {
        if (empty($this->_requiredExchangeRatePairs)) {
            return [];
        }

        $alerts = [];

        // Get accounts with Jan 1 positions (start value)
        $jan1Accounts = $this->_getAccountsWithPositions($returnsData, 'jan1PositionDetails');
        if (!empty($jan1Accounts)) {
            $jan1Missing = $this->_getMissingExchangeRatesForAccounts($year - 1, $jan1Accounts);
            if (!empty($jan1Missing)) {
                $alerts[] = [
                    'type' => 'missing_exchange_rate_jan1',
                    'message' => "Missing exchange rate overrides for Jan 1, $year "
                        . "(config date: Dec " . ($year - 1) . ")",
                    'pairs' => $jan1Missing,
                ];
            }
        }

        // Get accounts with Dec 31 positions (end value) - skip if Dec 31 is in the future
        $dec31Date = "$year-12-31";
        if ($dec31Date <= date('Y-m-d')) {
            $dec31Accounts = $this->_getAccountsWithPositions($returnsData, 'dec31PositionDetails');
            if (!empty($dec31Accounts)) {
                $dec31Missing = $this->_getMissingExchangeRatesForAccounts($year, $dec31Accounts);
                if (!empty($dec31Missing)) {
                    $alerts[] = [
                        'type' => 'missing_exchange_rate_dec31',
                        'message' => "Missing exchange rate overrides for Dec 31, $year "
                            . "(config date: Dec $year)",
                        'pairs' => $dec31Missing,
                    ];
                }
            }
        }

        return $alerts;
    }

    /**
     * Get accounts that have positions for the specified date
     *
     * @param array $returnsData The full returns data
     * @param string $positionsKey The key for position details (jan1PositionDetails or dec31PositionDetails)
     * @return array Map of accountId => accountName for accounts with positions
     */
    private function _getAccountsWithPositions(array $returnsData, string $positionsKey): array
    {
        $accounts = [];

        foreach ($returnsData as $accountId => $data) {
            if (!is_numeric($accountId) || !is_array($data)) {
                continue;
            }

            // Check if account has positions (check in EUR data or directly)
            $positions = $data['EUR'][$positionsKey] ?? $data[$positionsKey] ?? [];
            if (!empty($positions)) {
                $accounts[$accountId] = $data['account']->name ?? "Account #$accountId";
            }
        }

        return $accounts;
    }

    /**
     * Get exchange rate pairs missing for each account for a given year-end
     *
     * Hierarchical structure: global pairs are required for all accounts,
     * by_account pairs are required only for specific accounts.
     * An account is covered if the exchange_rate_overrides config has the pair
     * (either in global or by_account for that specific account).
     *
     * @param int $year The year-end to check (Dec of this year)
     * @param array $accounts Map of accountId => accountName
     */
    private function _getMissingExchangeRatesForAccounts(int $year, array $accounts): array
    {
        $missing = [];
        $datesToCheck = ["$year-12-31", "$year-12-30", "$year-12-29"];

        // Get global and by_account required pairs from config
        $globalRequiredPairs = $this->_requiredExchangeRatePairs['global'] ?? [];
        $byAccountRequiredPairs = $this->_requiredExchangeRatePairs['by_account'] ?? [];

        // Build a map of accountId => required pairs for that account
        $requiredPairsByAccount = [];
        foreach ($accounts as $accountId => $accountName) {
            // Start with global required pairs (apply to all accounts)
            $requiredPairsByAccount[$accountId] = $globalRequiredPairs;

            // Add account-specific required pairs
            if (isset($byAccountRequiredPairs[$accountId])) {
                $requiredPairsByAccount[$accountId] = array_unique(array_merge(
                    $requiredPairsByAccount[$accountId],
                    $byAccountRequiredPairs[$accountId]
                ));
            }
        }

        // Collect all unique pairs to check
        $allPairs = [];
        foreach ($requiredPairsByAccount as $pairs) {
            $allPairs = array_merge($allPairs, $pairs);
        }
        $allPairs = array_unique($allPairs);

        // Check each pair
        foreach ($allPairs as $pair) {
            // First check if global override exists for this pair
            $hasGlobalOverride = false;
            if (isset($this->_exchangeRateOverrides['global'][$pair])) {
                $dates = $this->_exchangeRateOverrides['global'][$pair];
                foreach ($datesToCheck as $date) {
                    if (isset($dates[$date])) {
                        $hasGlobalOverride = true;
                        break;
                    }
                }
            }

            // Check each account that requires this pair
            $accountsMissingOverride = [];
            foreach ($accounts as $accountId => $accountName) {
                // Skip if this account doesn't require this pair
                if (!in_array($pair, $requiredPairsByAccount[$accountId])) {
                    continue;
                }

                // If global override exists, this account is covered
                if ($hasGlobalOverride) {
                    continue;
                }

                // Check for by_account override
                $hasAccountOverride = false;
                if (isset($this->_exchangeRateOverrides['by_account'][$accountId][$pair])) {
                    $dates = $this->_exchangeRateOverrides['by_account'][$accountId][$pair];
                    foreach ($datesToCheck as $date) {
                        if (isset($dates[$date])) {
                            $hasAccountOverride = true;
                            break;
                        }
                    }
                }

                if (!$hasAccountOverride) {
                    $accountsMissingOverride[] = [
                        'id' => $accountId,
                        'name' => $accountName,
                    ];
                }
            }

            if (!empty($accountsMissingOverride)) {
                $missing[] = [
                    'pair' => $pair,
                    'dates_checked' => implode(', ', $datesToCheck),
                    'accounts_missing' => $accountsMissingOverride,
                ];
            }
        }

        return $missing;
    }

    /**
     * Check for positions with vested/moved trades that need position_date_overrides
     *
     * Detects positions where the underlying trades have keywords like "vested" or "moved"
     * in the description, indicating the position may need a position_date_override for
     * correct account attribution on specific dates.
     *
     * @param int $year The year to check
     * @param array $returnsData The full returns data
     * @return array Array of alerts for missing position date overrides
     */
    private function _checkMissingPositionDateOverrides(int $year, array $returnsData): array
    {
        $alerts = [];

        // Check for position_date_overrides without corresponding deposits/withdrawals overrides
        // This check is independent of keyword detection - always run it
        $missingCashOverrides = $this->_checkMissingCashOverridesForPositionOverrides($year, $returnsData);
        if (!empty($missingCashOverrides)) {
            $alerts[] = $missingCashOverrides;
        }

        // Keyword-based alerts require keywords to be configured
        if (empty($this->_positionOverrideKeywords)) {
            return $alerts;
        }

        // Fetch trades with position override keywords up to end of year
        $cutoffDate = "$year-12-31 23:59:59";
        $symbolsWithKeywords = $this->_fetchSymbolsWithPositionKeywords($cutoffDate);

        if (empty($symbolsWithKeywords)) {
            return $alerts;
        }

        // Check Jan 1 positions (start value)
        $jan1Alert = $this->_checkPositionsForPositionDateOverride(
            $returnsData,
            $year,
            'jan1',
            $symbolsWithKeywords
        );
        if (!empty($jan1Alert)) {
            $alerts[] = $jan1Alert;
        }

        // Check Dec 31 positions (end value)
        $dec31Alert = $this->_checkPositionsForPositionDateOverride(
            $returnsData,
            $year,
            'dec31',
            $symbolsWithKeywords
        );
        if (!empty($dec31Alert)) {
            $alerts[] = $dec31Alert;
        }

        return $alerts;
    }

    /**
     * Check for position_date_overrides that don't have corresponding deposits/withdrawals overrides
     *
     * When a position_date_override exists for an account, there should also be a
     * deposits_override or withdrawals_override to neutralize the value transfer.
     * The cash override year matches the position_date_override date's year.
     *
     * For year view:
     * - Dec 31 of previous year affects Jan 1 start value
     * - Jan 1 of current year affects Jan 1 start value
     * - Dec 31 of current year affects Dec 31 end value
     *
     * @param int $year The year being viewed
     * @param array $returnsData The full returns data
     * @return array|null Alert data or null if no issues
     */
    private function _checkMissingCashOverridesForPositionOverrides(int $year, array $returnsData): ?array
    {
        $missingOverrides = [];

        // Dates that affect the current year's returns view
        $datesToCheck = [
            ($year - 1) . "-12-31",  // Affects Jan 1 start value
            "$year-01-01",            // Affects Jan 1 start value
            "$year-12-31",            // Affects Dec 31 end value
        ];

        foreach ($datesToCheck as $overrideDate) {
            if (!isset($this->_positionDateOverrides[$overrideDate])) {
                continue;
            }

            // Extract year from the override date for cash override lookup
            $overrideYear = (int)substr($overrideDate, 0, 4);

            foreach ($this->_positionDateOverrides[$overrideDate] as $accountId => $overrides) {
                if (!is_numeric($accountId)) {
                    continue;
                }

                // Only require cash overrides for accounts with manual_positions
                // Accounts with only exclude_symbols don't add any value that needs neutralization
                if (!isset($overrides['manual_positions']) || empty($overrides['manual_positions'])) {
                    continue;
                }

                // Get account name from returns data
                $accountName = $returnsData[$accountId]['account']->name ?? "Account #$accountId";

                // Check if there's a deposits or withdrawals override for the override date's year
                if (!$this->_hasDepositsOrWithdrawalsOverride((int)$accountId, $overrideYear)) {
                    $missingOverrides[] = [
                        'date' => $overrideDate,
                        'account_id' => $accountId,
                        'account_name' => $accountName,
                        'year_needed' => $overrideYear,
                    ];
                }
            }
        }

        if (empty($missingOverrides)) {
            return null;
        }

        return [
            'type' => 'missing_cash_override_for_position',
            'message' => "Position date overrides exist but missing deposits_overrides/withdrawals_overrides",
            'items' => $missingOverrides,
        ];
    }

    /**
     * Check if deposits or withdrawals override exists for an account and year
     *
     * @param int $accountId The account ID
     * @param int $year The year to check
     * @return bool True if either deposits or withdrawals override exists
     */
    private function _hasDepositsOrWithdrawalsOverride(int $accountId, int $year): bool
    {
        // Check deposits overrides
        $depositsOverrides = $this->_depositsOverrides['by_account'] ?? [];
        if (isset($depositsOverrides[$accountId][$year])) {
            return true;
        }

        // Check withdrawals overrides
        $withdrawalsOverrides = $this->_withdrawalsOverrides['by_account'] ?? [];
        if (isset($withdrawalsOverrides[$accountId][$year])) {
            return true;
        }

        return false;
    }

    /**
     * Fetch symbols that have trades with position override keywords
     *
     * Only returns symbols where at least one keyword trade is NOT in exclude_trades_from_returns.
     * If all keyword trades for a symbol are excluded, those old vested/moved positions have been
     * fully accounted for and any current position is from new independent trades.
     *
     * @param string $cutoffDate Only include trades up to this date
     * @return array Map of symbol => array of account_ids that have trades with keywords
     */
    private function _fetchSymbolsWithPositionKeywords(string $cutoffDate): array
    {
        $trades = Trade::whereIn('action', ['BUY', 'SELL'])
            ->where('timestamp', '<=', $cutoffDate)
            ->whereNotNull('description')
            ->get(['id', 'symbol', 'account_id', 'description']);

        // Track keyword trades per symbol, separating excluded vs non-excluded
        $keywordTradesBySymbol = [];

        foreach ($trades as $trade) {
            $description = strtolower($trade->description ?? '');
            foreach ($this->_positionOverrideKeywords as $keyword) {
                if (str_contains($description, strtolower($keyword))) {
                    $symbol = $trade->symbol;
                    $isExcluded = in_array($trade->id, $this->_excludedTradeIds);

                    if (!isset($keywordTradesBySymbol[$symbol])) {
                        $keywordTradesBySymbol[$symbol] = [
                            'accounts' => [],
                            'hasNonExcluded' => false,
                        ];
                    }

                    // Track accounts
                    if (!in_array($trade->account_id, $keywordTradesBySymbol[$symbol]['accounts'])) {
                        $keywordTradesBySymbol[$symbol]['accounts'][] = $trade->account_id;
                    }

                    // Track if any keyword trade is NOT excluded
                    if (!$isExcluded) {
                        $keywordTradesBySymbol[$symbol]['hasNonExcluded'] = true;
                    }

                    break;
                }
            }
        }

        // Only return symbols where at least one keyword trade is not excluded
        $symbolsWithKeywords = [];
        foreach ($keywordTradesBySymbol as $symbol => $data) {
            if ($data['hasNonExcluded']) {
                $symbolsWithKeywords[$symbol] = $data['accounts'];
            }
        }

        return $symbolsWithKeywords;
    }

    /**
     * Check positions for missing position_date_overrides
     *
     * @param array $returnsData The full returns data
     * @param int $year The year being checked
     * @param string $dateType 'jan1' or 'dec31'
     * @param array $symbolsWithKeywords Map of symbol => account_ids with keyword trades
     * @return array|null Alert data or null if no alerts
     */
    private function _checkPositionsForPositionDateOverride(
        array $returnsData,
        int $year,
        string $dateType,
        array $symbolsWithKeywords
    ): ?array {
        $positionsKey = $dateType . 'PositionDetails';
        $missingOverrides = [];

        // Determine the date string for position_date_overrides lookup
        $overrideDate = $dateType === 'jan1' ? "$year-01-01" : "$year-12-31";

        foreach ($returnsData as $accountId => $data) {
            // Skip metadata entries
            if (!is_numeric($accountId) || !is_array($data)) {
                continue;
            }

            $accountName = $data['account']->name ?? 'Unknown';

            // Get positions from EUR data (contains the raw position details)
            $positions = $data['EUR'][$positionsKey] ?? $data[$positionsKey] ?? [];

            foreach ($positions as $position) {
                $symbol = $position['symbol'] ?? '';

                // Check if this symbol has trades with keywords
                if (!isset($symbolsWithKeywords[$symbol])) {
                    continue;
                }

                // Check if position_date_override already exists for this date/account/symbol
                if ($this->_hasPositionDateOverride($overrideDate, (int)$accountId, $symbol)) {
                    continue;
                }

                $missingOverrides[] = [
                    'symbol' => $symbol,
                    'account_id' => $accountId,
                    'account_name' => $accountName,
                    'quantity' => $position['quantity'] ?? 0,
                    'quantityFormatted' => $position['quantityFormatted'] ?? '',
                ];
            }
        }

        if (empty($missingOverrides)) {
            return null;
        }

        // Sort by symbol then account for consistent display
        usort($missingOverrides, function ($a, $b) {
            $symbolComp = strcasecmp($a['symbol'], $b['symbol']);
            return $symbolComp !== 0 ? $symbolComp : strcasecmp($a['account_name'], $b['account_name']);
        });

        $dateLabel = $dateType === 'jan1' ? 'Jan 1' : 'Dec 31';

        return [
            'type' => 'missing_position_date_override_' . $dateType,
            'message' => "Positions with vested/moved trades may need position_date_overrides "
                . "and deposits_overrides/withdrawals_overrides for $dateLabel, $year "
                . "(config date: $overrideDate)",
            'positions' => $missingOverrides,
        ];
    }

    /**
     * Check if position_date_override exists for a specific date/account/symbol
     *
     * Checks exclude_symbols and manual_positions entries.
     *
     * @param string $date The date to check (YYYY-MM-DD)
     * @param int $accountId The account ID
     * @param string $symbol The symbol to check
     * @return bool True if an override exists
     */
    private function _hasPositionDateOverride(string $date, int $accountId, string $symbol): bool
    {
        if (!isset($this->_positionDateOverrides[$date][$accountId])) {
            return false;
        }

        $accountOverrides = $this->_positionDateOverrides[$date][$accountId];

        // Check if symbol is in exclude_symbols
        if (isset($accountOverrides['exclude_symbols'])
            && in_array($symbol, $accountOverrides['exclude_symbols'])
        ) {
            return true;
        }

        // Check if symbol is in manual_positions
        if (isset($accountOverrides['manual_positions'][$symbol])) {
            return true;
        }

        return false;
    }

    /**
     * Check for split adjustment pairs that need to be excluded from returns
     *
     * Detects pairs of trades (BUY + SELL) on the same day for the same symbol
     * where both have suppression keywords (e.g., "split") in the description
     * and quantities differ by a split ratio. These are accounting adjustments,
     * not real trades, and should be excluded from returns calculations.
     */
    private function _checkSplitAdjustmentPairs(int $year): ?array
    {
        if (empty($this->_suppressionKeywords)) {
            return null;
        }

        $startDate = "$year-01-01 00:00:00";
        $endDate = "$year-12-31 23:59:59";

        // Query trades for the year that have suppression keywords in description
        $trades = Trade::whereBetween('timestamp', [$startDate, $endDate])
            ->whereIn('action', ['BUY', 'SELL'])
            ->orderBy('timestamp', 'ASC')
            ->get();

        // Filter trades that have suppression keywords
        $splitTrades = [];
        foreach ($trades as $trade) {
            $description = strtolower($trade->description ?? '');
            foreach ($this->_suppressionKeywords as $keyword) {
                if (str_contains($description, strtolower($keyword))) {
                    $splitTrades[] = $trade;
                    break;
                }
            }
        }

        if (empty($splitTrades)) {
            return null;
        }

        // Group by symbol and date to find pairs
        $grouped = [];
        foreach ($splitTrades as $trade) {
            $date = $trade->timestamp->format('Y-m-d');
            $key = $trade->symbol . '_' . $date;
            $grouped[$key][] = $trade;
        }

        // Find pairs that need exclusion
        $pairsNeedingExclusion = [];
        foreach ($grouped as $key => $tradesInGroup) {
            // Need at least 2 trades
            if (count($tradesInGroup) < 2) {
                continue;
            }

            // Check for BUY + SELL pair
            $buys = array_filter($tradesInGroup, fn($t) => $t->action === 'BUY');
            $sells = array_filter($tradesInGroup, fn($t) => $t->action === 'SELL');

            if (empty($buys) || empty($sells)) {
                continue;
            }

            // Check if quantities differ by a split ratio
            foreach ($buys as $buy) {
                foreach ($sells as $sell) {
                    if ($this->_isSplitRatio((float)$buy->quantity, (float)$sell->quantity)) {
                        // Check if already excluded
                        $buyExcluded = in_array($buy->id, $this->_excludedTradeIds);
                        $sellExcluded = in_array($sell->id, $this->_excludedTradeIds);

                        if (!$buyExcluded || !$sellExcluded) {
                            $pairsNeedingExclusion[] = [
                                'symbol' => $buy->symbol,
                                'date' => $buy->timestamp->format('Y-m-d'),
                                'buy_id' => $buy->id,
                                'buy_quantity' => $buy->quantity,
                                'buy_excluded' => $buyExcluded,
                                'sell_id' => $sell->id,
                                'sell_quantity' => $sell->quantity,
                                'sell_excluded' => $sellExcluded,
                            ];
                        }
                    }
                }
            }
        }

        if (empty($pairsNeedingExclusion)) {
            return null;
        }

        // Sort by date then symbol
        usort($pairsNeedingExclusion, function ($a, $b) {
            $dateComp = strcmp($a['date'], $b['date']);
            return $dateComp !== 0 ? $dateComp : strcasecmp($a['symbol'], $b['symbol']);
        });

        return [
            'type' => 'split_adjustment_pairs',
            'message' => "Split adjustment trades to exclude (add to exclude_trades_from_returns)",
            'trades' => $pairsNeedingExclusion,
        ];
    }

    /**
     * Check if two quantities differ by a split ratio
     */
    private function _isSplitRatio(float $qty1, float $qty2): bool
    {
        if ($qty1 <= 0 || $qty2 <= 0) {
            return false;
        }

        $ratio = $qty1 > $qty2 ? $qty1 / $qty2 : $qty2 / $qty1;

        foreach (self::SPLIT_RATIOS as $splitRatio) {
            if ($this->_isCloseToRatio($ratio, $splitRatio)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch historical trades from database for given symbols
     *
     * @param array $symbols List of symbols to fetch trades for
     * @param string|null $cutoffDate Only include trades up to this date (to exclude post-split prices)
     */
    private function _fetchHistoricalTradesBySymbol(array $symbols, ?string $cutoffDate = null): array
    {
        if (empty($symbols)) {
            return [];
        }

        $query = Trade::whereIn('symbol', $symbols)
            ->whereIn('action', ['BUY', 'SELL']);

        if ($cutoffDate !== null) {
            $query->where('timestamp', '<=', $cutoffDate);
        }

        $trades = $query->orderBy('timestamp', 'DESC')->get();

        $tradesBySymbol = [];
        $symbolHasSuppressedTrade = [];

        foreach ($trades as $trade) {
            // Check if any trade has suppression keywords in the description (case-insensitive)
            $description = strtolower($trade->description ?? '');
            foreach ($this->_suppressionKeywords as $keyword) {
                if (str_contains($description, strtolower($keyword))) {
                    $symbolHasSuppressedTrade[$trade->symbol] = true;
                    break;
                }
            }

            $tradesBySymbol[$trade->symbol][] = [
                'unit_price' => (float)$trade->unit_price,
                'date' => $trade->timestamp->format('Y-m-d'),
                'account_id' => $trade->account_id,
            ];
        }

        // Remove symbols that have a suppressed trade (broker already adjusted)
        foreach ($symbolHasSuppressedTrade as $symbol => $hasSuppressed) {
            unset($tradesBySymbol[$symbol]);
        }

        return $tradesBySymbol;
    }

    /**
     * Check positions for a specific date (jan1 or dec31)
     */
    private function _checkPositionsForDate(
        array $returnsData,
        int $year,
        string $dateType,
        array $tradesBySymbol
    ): ?array {
        $positionsKey = $dateType . 'PositionDetails';
        $missingOverrides = [];

        foreach ($returnsData as $accountId => $data) {
            // Skip metadata entries
            if (!is_numeric($accountId) || !is_array($data)) {
                continue;
            }

            $accountName = $data['account']->name ?? 'Unknown';

            // Get positions from EUR data (contains the raw position details)
            $positions = $data['EUR'][$positionsKey] ?? $data[$positionsKey] ?? [];

            foreach ($positions as $position) {
                $symbol = $position['symbol'] ?? '';

                // Check if symbol has known API price issues
                if (!in_array($symbol, $this->_apiPriceIssueSymbols)) {
                    continue;
                }

                // Check if price override already exists for this symbol/date
                if ($this->_hasPriceOverrideForDate($symbol, (int)$accountId, $year, $dateType)) {
                    continue;
                }

                // Get the API price from position (price field when not overridden)
                $apiPrice = $position['price'] ?? 0;
                if (empty($apiPrice)) {
                    continue;
                }

                // Compare API price with trade prices to detect split issues
                $trades = $tradesBySymbol[$symbol] ?? [];
                if (!$this->_detectsSplitIssue($apiPrice, $trades)) {
                    continue;
                }

                // Calculate detected ratio for display using most recent trade price
                $recentTradePrice = $this->_getMostRecentTradePrice($trades);
                $detectedRatio = $recentTradePrice > 0 ? round($recentTradePrice / $apiPrice, 1) : null;

                $missingOverrides[] = [
                    'symbol' => $symbol,
                    'account_id' => $accountId,
                    'account_name' => $accountName,
                    'quantity' => $position['quantity'] ?? 0,
                    'quantityFormatted' => $position['quantityFormatted'] ?? '',
                    'apiPrice' => round($apiPrice, 2),
                    'recentTradePrice' => $recentTradePrice ? round($recentTradePrice, 2) : null,
                    'detectedRatio' => $detectedRatio,
                ];
            }
        }

        if (empty($missingOverrides)) {
            return null;
        }

        // Sort by symbol for consistent display
        usort($missingOverrides, fn($a, $b) => strcasecmp($a['symbol'], $b['symbol']));

        $dateLabel = $dateType === 'jan1' ? 'Jan 1' : 'Dec 31';
        $dateForConfig = $dateType === 'jan1'
            ? ($year - 1) . '-12-31'  // Jan 1 uses previous year's last trading day
            : $year . '-12-31';

        return [
            'type' => 'missing_price_override_' . $dateType,
            'message' => "Missing price overrides for $dateLabel, $year (config date: $dateForConfig)",
            'positions' => $missingOverrides,
        ];
    }

    /**
     * Detect if API price vs trade prices suggests a stock split issue
     *
     * Returns true if the ratio between the most recent trade price and API price
     * is close to a common split ratio (3x, 5x, 10x, 20x, etc.)
     * Using the most recent trade price (closest to valuation date) gives more accurate
     * detection than averaging all historical trades which may span multiple splits.
     */
    private function _detectsSplitIssue(float $apiPrice, array $trades): bool
    {
        if (empty($trades) || $apiPrice <= 0) {
            // No trades to compare - can't detect split issue, skip alert
            return false;
        }

        $recentTradePrice = $this->_getMostRecentTradePrice($trades);
        if ($recentTradePrice <= 0) {
            return false;
        }

        // Calculate the ratio (could be > 1 or < 1 depending on split direction)
        $ratio = $recentTradePrice / $apiPrice;

        // Check if ratio is close to any common split factor
        foreach (self::SPLIT_RATIOS as $splitRatio) {
            // Check for ratio close to splitRatio (API price too low - split not applied)
            if ($this->_isCloseToRatio($ratio, $splitRatio)) {
                return true;
            }
            // Check for inverse ratio (API price too high - reverse split not applied)
            if ($this->_isCloseToRatio($ratio, 1 / $splitRatio)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value is close to a target ratio within tolerance
     */
    private function _isCloseToRatio(float $value, float $target): bool
    {
        if ($target <= 0) {
            return false;
        }
        $tolerance = $target * self::RATIO_TOLERANCE;
        return abs($value - $target) <= $tolerance;
    }

    /**
     * Get the most recent trade price for a symbol
     *
     * Uses the most recent trade (closest to valuation date) rather than averaging
     * all historical trades. This provides more accurate split detection when a symbol
     * has had multiple splits over time, as older trade prices may be from different
     * split-adjusted periods.
     *
     * @param array $trades Array of trades, expected to be sorted by timestamp DESC
     */
    private function _getMostRecentTradePrice(array $trades): float
    {
        if (empty($trades)) {
            return 0;
        }

        // Trades are already sorted by timestamp DESC from _fetchHistoricalTradesBySymbol()
        // So the first trade with a valid price is the most recent
        foreach ($trades as $trade) {
            if (!empty($trade['unit_price']) && $trade['unit_price'] > 0) {
                return (float)$trade['unit_price'];
            }
        }

        return 0;
    }

    /**
     * Check if price override exists for symbol on the specified date
     */
    private function _hasPriceOverrideForDate(
        string $symbol,
        int $accountId,
        int $year,
        string $dateType
    ): bool {
        // Determine the date range to check based on date type
        // Jan 1 valuation typically uses Dec 30-31 of previous year (last trading days)
        // Dec 31 valuation typically uses Dec 30-31 of current year
        if ($dateType === 'jan1') {
            $prevYear = $year - 1;
            $datesToCheck = ["$prevYear-12-31", "$prevYear-12-30", "$prevYear-12-29"];
        } else {
            $datesToCheck = ["$year-12-31", "$year-12-30", "$year-12-29"];
        }

        // Check account-specific overrides first
        if (isset($this->_priceOverrides['by_account'][$accountId][$symbol])) {
            $dates = $this->_priceOverrides['by_account'][$accountId][$symbol];
            foreach ($datesToCheck as $date) {
                if (isset($dates[$date])) {
                    return true;
                }
            }
        }

        // Check global overrides
        if (isset($this->_priceOverrides['global'][$symbol])) {
            $dates = $this->_priceOverrides['global'][$symbol];
            foreach ($datesToCheck as $date) {
                if (isset($dates[$date])) {
                    return true;
                }
            }
        }

        return false;
    }
}

