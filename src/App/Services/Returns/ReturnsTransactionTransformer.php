<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Transaction Transformer
 *
 * Handles transformation of deposits, withdrawals, and dividends for dual-currency display.
 * Transforms transaction lists and calculates totals.
 */
class ReturnsTransactionTransformer
{
    /**
     * Transform deposits with both currencies
     *
     * @param array $eurDeposits EUR currency deposits
     * @param array $usdDeposits USD currency deposits
     * @return array Transformed deposits with items and totals
     */
    public function transformDeposits(array $eurDeposits, array $usdDeposits): array
    {
        $deposits = [];
        $totalEUR = 0;
        $totalUSD = 0;

        foreach ($eurDeposits as $eurDep) {
            $date = $eurDep['date'] ?? '';
            $usdDep = collect($usdDeposits)->firstWhere('date', $date);

            $deposits[] = [
                'date' => $date,
                'description' => $eurDep['description'] ?? '',
                'EUR' => [
                    'amount' => $eurDep['amount'] ?? 0,
                    'formatted' => $eurDep['formatted'] ?? '',
                    'eurusdRate' => $eurDep['eurusdRate'] ?? '',
                    'conversionPair' => $eurDep['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurDep['conversionExchangeRateClean'] ?? '',
                ],
                'USD' => [
                    'amount' => $usdDep['amount'] ?? $eurDep['amount'] ?? 0,
                    'formatted' => $usdDep['formatted'] ?? $eurDep['formatted'] ?? '',
                    'eurusdRate' => $usdDep['eurusdRate'] ?? '',
                    'conversionPair' => $usdDep['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdDep['conversionExchangeRateClean'] ?? '',
                ],
            ];

            $totalEUR += $eurDep['amount'] ?? 0;
            $totalUSD += $usdDep['amount'] ?? $eurDep['amount'] ?? 0;
        }

        return [
            'items' => $deposits,
            'totals' => [
                'EUR' => [
                    'amount' => $totalEUR,
                    'formatted' => MoneyFormat::get_formatted_balance('€', $totalEUR),
                ],
                'USD' => [
                    'amount' => $totalUSD,
                    'formatted' => MoneyFormat::get_formatted_balance('$', $totalUSD),
                ],
            ],
        ];
    }

    /**
     * Transform withdrawals with both currencies
     *
     * @param array $eurWithdrawals EUR currency withdrawals
     * @param array $usdWithdrawals USD currency withdrawals
     * @return array Transformed withdrawals with items and totals
     */
    public function transformWithdrawals(array $eurWithdrawals, array $usdWithdrawals): array
    {
        $withdrawals = [];
        $totalEUR = 0;
        $totalUSD = 0;

        foreach ($eurWithdrawals as $eurWith) {
            $date = $eurWith['date'] ?? '';
            $usdWith = collect($usdWithdrawals)->firstWhere('date', $date);

            $withdrawals[] = [
                'date' => $date,
                'description' => $eurWith['description'] ?? '',
                'EUR' => [
                    'amount' => $eurWith['amount'] ?? 0,
                    'formatted' => $eurWith['formatted'] ?? '',
                    'eurusdRate' => $eurWith['eurusdRate'] ?? '',
                    'conversionPair' => $eurWith['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurWith['conversionExchangeRateClean'] ?? '',
                ],
                'USD' => [
                    'amount' => $usdWith['amount'] ?? $eurWith['amount'] ?? 0,
                    'formatted' => $usdWith['formatted'] ?? $eurWith['formatted'] ?? '',
                    'eurusdRate' => $usdWith['eurusdRate'] ?? '',
                    'conversionPair' => $usdWith['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdWith['conversionExchangeRateClean'] ?? '',
                ],
            ];

            $totalEUR += $eurWith['amount'] ?? 0;
            $totalUSD += $usdWith['amount'] ?? $eurWith['amount'] ?? 0;
        }

        return [
            'items' => $withdrawals,
            'totals' => [
                'EUR' => [
                    'amount' => $totalEUR,
                    'formatted' => MoneyFormat::get_formatted_balance('€', $totalEUR),
                ],
                'USD' => [
                    'amount' => $totalUSD,
                    'formatted' => MoneyFormat::get_formatted_balance('$', $totalUSD),
                ],
            ],
        ];
    }

    /**
     * Transform dividends with both currencies
     *
     * @param array $eurDividends EUR currency dividends
     * @param array $usdDividends USD currency dividends
     * @param float $totalEUR EUR total (may include override)
     * @param float $totalUSD USD total (may include override)
     * @param float|null $calculatedEUR EUR calculated value (before override)
     * @param float|null $calculatedUSD USD calculated value (before override)
     * @param mixed $eurOverride EUR override value
     * @param mixed $usdOverride USD override value
     * @return array Transformed dividends with items and totals
     */
    public function transformDividends(
        array $eurDividends,
        array $usdDividends,
        float $totalEUR,
        float $totalUSD,
        ?float $calculatedEUR = null,
        ?float $calculatedUSD = null,
        $eurOverride = null,
        $usdOverride = null
    ): array
    {
        $dividends = [];

        foreach ($eurDividends as $eurDiv) {
            $symbol = $eurDiv['symbol'] ?? '';
            $date = $eurDiv['date'] ?? '';
            $usdDiv = collect($usdDividends)
                ->where('symbol', $symbol)
                ->where('date', $date)
                ->first();

            $eurAmount = $eurDiv['amount'] ?? 0;
            $usdAmount = $usdDiv['amount'] ?? $eurDiv['amount'] ?? 0;

            $dividends[] = [
                'date' => $date,
                'symbol' => $symbol,
                'description' => $eurDiv['description'] ?? '',
                'fee' => $eurDiv['fee'] ?? 0,
                'feeFormatted' => $eurDiv['feeFormatted'] ?? '',
                'EUR' => [
                    'amount' => $eurAmount,
                    'formatted' => $eurDiv['formatted'] ?? '',
                    'eurusdRate' => $eurDiv['eurusdRate'] ?? '',
                    'conversionPair' => $eurDiv['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurDiv['conversionExchangeRateClean'] ?? '',
                    'showMissingRateWarning' => $eurDiv['showMissingRateWarning'] ?? false,
                ],
                'USD' => [
                    'amount' => $usdAmount,
                    'formatted' => $usdDiv['formatted'] ?? $eurDiv['formatted'] ?? '',
                    'eurusdRate' => $usdDiv['eurusdRate'] ?? '',
                    'conversionPair' => $usdDiv['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdDiv['conversionExchangeRateClean'] ?? '',
                    'showMissingRateWarning' => $usdDiv['showMissingRateWarning'] ?? false,
                ],
            ];
        }

        // Build totals using pre-calculated values from service
        $eurTotal = [
            'amount' => $totalEUR,
            'formatted' => MoneyFormat::get_formatted_balance('€', $totalEUR),
        ];

        // Add calculated value only if there's an actual override in the database
        if ($eurOverride !== null && $calculatedEUR !== null) {
            $eurTotal['calculatedAmount'] = $calculatedEUR;
            $eurTotal['calculatedFormatted'] = MoneyFormat::get_formatted_balance('€', $calculatedEUR);
        }

        $usdTotal = [
            'amount' => $totalUSD,
            'formatted' => MoneyFormat::get_formatted_balance('$', $totalUSD),
        ];

        // Add calculated value only if there's an actual override in the database
        if ($usdOverride !== null && $calculatedUSD !== null) {
            $usdTotal['calculatedAmount'] = $calculatedUSD;
            $usdTotal['calculatedFormatted'] = MoneyFormat::get_formatted_balance('$', $calculatedUSD);
        }

        return [
            'items' => $dividends,
            'totals' => [
                'EUR' => $eurTotal,
                'USD' => $usdTotal,
            ],
        ];
    }
}
