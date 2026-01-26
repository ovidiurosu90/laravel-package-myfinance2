<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Services\CashBalancesUtils;
use ovidiuro\myfinance2\App\Services\FinanceAPI;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\Positions;

/**
 * Returns Valuation Service
 *
 * Handles portfolio valuation at specific dates (start/end of year).
 * Calculates total portfolio value by fetching quotes, applying overrides,
 * computing exchange rates, and summing positions + cash balance.
 */
class ReturnsValuation
{
    private ReturnsQuoteProvider $quoteProvider;
    private ReturnsConfigHelper $configHelper;

    public function __construct(
        ReturnsQuoteProvider $quoteProvider = null,
        ReturnsConfigHelper $configHelper = null
    )
    {
        $this->quoteProvider = $quoteProvider ?? new ReturnsQuoteProvider();
        $this->configHelper = $configHelper ?? new ReturnsConfigHelper();
    }

    /**
     * Get portfolio value at a specific date
     * Returns total value, positions value, cash value, and position details
     *
     * @param Account|int $accountOrId The account object (preferred) or account ID
     * @param \DateTimeInterface $date The date to get portfolio value for
     * @param array|null $preloadedPositions Pre-fetched positions for this account (optional)
     */
    public function getPortfolioValue(
        Account|int $accountOrId,
        \DateTimeInterface $date,
        ?array $preloadedPositions = null
    ): array {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        // Handle both Account object and accountId for backwards compatibility
        if ($accountOrId instanceof Account) {
            $account = $accountOrId;
            $accountId = $account->id;
        } else {
            $accountId = $accountOrId;
            $account = Account::with('currency')->find($accountId);
        }

        // Use pre-loaded positions if provided, otherwise fetch them
        if ($preloadedPositions !== null) {
            $accountPositions = $preloadedPositions;
        } else {
            // Get positions and apply overrides (fallback for backwards compatibility)
            $positionsService = new Positions();
            // For Returns calculation, we need ALL trades (including CLOSED) to calculate realized gains
            $positionsService->setIncludeClosedTrades(true);
            $trades = $positionsService->getTrades($date);
            $positions = Positions::tradesToPositions($trades);
            $accountPositions = $positions[$accountId] ?? [];
        }

        $accountPositions = $this->applyPositionDateOverrides(
            $accountId,
            $date,
            $accountPositions,
            $account
        );

        // Pre-fetch quotes and calculate position values
        $allQuotes = $this->prefetchQuotesForDate($accountId, $accountPositions, $date);
        $exchangeRateCache = [];
        $positionsValue = 0;
        $positionDetails = [];

        $result = $this->calculatePositionValues(
            $accountId,
            $accountPositions,
            $allQuotes,
            $exchangeRateCache,
            $account,
            $date,
            $positionsValue,
            $positionDetails
        );

        // Get cash balance (pass account to avoid redundant query)
        $cashBalancesUtils = new CashBalancesUtils($accountId, $date, $account);
        $cashValue = $cashBalancesUtils->getAmount() ?? 0;

        return [
            'total' => $result['positionsValue'] + $cashValue,
            'positions' => $result['positionsValue'],
            'cash' => $cashValue,
            'positionDetails' => $result['positionDetails'],
        ];
    }

    /**
     * Apply position date overrides for tax reporting adjustments
     */
    private function applyPositionDateOverrides(
        int $accountId,
        \DateTimeInterface $date,
        array $accountPositions,
        Account $account
    ): array {
        $dateStr = $date->format('Y-m-d');
        $positionOverrides = config('trades.position_date_overrides', []);
        if (empty($positionOverrides[$dateStr][$accountId])) {
            return $accountPositions;
        }

        $override = $positionOverrides[$dateStr][$accountId];

        // Exclude specified symbols
        if (!empty($override['exclude_symbols'])) {
            foreach ($override['exclude_symbols'] as $excludeSymbol) {
                unset($accountPositions[$excludeSymbol]);
            }
        }

        // Add manual positions
        if (!empty($override['manual_positions'])) {
            foreach ($override['manual_positions'] as $manualSymbol => $manualData) {
                if (isset($accountPositions[$manualSymbol])) {
                    $accountPositions[$manualSymbol]['quantity'] = $manualData['quantity'];
                } else {
                    // Determine trade currency: use override config if provided, otherwise default to account currency
                    $tradeCurrencyIsoCode = $manualData['currency'] ?? $account->currency->iso_code;

                    // Load currency
                    $tradeCurrency = Currency::where('iso_code', $tradeCurrencyIsoCode)->first();
                    $tradeCurrencyId = $tradeCurrency ? $tradeCurrency->id : $account->currency->id;

                    $accountPositions[$manualSymbol] = [
                        'symbol' => $manualSymbol,
                        'quantity' => $manualData['quantity'],
                        'accountModel' => $account,
                        'trade_currency_id' => $tradeCurrencyId,
                        'tradeCurrencyModel' => $tradeCurrency ?? $account->currency,
                    ];
                }
            }
        }

        return $accountPositions;
    }

    /**
     * Pre-fetch all quotes for positions on a specific date
     */
    private function prefetchQuotesForDate(
        int $accountId,
        array $accountPositions,
        \DateTimeInterface $date
    ): array {
        $allQuotes = [];
        if (empty($accountPositions)) {
            return $allQuotes;
        }

        // Collect symbols needing quotes
        $symbolsToFetch = [];
        foreach ($accountPositions as $symbol => $position) {
            if (!empty($position['quantity']) && $position['quantity'] != 0) {
                $symbolsToFetch[$symbol] = true;
            }
        }

        // Fetch quotes for all symbols
        foreach (array_keys($symbolsToFetch) as $symbol) {
            // Skip API fetch for delisted symbols - use override instead
            if ($this->isDelistedSymbol($symbol)) {
                // Create a minimal stat entry from price override
                $priceOverride = $this->getOverride($symbol, $accountId, $date, 'price');
                if ($priceOverride !== null) {
                    $allQuotes[$symbol] = [
                        'unit_price' => $priceOverride,
                        'date' => $date->format('Y-m-d'),
                        'price_overridden' => true,
                        'api_price' => null,
                    ];
                }
                continue;
            }

            $stat = $this->quoteProvider->getOrFetchQuoteStat($accountId, $symbol, $date);
            if (!empty($stat)) {
                $allQuotes[$symbol] = $stat;
            }
        }

        return $allQuotes;
    }

    /**
     * Calculate market values for all positions
     */
    private function calculatePositionValues(
        int $accountId,
        array $accountPositions,
        array $allQuotes,
        array &$exchangeRateCache,
        Account $account,
        \DateTimeInterface $date,
        &$positionsValue,
        &$positionDetails
    ): array {
        $positionsValue = 0;
        $positionDetails = [];

        if (empty($accountPositions)) {
            return ['positionsValue' => 0, 'positionDetails' => []];
        }

        foreach ($accountPositions as $symbol => $position) {
            // Skip positions with 0 quantity
            if (empty($position['quantity']) || $position['quantity'] == 0) {
                continue;
            }

            $stat = $allQuotes[$symbol] ?? null;
            if (empty($stat) || empty($stat['unit_price'])) {
                Log::warning(
                    "Skipping position $symbol - could not get quote for "
                    . $date->format('Y-m-d')
                );
                continue;
            }

            $detail = $this->processPositionDetail(
                $accountId,
                $symbol,
                $position,
                $stat,
                $account,
                $date,
                $exchangeRateCache,
                $positionsValue
            );

            if ($detail !== null) {
                $positionDetails[] = $detail;
            }
        }

        return ['positionsValue' => $positionsValue, 'positionDetails' => $positionDetails];
    }

    /**
     * Process a single position detail and update positionsValue
     */
    private function processPositionDetail(
        int $accountId,
        string $symbol,
        array &$position,
        array $stat,
        Account $account,
        \DateTimeInterface $date,
        array &$exchangeRateCache,
        &$positionsValue
    ): ?array {
        // Set up quote data
        $quoteDate = $stat['date'] ?? $date;
        if (is_string($quoteDate)) {
            $quoteDate = new \DateTime($quoteDate);
        }
        $quote = [
            'price' => $stat['unit_price'],
            'quote_timestamp' => $quoteDate,
        ];

        Positions::addPrice($position, $quote, $date);

        // Get and apply exchange rate
        $accountCurrency = $position['accountModel']->currency->iso_code;
        $tradeCurrency = $position['tradeCurrencyModel']->iso_code;
        $exchangeRateOverridden = false;
        $apiExchangeRate = null;
        $exchangeRate = 1;

        if ($accountCurrency !== $tradeCurrency) {
            $result = $this->getPositionExchangeRate(
                $accountId,
                $accountCurrency,
                $tradeCurrency,
                $exchangeRateCache,
                $date
            );
            if ($result === null) {
                Log::warning(
                    "Skipping position $symbol - exchange rate not available on or before "
                    . $date->format('Y-m-d')
                );
                return null;
            }
            list($exchangeRate, $exchangeRateOverridden, $apiExchangeRate) = $result;
            Positions::addExchangeRate($position, $exchangeRate);
        } else {
            Positions::addExchangeRate($position, 1);
        }

        // Calculate market value
        Positions::addMarketValue($position);
        $marketValue = $position['market_value_in_account_currency'] ?? 0;
        $positionsValue += $marketValue;

        // Build position details array
        $localMarketValue = $position['price'] * $position['quantity'];
        $localMarketValueFormatted = MoneyFormat::get_formatted_balance(
            $position['tradeCurrencyModel']->display_code,
            $localMarketValue
        );
        $marketValueFormatted = MoneyFormat::get_formatted_balance(
            $account->currency->display_code,
            $marketValue
        );

        $priceFormatted = MoneyFormat::get_formatted_price_plain($position['price']);
        $configPriceFormatted = MoneyFormat::get_formatted_number_plain($position['price'], 4);
        $apiPriceFormatted = !empty($stat['api_price'])
            ? MoneyFormat::get_formatted_number_plain($stat['api_price'], 4)
            : null;
        $apiExchangeRateFormatted = !empty($apiExchangeRate)
            ? MoneyFormat::get_formatted_number_plain($apiExchangeRate, 4)
            : null;
        $quantityFormatted = MoneyFormat::get_formatted_quantity_plain($position['quantity']);

        return [
            'symbol' => $symbol,
            'quantity' => $position['quantity'],
            'quantityFormatted' => $quantityFormatted,
            'price' => $position['price'],
            'priceFormatted' => $priceFormatted,
            'tradeCurrency' => $position['tradeCurrencyModel']->iso_code,
            'tradeCurrencyDisplayCode' => $position['tradeCurrencyModel']->display_code,
            'exchangeRate' => $position['exchange_rate'],
            'exchangeRateClean' => $this->quoteProvider->formatCleanExchangeRate(
                $position['exchange_rate']
            ),
            'exchangeRateFormatted' => MoneyFormat::get_formatted_rate_plain(
                $position['exchange_rate']
            ),
            'exchangeRateOverridden' => $exchangeRateOverridden,
            'apiExchangeRate' => $apiExchangeRate,
            'apiExchangeRateFormatted' => $apiExchangeRateFormatted,
            'localMarketValue' => $localMarketValue,
            'localMarketValueFormatted' => $localMarketValueFormatted,
            'marketValue' => $marketValue,
            'marketValueFormatted' => $marketValueFormatted,
            'priceOverridden' => $stat['price_overridden'] ?? false,
            'apiPrice' => $stat['api_price'] ?? null,
            'apiPriceFormatted' => $apiPriceFormatted,
            'configPriceFormatted' => $configPriceFormatted,
        ];
    }

    /**
     * Get exchange rate for a position with caching
     */
    private function getPositionExchangeRate(
        int $accountId,
        string $accountCurrency,
        string $tradeCurrency,
        array &$exchangeRateCache,
        \DateTimeInterface $date
    ): ?array {
        // Handle GBX/GBp conversion
        $lookupCurrency = ($tradeCurrency === 'GBX' || $tradeCurrency === 'GBp')
            ? 'GBP'
            : $tradeCurrency;
        $exchangeRateSymbol = $accountCurrency . $lookupCurrency . '=X';

        // Check cache
        if (!isset($exchangeRateCache[$exchangeRateSymbol])) {
            $exchangeRateCache[$exchangeRateSymbol] = $this->quoteProvider->getExchangeRateWithFallback(
                $accountId,
                $exchangeRateSymbol,
                $date
            );
        }
        $exchangeRateStat = $exchangeRateCache[$exchangeRateSymbol];

        if (empty($exchangeRateStat) || empty($exchangeRateStat['unit_price'])) {
            return null;
        }

        $exchangeRate = $exchangeRateStat['unit_price'];
        $exchangeRateOverridden = !empty($exchangeRateStat['exchange_rate_overridden']);
        $apiExchangeRate = $exchangeRateStat['api_rate'] ?? null;

        // Convert GBP to GBX/GBp (multiply by 100)
        if ($tradeCurrency === 'GBX' || $tradeCurrency === 'GBp') {
            $exchangeRate *= 100;
            if (!empty($apiExchangeRate)) {
                $apiExchangeRate *= 100;
            }
        }

        return [$exchangeRate, $exchangeRateOverridden, $apiExchangeRate];
    }

    /**
     * Check if a symbol is known to be delisted
     */
    private function isDelistedSymbol(string $symbol): bool
    {
        $delistedSymbols = config('trades.delisted_symbols', []);
        return in_array($symbol, $delistedSymbols, true);
    }

    /**
     * Get price override for a symbol on a specific date
     * Delegates to ReturnsConfigHelper for centralized override resolution
     */
    private function getOverride(
        string $symbol,
        int $accountId,
        \DateTimeInterface $date,
        string $type
    ): ?float {
        return $this->configHelper->getOverride($symbol, $accountId, $date, $type);
    }
}

