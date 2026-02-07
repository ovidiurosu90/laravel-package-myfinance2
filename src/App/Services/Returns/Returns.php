<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\Positions;

/**
 * Returns Orchestrator Service
 *
 * Coordinates portfolio returns calculation by orchestrating domain-specific services:
 * - ReturnsValuation: Portfolio valuation at start/end dates
 * - ReturnsDeposits: Deposit transaction data
 * - ReturnsWithdrawals: Withdrawal transaction data
 * - ReturnsDividends: Dividend income data
 * - ReturnsTrades: Stock purchases, sales, and excluded trades
 * - ReturnsCurrencyConverter: Multi-currency conversion
 *
 * Main entry point: handle(year)
 */
class Returns
{
    private ReturnsValuation $_valuation;
    private ReturnsDeposits $_deposits;
    private ReturnsWithdrawals $_withdrawals;
    private ReturnsDividends $_dividends;
    private ReturnsTrades $_trades;
    private ReturnsCurrencyConverter $_currencyConverter;

    /**
     * Cached positions data keyed by date string (Y-m-d)
     * Prevents redundant Positions::getTrades() calls for same date
     * Format: ['2022-01-01' => ['trades' => Collection, 'positions' => array]]
     */
    private array $_positionsCache = [];

    /**
     * Cached currency models keyed by iso_code
     * Prevents redundant Currency queries
     */
    private array $_currencyCache = [];

    public function __construct(
        ReturnsValuation $valuation = null,
        ReturnsDeposits $deposits = null,
        ReturnsWithdrawals $withdrawals = null,
        ReturnsDividends $dividends = null,
        ReturnsTrades $trades = null,
        ReturnsCurrencyConverter $currencyConverter = null
    )
    {
        $this->_valuation = $valuation ?? new ReturnsValuation();
        $this->_deposits = $deposits ?? new ReturnsDeposits();
        $this->_withdrawals = $withdrawals ?? new ReturnsWithdrawals();
        $this->_dividends = $dividends ?? new ReturnsDividends();
        $this->_trades = $trades ?? new ReturnsTrades();
        $this->_currencyConverter = $currencyConverter ?? new ReturnsCurrencyConverter();
    }

    /**
     * Main entry point: Calculate returns for all trading accounts for a year
     *
     * @param int $year The year to calculate returns for
     * @return array Returns data for all trading accounts
     */
    public function handle(int $year): array
    {
        // Get all trading accounts
        $accounts = Account::with('currency')
            ->where('is_trade_account', '1')
            ->orderBy('name')
            ->get();

        // Pre-fetch currencies used for conversion (EUR, USD)
        $this->_prefetchCurrencies(['EUR', 'USD']);

        // Pre-fetch positions for the year boundaries (jan1 and dec31)
        // This prevents duplicate trades queries across accounts
        try {
            [$jan1, $dec31] = $this->_createDateRange($year);
            $this->_prefetchPositionsForDates([$jan1, $dec31]);
        } catch (\Exception $e) {
            Log::error('Failed to prefetch positions for year: ' . $year . '. Error: ' . $e->getMessage());
        }

        $returnsData = [];
        $totalReturnEUR = 0;
        $totalReturnUSD = 0;

        foreach ($accounts as $account) {
            $baseReturns = $this->_calculateAccountReturns($account, $year);

            $accountId = $account->id;

            // Store calculated return before applying override (for display comparison)
            $baseReturns['actualReturnCalculated'] = $baseReturns['actualReturn'];

            // Apply returns override if configured
            $override = $this->_getReturnsOverride($accountId, $year);
            if ($override !== null) {
                // Store the fact that an override was applied
                $baseReturns['actualReturnOverride'] = $override;
                $baseReturns['actualReturnOverrideReason'] = $override['reason'] ?? null;
                // Don't change the actualReturn here - let currency converter handle it
                // by passing both calculated and override values
            }

            $converted = $this->_currencyConverter->convertReturnsToCurrencies(
                $accountId,
                $baseReturns,
                ['EUR', 'USD'],
                $year,
                $this->_currencyCache
            );

            // Filter out accounts with no activity for the selected year
            if (!$this->_hasAccountActivity($baseReturns, $converted)) {
                continue;
            }

            // Flatten the structure: account id maps to array with all keys accessible at top level
            $returnsData[$account->id] = array_merge(
                [
                    'account' => $account,
                ],
                $baseReturns,
                $converted
            );

            // Aggregate total returns across all accounts
            if (isset($converted['EUR']['actualReturn'])) {
                $totalReturnEUR += $converted['EUR']['actualReturn'];
            }
            if (isset($converted['USD']['actualReturn'])) {
                $totalReturnUSD += $converted['USD']['actualReturn'];
            }
        }

        // Process virtual accounts (e.g., transfer adjustments)
        $virtualAccounts = $this->_processVirtualAccounts($year);
        foreach ($virtualAccounts as $virtualKey => $virtualData) {
            $returnsData[$virtualKey] = $virtualData;
            $totalReturnEUR += $virtualData['EUR']['actualReturn'] ?? 0;
            $totalReturnUSD += $virtualData['USD']['actualReturn'] ?? 0;
        }

        // Add aggregate totals to the data
        $returnsData['totalReturnEUR'] = $totalReturnEUR;
        $returnsData['totalReturnUSD'] = $totalReturnUSD;
        $returnsData['totalReturnEURFormatted'] = MoneyFormat::get_formatted_gain('€', $totalReturnEUR);
        $returnsData['totalReturnUSDFormatted'] = MoneyFormat::get_formatted_gain('$', $totalReturnUSD);

        return $returnsData;
    }

    /**
     * Calculate returns for a single account
     */
    private function _calculateAccountReturns(Account $account, int $year): array
    {
        $accountId = $account->id;
        $baseCurrency = $account->currency->iso_code;

        // Create date range (Jan 1 - Dec 31 or today if current year)
        try {
            [$jan1, $dec31] = $this->_createDateRange($year);
        } catch (\Exception $e) {
            Log::error('Invalid date for year: ' . $year . '. Error: ' . $e->getMessage());
            return $this->_buildEmptyReturnsArray($account, $baseCurrency);
        }

        // Fetch portfolio values at start and end of period
        $portfolioValues = $this->_fetchPortfolioValues($account, $jan1, $dec31);

        // Fetch all transactions for the year (pass account to avoid redundant queries)
        $transactions = $this->_fetchAllTransactions($account, $year);

        // Calculate all totals with override handling
        $totals = $this->_calculateTotals(
            $transactions,
            $accountId,
            $year,
            $baseCurrency
        );

        // Calculate actual return using the formula
        $actualReturn = $this->_computeActualReturn(
            $totals,
            $portfolioValues['jan1']['total'],
            $portfolioValues['dec31']['total']
        );

        // Create dividends summary
        $dividendsSummary = $this->_dividends->createDividendsSummaryByTransactionCurrency(
            $transactions['dividendsList'],
            $baseCurrency,
            $accountId
        );

        // Build result array with all data and formatting
        return [
            'account' => $account,
            'baseCurrency' => $baseCurrency,
            'jan1Value' => $portfolioValues['jan1']['total'],
            'jan1PositionsValue' => $portfolioValues['jan1']['positions'],
            'jan1CashValue' => $portfolioValues['jan1']['cash'],
            'jan1PositionDetails' => $portfolioValues['jan1']['positionDetails'],
            'jan1Date' => $portfolioValues['jan1']['date'],
            'dec31Value' => $portfolioValues['dec31']['total'],
            'dec31PositionsValue' => $portfolioValues['dec31']['positions'],
            'dec31CashValue' => $portfolioValues['dec31']['cash'],
            'dec31PositionDetails' => $portfolioValues['dec31']['positionDetails'],
            'dec31Date' => $portfolioValues['dec31']['date'],
            'deposits' => $transactions['deposits'],
            'withdrawals' => $transactions['withdrawals'],
            'dividends' => $transactions['dividendsList'],
            'dividendsSummary' => $dividendsSummary,
            'purchases' => $transactions['purchases'],
            'sales' => $transactions['sales'],
            'excludedTrades' => $transactions['excludedTrades'],
            'totalDeposits' => $totals['totalDeposits'],
            'totalDepositsCalculated' => $totals['totalDepositsCalculated'],
            'totalDepositsOverride' => $totals['totalDepositsOverride'],
            'totalWithdrawals' => $totals['totalWithdrawals'],
            'totalWithdrawalsCalculated' => $totals['totalWithdrawalsCalculated'],
            'totalWithdrawalsOverride' => $totals['totalWithdrawalsOverride'],
            'totalGrossDividends' => $totals['totalGrossDividends'],
            'totalGrossDividendsCalculated' => $totals['totalGrossDividendsCalculated'],
            'totalGrossDividendsOverride' => $totals['totalGrossDividendsOverride'],
            'totalPurchases' => $totals['totalPurchases'],
            'totalSales' => $totals['totalSales'],
            'totalPurchasesNet' => $totals['totalPurchasesNet'],
            'totalSalesNet' => $totals['totalSalesNet'],
            'actualReturn' => $actualReturn,
            'jan1ValueFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $portfolioValues['jan1']['total']
            ),
            'dec31ValueFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $portfolioValues['dec31']['total']
            ),
            'totalDepositsFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalDeposits']
            ),
            'totalDepositsCalculatedFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalDepositsCalculated']
            ),
            'totalWithdrawalsFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalWithdrawals']
            ),
            'totalWithdrawalsCalculatedFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalWithdrawalsCalculated']
            ),
            'totalPurchasesFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalPurchases']
            ),
            'totalSalesFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalSales']
            ),
            'totalPurchasesNetFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalPurchasesNet']
            ),
            'totalSalesNetFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalSalesNet']
            ),
            'totalGrossDividendsFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalGrossDividends']
            ),
            'totalGrossDividendsCalculatedFormatted' => MoneyFormat::get_formatted_balance(
                $baseCurrency,
                $totals['totalGrossDividendsCalculated']
            ),
            'actualReturnFormatted' => MoneyFormat::get_formatted_gain(
                $baseCurrency,
                $actualReturn
            ),
        ];
    }

    /**
     * Check if an account has any meaningful activity in the year
     */
    private function _hasAccountActivity(array $baseReturns, array $converted): bool
    {
        // Has activity if there are any transactions
        if (!empty($baseReturns['deposits'])
            || !empty($baseReturns['withdrawals'])
            || !empty($baseReturns['dividends'])
            || !empty($baseReturns['purchases'])
            || !empty($baseReturns['sales'])
            || !empty($baseReturns['excludedTrades'])
        ) {
            return true;
        }

        // Has activity if there are meaningful position or cash values
        if ($this->_hasSignificantValue((float)$baseReturns['jan1PositionsValue'])
            || $this->_hasSignificantValue((float)$baseReturns['dec31PositionsValue'])
            || $this->_hasSignificantValue((float)$baseReturns['jan1CashValue'])
            || $this->_hasSignificantValue((float)$baseReturns['dec31CashValue'])
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if a value is significantly different from zero
     */
    private function _hasSignificantValue(float $value): bool
    {
        return abs($value) > ReturnsConstants::SIGNIFICANT_VALUE_THRESHOLD;
    }

    /**
     * Process virtual accounts from config for the given year
     *
     * Virtual accounts are not real brokerage accounts — they exist only to adjust
     * total returns (e.g., for cross-account share transfers that create imbalances).
     *
     * @param int $year The year
     * @return array Keyed by 'virtual_<id>', each entry has EUR/USD actualReturn and metadata
     */
    private function _processVirtualAccounts(int $year): array
    {
        $virtualAccounts = config('trades.virtual_accounts', []);
        $result = [];

        foreach ($virtualAccounts as $id => $config) {
            $returns = $config['returns'] ?? [];
            if (!isset($returns[$year])) {
                continue;
            }

            $yearReturns = $returns[$year];
            $eurReturn = $yearReturns['EUR'] ?? 0;
            $usdReturn = $yearReturns['USD'] ?? 0;
            $reason = $yearReturns['reason'] ?? null;

            $result['virtual_' . $id] = [
                'isVirtual' => true,
                'virtualName' => $config['name'] ?? $id,
                'virtualReason' => $reason,
                'EUR' => [
                    'actualReturn' => $eurReturn,
                ],
                'USD' => [
                    'actualReturn' => $usdReturn,
                ],
            ];
        }

        return $result;
    }

    /**
     * Get returns override for an account and year
     *
     * @param int $accountId The account ID
     * @param int $year The year
     * @return array|null Array with EUR and USD overrides, or null if no override exists
     */
    private function _getReturnsOverride(int $accountId, int $year): ?array
    {
        $config = config('trades.returns_overrides', []);
        $byAccount = $config['by_account'] ?? [];

        // Check if there's an override for this account and year
        if (isset($byAccount[$accountId][$year])) {
            return $byAccount[$accountId][$year];
        }

        return null;
    }

    /**
     * Get deposits override for an account and year
     *
     * @param int $accountId The account ID
     * @param int $year The year
     * @return array|null Array with EUR and USD overrides, or null if no override exists
     */
    private function _getDepositsOverride(int $accountId, int $year): ?array
    {
        $config = config('trades.deposits_overrides', []);
        $byAccount = $config['by_account'] ?? [];

        // Check if there's an override for this account and year
        if (isset($byAccount[$accountId][$year])) {
            return $byAccount[$accountId][$year];
        }

        return null;
    }

    /**
     * Get withdrawals override for an account and year
     *
     * @param int $accountId The account ID
     * @param int $year The year
     * @return array|null Array with EUR and USD overrides, or null if no override exists
     */
    private function _getWithdrawalsOverride(int $accountId, int $year): ?array
    {
        $config = config('trades.withdrawals_overrides', []);
        $byAccount = $config['by_account'] ?? [];

        // Check if there's an override for this account and year
        if (isset($byAccount[$accountId][$year])) {
            return $byAccount[$accountId][$year];
        }

        return null;
    }

    /**
     * Create date range for the year
     *
     * @param int $year The year
     * @return array Array with [jan1, dec31] DateTime objects
     * @throws \Exception If date creation fails
     */
    private function _createDateRange(int $year): array
    {
        $jan1 = new \DateTime("$year-01-01 00:00:00");
        $dec31 = new \DateTime("$year-12-31 23:59:59");

        // If current year, use today instead of Dec 31 if we haven't reached it yet
        $currentYear = (int) date('Y');
        $today = new \DateTime();
        if ($year === $currentYear && $today < $dec31) {
            $dec31 = clone $today;
            $dec31->setTime(23, 59, 59);
        }

        return [$jan1, $dec31];
    }

    /**
     * Build empty returns array for error cases
     *
     * @param Account $account The account
     * @param string $baseCurrency The base currency code
     * @return array Empty returns array with zero values
     */
    private function _buildEmptyReturnsArray(Account $account, string $baseCurrency): array
    {
        return [
            'account' => $account,
            'baseCurrency' => $baseCurrency,
            'jan1Value' => 0,
            'jan1PositionsValue' => 0,
            'jan1CashValue' => 0,
            'jan1PositionDetails' => [],
            'jan1Date' => null,
            'dec31Value' => 0,
            'dec31PositionsValue' => 0,
            'dec31CashValue' => 0,
            'dec31PositionDetails' => [],
            'dec31Date' => null,
            'deposits' => [],
            'withdrawals' => [],
            'dividends' => [],
            'dividendsSummary' => null,
            'purchases' => [],
            'sales' => [],
            'excludedTrades' => [],
            'totalDeposits' => 0,
            'totalDepositsCalculated' => 0,
            'totalDepositsFees' => 0,
            'totalDepositsOverride' => null,
            'totalWithdrawals' => 0,
            'totalWithdrawalsCalculated' => 0,
            'totalWithdrawalsFees' => 0,
            'totalWithdrawalsOverride' => null,
            'totalGrossDividends' => 0,
            'totalGrossDividendsCalculated' => 0,
            'totalGrossDividendsOverride' => null,
            'totalPurchases' => 0,
            'totalSales' => 0,
            'totalPurchasesNet' => 0,
            'totalSalesNet' => 0,
            'actualReturn' => 0,
            'jan1ValueFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'dec31ValueFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalDepositsFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalDepositsCalculatedFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalWithdrawalsFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalWithdrawalsCalculatedFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalPurchasesFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalSalesFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalPurchasesNetFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalSalesNetFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalGrossDividendsFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'totalGrossDividendsCalculatedFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, 0),
            'actualReturnFormatted' => MoneyFormat::get_formatted_gain($baseCurrency, 0),
        ];
    }

    /**
     * Fetch portfolio values at start and end of period
     *
     * @param Account $account The account (already loaded with currency)
     * @param \DateTime $jan1 Start date
     * @param \DateTime $dec31 End date
     * @return array Portfolio values with jan1 and dec31 data
     */
    private function _fetchPortfolioValues(Account $account, \DateTime $jan1, \DateTime $dec31): array
    {
        $accountId = $account->id;

        // Get cached positions for these dates
        $jan1Positions = $this->_getCachedPositionsForAccount($jan1, $accountId);
        $dec31Positions = $this->_getCachedPositionsForAccount($dec31, $accountId);

        $jan1Data = $this->_valuation->getPortfolioValue($account, $jan1, $jan1Positions);
        $dec31Data = $this->_valuation->getPortfolioValue($account, $dec31, $dec31Positions);

        return [
            'jan1' => [
                'total' => $jan1Data['total'],
                'positions' => $jan1Data['positions'],
                'cash' => $jan1Data['cash'],
                'positionDetails' => $jan1Data['positionDetails'],
                'date' => $jan1,
            ],
            'dec31' => [
                'total' => $dec31Data['total'],
                'positions' => $dec31Data['positions'],
                'cash' => $dec31Data['cash'],
                'positionDetails' => $dec31Data['positionDetails'],
                'date' => $dec31,
            ],
        ];
    }

    /**
     * Pre-fetch currencies used for conversion
     * Prevents duplicate Currency queries across accounts
     */
    private function _prefetchCurrencies(array $isoCodes): void
    {
        $currencies = Currency::whereIn('iso_code', $isoCodes)->get();
        foreach ($currencies as $currency) {
            $this->_currencyCache[$currency->iso_code] = $currency;
        }
    }

    /**
     * Pre-fetch positions for given dates
     * Prevents duplicate Positions::getTrades() queries across accounts
     */
    private function _prefetchPositionsForDates(array $dates): void
    {
        $positionsService = new Positions();
        $positionsService->setIncludeClosedTrades(true);

        foreach ($dates as $date) {
            $dateKey = $date->format('Y-m-d');
            if (!isset($this->_positionsCache[$dateKey])) {
                $trades = $positionsService->getTrades($date);
                $positions = Positions::tradesToPositions($trades);
                $this->_positionsCache[$dateKey] = [
                    'trades' => $trades,
                    'positions' => $positions,
                ];
            }
        }
    }

    /**
     * Get cached positions for a specific account and date
     */
    private function _getCachedPositionsForAccount(\DateTime $date, int $accountId): array
    {
        $dateKey = $date->format('Y-m-d');
        if (isset($this->_positionsCache[$dateKey])) {
            return $this->_positionsCache[$dateKey]['positions'][$accountId] ?? [];
        }
        return [];
    }

    /**
     * Fetch all transactions for the year
     *
     * @param Account $account The account (with currency pre-loaded)
     * @param int $year The year
     * @return array All transaction data
     */
    private function _fetchAllTransactions(Account $account, int $year): array
    {
        $accountId = $account->id;

        // Pass pre-loaded account to avoid redundant eager loading queries
        $tradesData = $this->_trades->getPurchasesAndSales($accountId, $year, $account);

        return [
            'deposits' => $this->_deposits->getDeposits($accountId, $year, $account),
            'withdrawals' => $this->_withdrawals->getWithdrawals($accountId, $year, $account),
            'dividendsList' => $this->_dividends->getDividends($accountId, $year, $account),
            'purchases' => $tradesData['purchases'],
            'sales' => $tradesData['sales'],
            'excludedTrades' => $this->_trades->getExcludedTrades($accountId, $year, $account),
        ];
    }

    /**
     * Calculate all totals with override handling
     *
     * @param array $transactions Transaction data
     * @param int $accountId The account ID
     * @param int $year The year
     * @param string $baseCurrency The base currency
     * @return array Calculated totals
     */
    private function _calculateTotals(
        array $transactions,
        int $accountId,
        int $year,
        string $baseCurrency
    ): array
    {
        // Calculate deposit total with override handling
        $totalDepositsCalculated = 0;
        $totalDepositsFees = 0;
        foreach ($transactions['deposits'] as $deposit) {
            $totalDepositsCalculated += $deposit['amount'];
            $totalDepositsFees += $deposit['fee'] ?? 0;
        }

        $depositsOverride = $this->_getDepositsOverride($accountId, $year);
        $depositsOverrideRaw = $depositsOverride;
        $totalDeposits = $totalDepositsCalculated;

        if ($depositsOverride !== null) {
            $overrideValue = $depositsOverride[$baseCurrency] ?? null;
            if ($overrideValue !== null) {
                $totalDeposits = $overrideValue;
            }
        }

        // Calculate withdrawal total with override handling
        $totalWithdrawalsCalculated = 0;
        $totalWithdrawalsFees = 0;
        foreach ($transactions['withdrawals'] as $withdrawal) {
            $totalWithdrawalsCalculated += $withdrawal['amount'];
            $totalWithdrawalsFees += $withdrawal['fee'] ?? 0;
        }

        $withdrawalsOverride = $this->_getWithdrawalsOverride($accountId, $year);
        $withdrawalsOverrideRaw = $withdrawalsOverride;
        $totalWithdrawals = $totalWithdrawalsCalculated;

        if ($withdrawalsOverride !== null) {
            $overrideValue = $withdrawalsOverride[$baseCurrency] ?? null;
            if ($overrideValue !== null) {
                $totalWithdrawals = $overrideValue;
            }
        }

        // Calculate dividends total (convert to account currency)
        $totalGrossDividends = 0;
        foreach ($transactions['dividendsList'] as $dividend) {
            $dividendInAccountCurrency = $dividend['amount'] / $dividend['exchangeRate'];
            $totalGrossDividends += $dividendInAccountCurrency;
        }

        // Apply dividends override if configured
        $totalGrossDividendsOverrideRaw = $this->_dividends->getTotalGrossDividendsOverride(
            $accountId,
            $year
        );
        $dividendsForReturn = $totalGrossDividends;

        if ($totalGrossDividendsOverrideRaw !== null) {
            $overrideValue = is_array($totalGrossDividendsOverrideRaw)
                ? ($totalGrossDividendsOverrideRaw[$baseCurrency] ?? null)
                : $totalGrossDividendsOverrideRaw;
            if ($overrideValue !== null) {
                $dividendsForReturn = $overrideValue;
            }
        }

        // Calculate purchases total (convert to account currency)
        $totalPurchases = 0;
        foreach ($transactions['purchases'] as $purchase) {
            $purchaseInAccountCurrency = $purchase['principal_amount'] / $purchase['exchangeRate'];
            $totalPurchases += $purchaseInAccountCurrency;
        }

        // Calculate sales total (convert to account currency)
        $totalSales = 0;
        foreach ($transactions['sales'] as $sale) {
            $saleInAccountCurrency = $sale['principal_amount'] / $sale['exchangeRate'];
            $totalSales += $saleInAccountCurrency;
        }

        // Calculate net totals (including fees)
        $totalPurchasesNet = $totalPurchases;
        $totalSalesNet = $totalSales;
        foreach ($transactions['purchases'] as $purchase) {
            if (!empty($purchase['fee'])) {
                $feeInAccountCurrency = $purchase['fee'] / $purchase['exchangeRate'];
                $totalPurchasesNet += $feeInAccountCurrency;
            }
        }
        foreach ($transactions['sales'] as $sale) {
            if (!empty($sale['fee'])) {
                $feeInAccountCurrency = $sale['fee'] / $sale['exchangeRate'];
                $totalSalesNet += $feeInAccountCurrency;
            }
        }

        return [
            'totalDeposits' => $totalDeposits,
            'totalDepositsCalculated' => $totalDepositsCalculated,
            'totalDepositsFees' => $totalDepositsFees,
            'totalDepositsOverride' => $depositsOverrideRaw,
            'totalWithdrawals' => $totalWithdrawals,
            'totalWithdrawalsCalculated' => $totalWithdrawalsCalculated,
            'totalWithdrawalsFees' => $totalWithdrawalsFees,
            'totalWithdrawalsOverride' => $withdrawalsOverrideRaw,
            'totalGrossDividends' => $dividendsForReturn,
            'totalGrossDividendsCalculated' => $totalGrossDividends,
            'totalGrossDividendsOverride' => $totalGrossDividendsOverrideRaw,
            'totalPurchases' => $totalPurchases,
            'totalSales' => $totalSales,
            'totalPurchasesNet' => $totalPurchasesNet,
            'totalSalesNet' => $totalSalesNet,
        ];
    }

    /**
     * Compute actual return using the formula
     *
     * Formula: Gross Dividends + End value - Start value
     *          - (Deposits - Deposit Fees) + (Withdrawals + Withdrawal Fees)
     *          - Purchases (net) + Sales (net)
     *
     * Deposits are reduced by fees (net deposit is less when fees apply).
     * Withdrawals are increased by fees (total outflow includes fees).
     * Purchases/Sales already use net totals which include their fees.
     *
     * @param array $totals Calculated totals
     * @param float $jan1Value Start value
     * @param float $dec31Value End value
     * @return float Actual return
     */
    private function _computeActualReturn(array $totals, float $jan1Value, float $dec31Value): float
    {
        $depositsWithFees = $totals['totalDeposits'] - ($totals['totalDepositsFees'] ?? 0);
        $withdrawalsWithFees = $totals['totalWithdrawals'] + ($totals['totalWithdrawalsFees'] ?? 0);

        return $totals['totalGrossDividends']
            + $dec31Value
            - $jan1Value
            - $depositsWithFees
            + $withdrawalsWithFees
            - $totals['totalPurchasesNet']
            + $totals['totalSalesNet'];
    }
}

