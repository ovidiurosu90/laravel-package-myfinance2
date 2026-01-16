<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

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
    private bool $withUser = true;
    private ReturnsValuation $valuation;
    private ReturnsDeposits $deposits;
    private ReturnsWithdrawals $withdrawals;
    private ReturnsDividends $dividends;
    private ReturnsTrades $trades;
    private ReturnsCurrencyConverter $currencyConverter;

    public function __construct(
        ReturnsValuation $valuation = null,
        ReturnsDeposits $deposits = null,
        ReturnsWithdrawals $withdrawals = null,
        ReturnsDividends $dividends = null,
        ReturnsTrades $trades = null,
        ReturnsCurrencyConverter $currencyConverter = null
    ) {
        $this->valuation = $valuation ?? new ReturnsValuation();
        $this->deposits = $deposits ?? new ReturnsDeposits();
        $this->withdrawals = $withdrawals ?? new ReturnsWithdrawals();
        $this->dividends = $dividends ?? new ReturnsDividends();
        $this->trades = $trades ?? new ReturnsTrades();
        $this->currencyConverter = $currencyConverter ?? new ReturnsCurrencyConverter();
    }

    public function setWithUser(bool $withUser = true): void
    {
        $this->withUser = $withUser;
        $this->valuation->setWithUser($withUser);
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
            ->where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        $returnsData = [];
        $totalReturnEUR = 0;
        $totalReturnUSD = 0;

        foreach ($accounts as $account) {
            $baseReturns = $this->calculateAccountReturns($account, $year);

            $accountId = $account->id;

            // Store calculated return before applying override (for display comparison)
            $baseReturns['actualReturnCalculated'] = $baseReturns['actualReturn'];

            // Apply returns override if configured
            $override = $this->getReturnsOverride($accountId, $year);
            if ($override !== null) {
                // Store the fact that an override was applied
                $baseReturns['actualReturnOverride'] = $override;
                $baseReturns['actualReturnOverrideReason'] = $override['reason'] ?? null;
                // Don't change the actualReturn here - let currency converter handle it
                // by passing both calculated and override values
            }

            $converted = $this->currencyConverter->convertReturnsToCurrencies(
                $accountId,
                $baseReturns,
                ['EUR', 'USD'],
                $year
            );

            // Filter out accounts with no activity for the selected year
            if (!$this->hasAccountActivity($baseReturns, $converted)) {
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
    private function calculateAccountReturns(Account $account, int $year): array
    {
        $accountId = $account->id;
        $baseCurrency = $account->currency->iso_code;

        // Create date objects for Jan 1 and Dec 31
        try {
            $jan1 = new \DateTime("$year-01-01 00:00:00");
            $dec31 = new \DateTime("$year-12-31 23:59:59");

            // If we're in the current year, use today's date instead of Dec 31
            $currentYear = (int) date('Y');
            $today = new \DateTime();
            if ($year === $currentYear && $today < $dec31) {
                $dec31 = clone $today;
                $dec31->setTime(23, 59, 59);
            }
        } catch (\Exception $e) {
            Log::error('Invalid date for year: ' . $year . '. Error: ' . $e->getMessage());
            // Return empty/zero returns for invalid year
            return [
                'account' => $account,
                'baseCurrency' => $baseCurrency,
                'jan1Value' => 0,
                'dec31Value' => 0,
                'deposits' => [],
                'withdrawals' => [],
                'totalDeposits' => 0,
                'totalWithdrawals' => 0,
                'totalPurchases' => 0,
                'totalSales' => 0,
                'totalPurchasesNet' => 0,
                'totalSalesNet' => 0,
                'actualReturn' => 0,
                'jan1ValueFormatted' => '0.00',
                'dec31ValueFormatted' => '0.00',
                'totalDepositsFormatted' => '0.00',
                'totalWithdrawalsFormatted' => '0.00',
                'totalPurchasesFormatted' => '0.00',
                'totalSalesFormatted' => '0.00',
                'totalPurchasesNetFormatted' => '0.00',
                'totalSalesNetFormatted' => '0.00',
                'actualReturnFormatted' => '0.00',
            ];
        }

        // 1. Calculate portfolio value at Jan 1
        $jan1PortfolioData = $this->valuation->getPortfolioValue($accountId, $jan1);
        $jan1Value = $jan1PortfolioData['total'];
        $jan1PositionsValue = $jan1PortfolioData['positions'];
        $jan1CashValue = $jan1PortfolioData['cash'];
        $jan1PositionDetails = $jan1PortfolioData['positionDetails'];

        // 2. Calculate portfolio value at end date (Dec 31 or today if current year)
        $dec31PortfolioData = $this->valuation->getPortfolioValue($accountId, $dec31);
        $dec31Value = $dec31PortfolioData['total'];
        $dec31PositionsValue = $dec31PortfolioData['positions'];
        $dec31CashValue = $dec31PortfolioData['cash'];
        $dec31PositionDetails = $dec31PortfolioData['positionDetails'];

        // 3. Get deposits (credits to trading account)
        $deposits = $this->deposits->getDeposits($accountId, $year);

        // 4. Get withdrawals (debits from trading account)
        $withdrawals = $this->withdrawals->getWithdrawals($accountId, $year);

        // 5. Get dividends (gross amounts, excluding fees)
        $dividendsList = $this->dividends->getDividends($accountId, $year);

        // 6. Get stock purchases and sales (in single query to reduce DB round-trips)
        $tradesData = $this->trades->getPurchasesAndSales($accountId, $year);
        $purchases = $tradesData['purchases'];
        $sales = $tradesData['sales'];

        // 7. Get excluded trades for informational display
        $excludedTrades = $this->trades->getExcludedTrades($accountId, $year);

        // 9. Calculate totals
        $totalDeposits = array_sum(array_column($deposits, 'amount'));
        $totalWithdrawals = array_sum(array_column($withdrawals, 'amount'));

        // Convert purchases to account currency before summing
        $totalPurchases = 0;
        foreach ($purchases as $purchase) {
            $purchaseInAccountCurrency = $purchase['principal_amount'] / $purchase['exchangeRate'];
            $totalPurchases += $purchaseInAccountCurrency;
        }

        // Convert sales to account currency before summing
        $totalSales = 0;
        foreach ($sales as $sale) {
            $saleInAccountCurrency = $sale['principal_amount'] / $sale['exchangeRate'];
            $totalSales += $saleInAccountCurrency;
        }

        // Calculate net totals (including fees)
        $totalPurchasesNet = $totalPurchases;
        $totalSalesNet = $totalSales;
        foreach ($purchases as $purchase) {
            if (!empty($purchase['fee'])) {
                $feeInAccountCurrency = $purchase['fee'] / $purchase['exchangeRate'];
                $totalPurchasesNet += $feeInAccountCurrency;
            }
        }
        foreach ($sales as $sale) {
            if (!empty($sale['fee'])) {
                $feeInAccountCurrency = $sale['fee'] / $sale['exchangeRate'];
                $totalSalesNet += $feeInAccountCurrency;
            }
        }

        // Convert dividends to account currency before summing
        $totalGrossDividends = 0;
        foreach ($dividendsList as $dividend) {
            $dividendInAccountCurrency = $dividend['amount'] / $dividend['exchangeRate'];
            $totalGrossDividends += $dividendInAccountCurrency;
        }

        // Check for override value early so we can use it in the calculation
        $totalGrossDividendsOverrideRaw = $this->dividends->getTotalGrossDividendsOverride(
            $accountId,
            $year
        );

        // Extract base currency override value for return calculation
        $totalGrossDividendsOverride = null;
        if ($totalGrossDividendsOverrideRaw !== null) {
            if (is_array($totalGrossDividendsOverrideRaw)) {
                // New format: extract base currency value
                $totalGrossDividendsOverride = $totalGrossDividendsOverrideRaw[$baseCurrency] ?? null;
            } else {
                // Old format: use the value directly
                $totalGrossDividendsOverride = $totalGrossDividendsOverrideRaw;
            }
        }

        $dividendsForReturn = $totalGrossDividendsOverride ?? $totalGrossDividends;

        // 10. Calculate actual return
        // Formula: Gross Dividends + End value – Start value – Deposits + Withdrawals – Purchases (net) + Sales (net)
        $actualReturn = $dividendsForReturn + $dec31Value - $jan1Value - $totalDeposits
            + $totalWithdrawals - $totalPurchasesNet + $totalSalesNet;

        // 11. Create dividends summary
        $dividendsSummary = $this->dividends->createDividendsSummaryByTransactionCurrency(
            $dividendsList,
            $baseCurrency,
            $accountId
        );

        // 12. Build result array with formatting
        return [
            'account' => $account,
            'baseCurrency' => $baseCurrency,
            'jan1Value' => $jan1Value,
            'jan1PositionsValue' => $jan1PositionsValue,
            'jan1CashValue' => $jan1CashValue,
            'jan1PositionDetails' => $jan1PositionDetails,
            'jan1Date' => $jan1,
            'dec31Value' => $dec31Value,
            'dec31PositionsValue' => $dec31PositionsValue,
            'dec31CashValue' => $dec31CashValue,
            'dec31PositionDetails' => $dec31PositionDetails,
            'dec31Date' => $dec31,
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'dividends' => $dividendsList,
            'dividendsSummary' => $dividendsSummary,
            'purchases' => $purchases,
            'sales' => $sales,
            'excludedTrades' => $excludedTrades,
            'totalDeposits' => $totalDeposits,
            'totalWithdrawals' => $totalWithdrawals,
            'totalGrossDividends' => $dividendsForReturn,
            'totalGrossDividendsCalculated' => $totalGrossDividends,
            'totalGrossDividendsOverride' => $totalGrossDividendsOverrideRaw,
            'totalPurchases' => $totalPurchases,
            'totalSales' => $totalSales,
            'totalPurchasesNet' => $totalPurchasesNet,
            'totalSalesNet' => $totalSalesNet,
            'actualReturn' => $actualReturn,
            'jan1ValueFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $jan1Value),
            'dec31ValueFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $dec31Value),
            'totalDepositsFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $totalDeposits),
            'totalWithdrawalsFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $totalWithdrawals),
            'totalPurchasesFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $totalPurchases),
            'totalSalesFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $totalSales),
            'totalPurchasesNetFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $totalPurchasesNet),
            'totalSalesNetFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $totalSalesNet),
            'totalGrossDividendsFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $dividendsForReturn),
            'totalGrossDividendsCalculatedFormatted' => MoneyFormat::get_formatted_balance($baseCurrency, $totalGrossDividends),
            'actualReturnFormatted' => MoneyFormat::get_formatted_gain($baseCurrency, $actualReturn),
        ];
    }

    /**
     * Check if an account has any meaningful activity in the year
     */
    private function hasAccountActivity(array $baseReturns, array $converted): bool
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
        if ($this->hasSignificantValue((float)$baseReturns['jan1PositionsValue'])
            || $this->hasSignificantValue((float)$baseReturns['dec31PositionsValue'])
            || $this->hasSignificantValue((float)$baseReturns['jan1CashValue'])
            || $this->hasSignificantValue((float)$baseReturns['dec31CashValue'])
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check if a value is significantly different from zero
     */
    private function hasSignificantValue(float $value): bool
    {
        return abs($value) > 0.01;
    }

    /**
     * Get returns override for an account and year
     *
     * @param int $accountId The account ID
     * @param int $year The year
     * @return array|null Array with EUR and USD overrides, or null if no override exists
     */
    private function getReturnsOverride(int $accountId, int $year): ?array
    {
        $config = config('trades.returns_overrides', []);
        $byAccount = $config['by_account'] ?? [];

        // Check if there's an override for this account and year
        if (isset($byAccount[$accountId][$year])) {
            return $byAccount[$accountId][$year];
        }

        return null;
    }
}

