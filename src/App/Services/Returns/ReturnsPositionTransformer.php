<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

/**
 * Returns Position Transformer
 *
 * Handles transformation of position details for dual-currency display.
 * Combines EUR and USD position data with price/FX difference calculations.
 */
class ReturnsPositionTransformer
{
    /**
     * Transform position details with both currencies
     *
     * @param array $eurPositions EUR currency positions
     * @param array $usdPositions USD currency positions
     * @return array Transformed positions
     */
    public function transformPositions(array $eurPositions, array $usdPositions): array
    {
        $positions = [];

        foreach ($eurPositions as $eurPos) {
            $symbol = $eurPos['symbol'] ?? '';
            $usdPos = collect($usdPositions)->firstWhere('symbol', $symbol);

            $positions[] = [
                'symbol' => $symbol,
                'quantity' => $eurPos['quantity'] ?? $eurPos['quantityFormatted'] ?? '',
                'quantityFormatted' => $eurPos['quantityFormatted'] ?? '',
                'price' => $eurPos['price'] ?? 0,
                'priceFormatted' => $eurPos['priceFormatted'] ?? '',
                'tradeCurrencyDisplayCode' => $eurPos['tradeCurrencyDisplayCode'] ?? '',
                'localMarketValue' => $eurPos['localMarketValue'] ?? 0,
                'localMarketValueFormatted' => $eurPos['localMarketValueFormatted'] ?? '',
                'priceOverridden' => $eurPos['priceOverridden'] ?? false,
                'apiPrice' => $eurPos['apiPrice'] ?? null,
                'apiPriceFormatted' => $eurPos['apiPriceFormatted'] ?? '',
                'configPrice' => $eurPos['configPrice'] ?? null,
                'configPriceFormatted' => $eurPos['configPriceFormatted'] ?? '',
                'priceDiffPercentage' => $this->_calculatePriceDiff($eurPos),
                'exchangeRate' => $eurPos['exchangeRate'] ?? 1,
                'exchangeRateClean' => $eurPos['exchangeRateClean'] ?? '',
                'exchangeRateOverridden' => $eurPos['exchangeRateOverridden'] ?? false,
                'apiExchangeRate' => $eurPos['apiExchangeRate'] ?? null,
                'apiExchangeRateFormatted' => $eurPos['apiExchangeRateFormatted'] ?? '',
                'exchangeRateFormatted' => $eurPos['exchangeRateFormatted'] ?? '',
                'fxDiffPercentage' => $this->_calculateFxDiff($eurPos),
                'EUR' => [
                    'marketValue' => $eurPos['marketValue'] ?? 0,
                    'marketValueFormatted' => $eurPos['marketValueFormatted'] ?? '',
                    'eurusdRate' => $eurPos['eurusdRate'] ?? '',
                    'conversionPair' => $eurPos['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $eurPos['conversionExchangeRateClean'] ?? '',
                ],
                'USD' => [
                    'marketValue' => $usdPos['marketValue'] ?? $eurPos['marketValue'] ?? 0,
                    'marketValueFormatted' => $usdPos['marketValueFormatted']
                        ?? $eurPos['marketValueFormatted'] ?? '',
                    'eurusdRate' => $usdPos['eurusdRate'] ?? '',
                    'conversionPair' => $usdPos['conversionPair'] ?? '',
                    'conversionExchangeRateClean' => $usdPos['conversionExchangeRateClean'] ?? '',
                ],
            ];
        }

        // Sort positions alphabetically by symbol
        usort($positions, fn($a, $b) => strcasecmp($a['symbol'], $b['symbol']));

        return $positions;
    }

    /**
     * Calculate price difference percentage between config/override and API price
     *
     * @param array $position Position data
     * @return float Percentage difference
     */
    private function _calculatePriceDiff(array $position): float
    {
        if (empty($position['apiPrice']) || empty($position['price'])) {
            return 0;
        }

        return abs($position['price'] - $position['apiPrice']) / $position['apiPrice'] * 100;
    }

    /**
     * Calculate FX difference percentage between config/override and API exchange rate
     *
     * @param array $position Position data
     * @return float Percentage difference
     */
    private function _calculateFxDiff(array $position): float
    {
        if (empty($position['apiExchangeRate']) || empty($position['exchangeRate'])) {
            return 0;
        }

        return abs($position['exchangeRate'] - $position['apiExchangeRate'])
            / $position['apiExchangeRate'] * 100;
    }
}
