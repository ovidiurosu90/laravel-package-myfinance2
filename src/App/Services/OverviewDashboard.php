<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Enums\FundingRole;
use ovidiuro\myfinance2\App\Services\Returns\ReturnsQuoteProvider;

class OverviewDashboard
{
    private ReturnsQuoteProvider $_quoteProvider;

    public function __construct()
    {
        $this->_quoteProvider = new ReturnsQuoteProvider();
    }

    /**
     * Build the full overview data for the dashboard.
     *
     * @return array
     */
    public function handle(): array
    {
        $fundingService = new FundingDashboard();
        $positionsService = new Positions();
        $positionsService->setPersistStats(false);
        $gainsPerYearService = new GainsPerYear();
        $dividendsPerYearService = new DividendsPerYear();

        $fundingData = $fundingService->handle();
        $positionsData = $positionsService->handle();
        $gainsPerYear = $gainsPerYearService->handle();
        $dividendsPerYear = $dividendsPerYearService->handle();

        $eurRatesPerYear = $this->_fetchEurRatesPerYear(
            $gainsPerYear ?? [],
            $dividendsPerYear
        );

        $buckets = $this->_bucketAccountsByFundingRole(
            $fundingData['accounts'],
            $fundingData['balances']
        );

        $investmentAccounts = $this->_buildInvestmentAccounts(
            $positionsData['accountData'],
            $buckets[FundingRole::INVESTMENT->value]
        );

        $topWinners = $this->_computeTopWinners(
            $gainsPerYear ?? [],
            $dividendsPerYear,
            $eurRatesPerYear
        );

        return [
            'sourceAccounts'       => $buckets[FundingRole::SOURCE->value],
            'intermediaryAccounts' => $buckets[FundingRole::INTERMEDIARY->value],
            'otherAccounts'        => $buckets[FundingRole::OTHER->value],
            'uncategorizedAccounts' => $buckets['uncategorized'],
            'investmentAccountsWithPositions' => $investmentAccounts,
            'gainsPerYear'         => $gainsPerYear,
            'dividendsPerYear'     => $dividendsPerYear,
            'eurRatesPerYear'      => $eurRatesPerYear,
            'topWinners'           => $topWinners,
        ];
    }

    /**
     * Split accounts into buckets based on their funding_role.
     *
     * @param array $accounts  Keyed by account ID
     * @param array $balances  Keyed by account ID
     * @return array
     */
    private function _bucketAccountsByFundingRole(
        array $accounts,
        array $balances
    ): array
    {
        $buckets = [
            FundingRole::SOURCE->value       => [],
            FundingRole::INTERMEDIARY->value  => [],
            FundingRole::INVESTMENT->value    => [],
            FundingRole::OTHER->value         => [],
            'uncategorized'                   => [],
        ];

        foreach ($balances as $accountId => $balance) {
            if (empty($accounts[$accountId])) {
                continue;
            }

            $account = $accounts[$accountId];
            $role = $account->funding_role;

            $eurConversion = $this->_convertToEur(
                $balance,
                $account->currency->iso_code,
                (int) $accountId
            );

            $entry = [
                'account'                  => $account,
                'balance'                  => $balance,
                'balance_in_eur'           => $eurConversion['amount'],
                'balance_in_eur_formatted' => $eurConversion['formatted'],
                'exchange_rate'            => $eurConversion['exchange_rate'],
                'conversion_pair'          => $eurConversion['conversion_pair'],
                'eurusd_rate'              => $eurConversion['eurusd_rate'],
            ];

            if ($role instanceof FundingRole) {
                $buckets[$role->value][$accountId] = $entry;
            } else {
                $buckets['uncategorized'][$accountId] = $entry;
            }
        }

        foreach ($buckets as $key => $bucket) {
            uasort($buckets[$key], function ($a, $b)
            {
                return strcasecmp(
                    $a['account']->name,
                    $b['account']->name
                );
            });
        }

        return $buckets;
    }

    /**
     * Build the investment accounts list by merging open positions
     * with cash-only investment accounts.
     *
     * @param array $positionAccountData  From Positions service
     * @param array $investmentBucket     Investment accounts from funding buckets
     * @return array Sorted by account name
     */
    private function _buildInvestmentAccounts(
        array $positionAccountData,
        array $investmentBucket
    ): array
    {
        $accounts = array_filter(
            $positionAccountData,
            fn($totals) => $totals['total_cost'] != 0
                || $totals['total_market_value'] != 0
        );

        // Add cash + balance + EUR conversion to accounts with positions
        foreach ($accounts as $accountId => &$totals) {
            $displayCode = $totals['accountModel']->currency->display_code;
            $isoCode = $totals['accountModel']->currency->iso_code;
            $cash = 0;
            if (!empty($totals['cashBalanceUtils'])) {
                $cash = $totals['cashBalanceUtils']->getAmount() ?? 0;
            }
            $totals['cash'] = $cash;
            $totals['cash_formatted'] = MoneyFormat::get_formatted_balance(
                $displayCode,
                $cash
            );
            $totals['balance'] = $totals['total_market_value'] + $cash;
            $totals['balance_formatted'] = MoneyFormat::get_formatted_balance(
                $displayCode,
                $totals['balance']
            );

            $eurConversion = $this->_convertToEur(
                $totals['balance'],
                $isoCode,
                (int) $accountId
            );
            $totals['balance_in_eur'] = $eurConversion['amount'];
            $totals['balance_in_eur_formatted'] = $eurConversion['formatted'];
            $totals['exchange_rate'] = $eurConversion['exchange_rate'];
            $totals['conversion_pair'] = $eurConversion['conversion_pair'];
            $totals['eurusd_rate'] = $eurConversion['eurusd_rate'];
        }
        unset($totals);

        // Add cash-only investment accounts (not already in positions)
        foreach ($investmentBucket as $accountId => $data) {
            if (isset($accounts[$accountId])) {
                continue;
            }

            $displayCode = $data['account']->currency->display_code;
            $isoCode = $data['account']->currency->iso_code;
            $cash = $data['balance'];

            $eurConversion = $this->_convertToEur(
                $cash,
                $isoCode,
                (int) $accountId
            );

            $accounts[$accountId] = [
                'accountModel'                 => $data['account'],
                'total_cost'                   => 0,
                'total_cost_formatted'         => '',
                'total_market_value'           => 0,
                'total_market_value_formatted' => '',
                'total_change'                 => 0,
                'total_change_formatted'       => '',
                'cash'                         => $cash,
                'cash_formatted'               => MoneyFormat
                    ::get_formatted_balance($displayCode, $cash),
                'balance'                      => $cash,
                'balance_formatted'            => MoneyFormat
                    ::get_formatted_balance($displayCode, $cash),
                'balance_in_eur'               => $eurConversion['amount'],
                'balance_in_eur_formatted'     => $eurConversion['formatted'],
                'exchange_rate'                => $eurConversion['exchange_rate'],
                'conversion_pair'              => $eurConversion['conversion_pair'],
                'eurusd_rate'                  => $eurConversion['eurusd_rate'],
            ];
        }

        uasort($accounts, function ($a, $b)
        {
            return strcasecmp(
                $a['accountModel']->name,
                $b['accountModel']->name
            );
        });

        return $accounts;
    }

    /**
     * Fetch EURUSD exchange rates per year per account using
     * ReturnsQuoteProvider (same logic as /returns, including
     * per-account overrides).
     *
     * @param array $gainsPerYear     year => accountId => symbol => data
     * @param array $dividendsPerYear year => accountId => symbol => data
     * @return array year => accountId => {eurusd_rate, exchange_rate,
     *               conversion_pair}
     */
    private function _fetchEurRatesPerYear(
        array $gainsPerYear,
        array $dividendsPerYear
    ): array
    {
        $allYears = array_unique(array_merge(
            array_keys($gainsPerYear),
            array_keys($dividendsPerYear)
        ));

        if (empty($allYears)) {
            return [];
        }

        $currentYear = (int) date('Y');
        $eurRatesPerYear = [];

        foreach ($allYears as $year) {
            $eurRatesPerYear[$year] = [];
            $allAccounts = array_unique(array_merge(
                array_keys($gainsPerYear[$year] ?? []),
                array_keys($dividendsPerYear[$year] ?? [])
            ));

            $intYear = (int) $year;
            if ($intYear === $currentYear) {
                $date = new \DateTime();
            } else {
                $date = new \DateTime("$year-12-31");
            }

            foreach ($allAccounts as $accId) {
                $accountModel = null;
                if (!empty($gainsPerYear[$year][$accId])) {
                    $firstSym = array_key_first(
                        $gainsPerYear[$year][$accId]
                    );
                    $accountModel = $gainsPerYear[$year]
                        [$accId][$firstSym]['accountModel'];
                } elseif (!empty($dividendsPerYear[$year][$accId])) {
                    $firstSym = array_key_first(
                        $dividendsPerYear[$year][$accId]
                    );
                    $accountModel = $dividendsPerYear[$year]
                        [$accId][$firstSym]['accountModel'];
                }

                if (!$accountModel) {
                    continue;
                }

                $isoCode = $accountModel->currency->iso_code;

                if ($isoCode === 'EUR') {
                    $eurRatesPerYear[$year][$accId] = [
                        'eurusd_rate'     => null,
                        'exchange_rate'   => null,
                        'conversion_pair' => null,
                    ];
                    continue;
                }

                if ($isoCode === 'USD') {
                    $eurusdRate = $this->_quoteProvider
                        ->getExchangeRate(
                            (int) $accId, 'EUR', 'USD', $date
                        );
                    $eurRatesPerYear[$year][$accId] = [
                        'eurusd_rate'     => $eurusdRate,
                        'exchange_rate'   => 1 / $eurusdRate,
                        'conversion_pair' => 'USD->EUR',
                    ];
                    continue;
                }

                // Unsupported currency
                $eurRatesPerYear[$year][$accId] = [
                    'eurusd_rate'     => null,
                    'exchange_rate'   => null,
                    'conversion_pair' => null,
                ];
            }
        }

        return $eurRatesPerYear;
    }

    /**
     * Compute top 3 accounts and top 5 symbols by total
     * gain in EUR (stock gains + dividends, all years).
     *
     * @param array $gainsPerYear     year => accId => sym => data
     * @param array $dividendsPerYear year => accId => sym => data
     * @param array $eurRatesPerYear  year => accId => rate data
     * @return array{topAccounts: array, topSymbols: array}
     */
    private function _computeTopWinners(
        array $gainsPerYear,
        array $dividendsPerYear,
        array $eurRatesPerYear
    ): array
    {
        $accountTotals = [];
        $symbolTotals = [];
        $gainsAnnotations = config('trades.gains_annotations', []);

        $allYears = array_unique(array_merge(
            array_keys($gainsPerYear),
            array_keys($dividendsPerYear)
        ));

        foreach ($allYears as $year) {
            $yearGains = $gainsPerYear[$year] ?? [];
            $yearDividends = $dividendsPerYear[$year] ?? [];

            $allAccIds = array_unique(array_merge(
                array_keys($yearGains),
                array_keys($yearDividends)
            ));

            foreach ($allAccIds as $accId) {
                $rateData = $eurRatesPerYear[$year][$accId] ?? [];
                $eurRate = $rateData['exchange_rate'] ?? null;

                $accountModel = null;
                $gains = $yearGains[$accId] ?? [];
                $dividends = $yearDividends[$accId] ?? [];

                if (!empty($gains)) {
                    $firstSym = array_key_first($gains);
                    $accountModel = $gains[$firstSym]['accountModel'];
                } elseif (!empty($dividends)) {
                    $firstSym = array_key_first($dividends);
                    $accountModel = $dividends[$firstSym]['accountModel'];
                }

                if (!$accountModel) {
                    continue;
                }

                $allSyms = array_unique(array_merge(
                    array_keys($gains),
                    array_keys($dividends)
                ));

                foreach ($allSyms as $sym) {
                    $gain = $gains[$sym]
                        ['total_gain_in_account_currency'] ?? 0;
                    $div = $dividends[$sym]
                        ['total_dividend_in_account_currency'] ?? 0;
                    $total = $gain + $div;

                    $totalEur = $eurRate !== null
                        ? $total * $eurRate : $total;

                    $isAnnotated = !empty(
                        $gainsAnnotations[$year][$accId][$sym]
                    );
                    $annotatedEur = $isAnnotated
                        ? ($eurRate !== null
                            ? $gain * $eurRate : $gain)
                        : 0;

                    if (!isset($accountTotals[$accId])) {
                        $accountTotals[$accId] = [
                            'name'            => $accountModel->name,
                            'total_eur'       => 0,
                            'transferred_eur' => 0,
                            'per_year'        => [],
                        ];
                    }
                    $accountTotals[$accId]['total_eur'] += $totalEur;
                    $accountTotals[$accId]['transferred_eur']
                        += $annotatedEur;
                    if (!isset($accountTotals[$accId]
                        ['per_year'][$year])
                    ) {
                        $accountTotals[$accId]
                            ['per_year'][$year] = 0;
                    }
                    $accountTotals[$accId]
                        ['per_year'][$year] += $totalEur;

                    if (!isset($symbolTotals[$sym])) {
                        $symbolTotals[$sym] = [
                            'name'            => $sym,
                            'total_eur'       => 0,
                            'transferred_eur' => 0,
                            'per_year'        => [],
                        ];
                    }
                    $symbolTotals[$sym]['total_eur'] += $totalEur;
                    $symbolTotals[$sym]['transferred_eur']
                        += $annotatedEur;
                    if (!isset($symbolTotals[$sym]
                        ['per_year'][$year])
                    ) {
                        $symbolTotals[$sym]
                            ['per_year'][$year] = 0;
                    }
                    $symbolTotals[$sym]
                        ['per_year'][$year] += $totalEur;
                }
            }
        }

        uasort(
            $accountTotals,
            fn($a, $b) => $b['total_eur'] <=> $a['total_eur']
        );
        uasort(
            $symbolTotals,
            fn($a, $b) => $b['total_eur'] <=> $a['total_eur']
        );

        return [
            'topAccounts' => array_slice(
                array_values($accountTotals), 0, 3
            ),
            'topSymbols' => array_slice(
                array_values($symbolTotals), 0, 5
            ),
        ];
    }

    /**
     * Convert an amount to EUR using ReturnsQuoteProvider
     * (same logic as /returns, with per-account overrides).
     *
     * @param float  $amount
     * @param string $fromCurrency ISO code
     * @param int    $accountId
     * @return array{amount: float, formatted: string,
     *               exchange_rate: float|null,
     *               conversion_pair: string|null,
     *               eurusd_rate: float|null}
     */
    private function _convertToEur(
        float $amount,
        string $fromCurrency,
        int $accountId
    ): array
    {
        $eurDisplayCode = '&euro;';

        if ($fromCurrency === 'EUR') {
            return [
                'amount'          => $amount,
                'formatted'       => MoneyFormat::get_formatted_balance(
                    $eurDisplayCode, $amount
                ),
                'exchange_rate'   => null,
                'conversion_pair' => null,
                'eurusd_rate'     => null,
            ];
        }

        $exchangeRate = null;
        $conversionPair = null;
        $eurusdRate = null;

        if ($fromCurrency === 'USD') {
            $today = new \DateTime();
            $eurusdRate = $this->_quoteProvider->getExchangeRate(
                $accountId, 'EUR', 'USD', $today
            );
            $exchangeRate = 1 / $eurusdRate;
            $conversionPair = 'USD->EUR';
        }

        if ($exchangeRate !== null) {
            $convertedAmount = $amount * $exchangeRate;
            return [
                'amount'          => $convertedAmount,
                'formatted'       => MoneyFormat::get_formatted_balance(
                    $eurDisplayCode, $convertedAmount
                ),
                'exchange_rate'   => $exchangeRate,
                'conversion_pair' => $conversionPair,
                'eurusd_rate'     => $eurusdRate,
            ];
        }

        return [
            'amount'          => $amount,
            'formatted'       => MoneyFormat::get_formatted_balance(
                $eurDisplayCode, $amount
            ),
            'exchange_rate'   => null,
            'conversion_pair' => null,
            'eurusd_rate'     => null,
        ];
    }
}

