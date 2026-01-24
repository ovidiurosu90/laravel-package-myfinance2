<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Trade Transformer
 *
 * Handles transformation of trades (purchases and sales) for dual-currency display.
 * Includes complex fee handling and excluded fees text generation.
 */
class ReturnsTradeTransformer
{
    /**
     * Transform trades (purchases or sales) with fees text pre-calculated
     *
     * @param array $eurTrades EUR currency trades
     * @param array $usdTrades USD currency trades
     * @param array $eurExcluded EUR excluded trades
     * @param array $usdExcluded USD excluded trades
     * @param float $eurExcludedFees EUR excluded fees amount
     * @param float $usdExcludedFees USD excluded fees amount
     * @param string $type Trade type: 'purchase' or 'sale'
     * @param float $eurTotalNet EUR net total (with fees)
     * @param float $usdTotalNet USD net total (with fees)
     * @return array Transformed trades with items and totals
     */
    public function transformTrades(
        array $eurTrades,
        array $usdTrades,
        array $eurExcluded,
        array $usdExcluded,
        float $eurExcludedFees = 0,
        float $usdExcludedFees = 0,
        string $type = 'purchase',
        float $eurTotalNet = 0,
        float $usdTotalNet = 0
    ): array
    {
        $trades = [];
        $totalEURPrincipal = 0;
        $totalUSDPrincipal = 0;
        $totalEURFees = 0;
        $totalUSDFees = 0;

        foreach ($eurTrades as $eurTrade) {
            $id = $eurTrade['id'] ?? null;
            $usdTrade = collect($usdTrades)->firstWhere('id', $id);

            $eurFee = $eurTrade['fee'] ?? 0;
            $usdFee = $usdTrade['fee'] ?? $eurTrade['fee'] ?? 0;

            // Determine account currency and use the corresponding fee value
            $accountCurrencyCode = $eurTrade['accountCurrencyCode'] ?? '€';
            $accountCurrencyIsoCode = $eurTrade['accountCurrencyIsoCode'] ?? 'EUR';
            $accountCurrencyFee = ($accountCurrencyIsoCode === 'USD' && $usdTrade)
                ? ($usdTrade['fee'] ?? 0)
                : ($eurTrade['fee'] ?? 0);

            // For sales, fees are shown with a minus sign (they reduce proceeds)
            // For purchases, fees are shown without sign
            $accountCurrencyFeeFormatted = $accountCurrencyFee > 0
                ? ($type === 'sale' ? '- ' : '')
                    . MoneyFormat::get_formatted_balance($accountCurrencyCode, $accountCurrencyFee)
                : '';

            $eurPrincipal = $eurTrade['principal_amount'] ?? 0;
            $usdPrincipal = $usdTrade['principal_amount'] ?? $eurTrade['principal_amount'] ?? 0;

            $trades[] = [
                'id' => $id,
                'date' => $eurTrade['date'] ?? '',
                'symbol' => $eurTrade['symbol'] ?? '',
                'quantityFormatted' => $eurTrade['quantityFormatted'] ?? '',
                'unitPriceFormatted' => $eurTrade['unitPriceFormatted'] ?? '',
                'accountCurrencyFee' => $accountCurrencyFee,
                'accountCurrencyFeeFormatted' => $accountCurrencyFeeFormatted,
                'EUR' => [
                    'principalAmount' => $eurPrincipal,
                    'principalAmountFormatted' => $eurTrade['principalAmountFormatted'] ?? '',
                    'fee' => $eurFee,
                    'feeFormatted' => $eurFee > 0
                        ? MoneyFormat::get_formatted_balance('€', $eurFee)
                        : '',
                    'eurusdRate' => $eurTrade['eurusdRate'] ?? '',
                    'conversionPair' => $eurTrade['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurTrade['conversionExchangeRateClean'] ?? '',
                    'showMissingRateWarning' => $eurTrade['showMissingRateWarning'] ?? false,
                ],
                'USD' => [
                    'principalAmount' => $usdPrincipal,
                    'principalAmountFormatted' => $usdTrade['principalAmountFormatted']
                        ?? $eurTrade['principalAmountFormatted'] ?? '',
                    'fee' => $usdFee,
                    'feeFormatted' => $usdFee > 0
                        ? MoneyFormat::get_formatted_balance('$', $usdFee)
                        : '',
                    'eurusdRate' => $usdTrade['eurusdRate'] ?? '',
                    'conversionPair' => $usdTrade['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdTrade['conversionExchangeRateClean'] ?? '',
                    'showMissingRateWarning' => $usdTrade['showMissingRateWarning'] ?? false,
                ],
            ];

            $totalEURPrincipal += $eurPrincipal;
            $totalUSDPrincipal += $usdPrincipal;
            $totalEURFees += $eurFee;
            $totalUSDFees += $usdFee;
        }

        return [
            'items' => $trades,
            'totals' => [
                'EUR' => [
                    'principalAmount' => $eurTotalNet,
                    'principalAmountFormatted' => MoneyFormat::get_formatted_balance('€', $eurTotalNet),
                    'principalAmountGross' => $totalEURPrincipal,
                    'principalAmountGrossFormatted' => MoneyFormat::get_formatted_balance('€', $totalEURPrincipal),
                    'fees' => $totalEURFees,
                    'feesFormatted' => MoneyFormat::get_formatted_balance('€', $totalEURFees),
                    'feesText' => $this->buildFeesText('€', $totalEURFees, $eurExcludedFees, $type),
                    'excludedFees' => $eurExcludedFees,
                    'excludedFeesFormatted' => MoneyFormat::get_formatted_balance('€', abs($eurExcludedFees)),
                ],
                'USD' => [
                    'principalAmount' => $usdTotalNet,
                    'principalAmountFormatted' => MoneyFormat::get_formatted_balance('$', $usdTotalNet),
                    'principalAmountGross' => $totalUSDPrincipal,
                    'principalAmountGrossFormatted' => MoneyFormat::get_formatted_balance('$', $totalUSDPrincipal),
                    'fees' => $totalUSDFees,
                    'feesFormatted' => MoneyFormat::get_formatted_balance('$', $totalUSDFees),
                    'feesText' => $this->buildFeesText('$', $totalUSDFees, $usdExcludedFees, $type),
                    'excludedFees' => $usdExcludedFees,
                    'excludedFeesFormatted' => MoneyFormat::get_formatted_balance('$', abs($usdExcludedFees)),
                ],
            ],
        ];
    }

    /**
     * Build fees text for display
     *
     * @param string $symbol Currency symbol (€ or $)
     * @param float $fees Total fees amount
     * @param float $excludedFees Excluded fees amount (from config)
     * @param string $type Trade type: 'purchase' or 'sale'
     * @return string Formatted fees text
     */
    public function buildFeesText(
        string $symbol,
        float $fees,
        float $excludedFees,
        string $type = 'purchase'
    ): string
    {
        // For purchases: fees are shown as positive (they reduce cost basis)
        // For sales: fees are shown as negative (they reduce proceeds)
        $feesSign = ($type === 'purchase') ? '' : '-';
        $feesFormatted = MoneyFormat::get_formatted_balance($symbol, abs($fees));
        $text = "(including {$feesSign}{$feesFormatted} in fees";

        if (abs($excludedFees) > ReturnsConstants::EPSILON) {
            $excludedFormatted = MoneyFormat::get_formatted_balance($symbol, abs($excludedFees));
            // For purchases: always show as negative (reducing cost basis)
            // For sales: always show as positive (adding back to proceeds)
            $sign = ($type === 'purchase') ? '-' : '+';
            $text .= ", excluding {$sign}{$excludedFormatted} due to config";
        }

        $text .= ")";
        return $text;
    }
}
