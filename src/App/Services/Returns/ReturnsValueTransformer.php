<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Value Transformer
 *
 * Handles transformation of simple monetary values for dual-currency display.
 * Transforms EUR/USD pairs into view-ready structures.
 */
class ReturnsValueTransformer
{
    /**
     * Transform a value for both currencies
     *
     * @param float $eurValue EUR value
     * @param float $usdValue USD value
     * @param string $eurSymbol EUR symbol (typically '€')
     * @param string $usdSymbol USD symbol (typically '$')
     * @return array Transformed value with EUR/USD data
     */
    public function transformValue(
        float $eurValue,
        float $usdValue,
        string $eurSymbol,
        string $usdSymbol
    ): array
    {
        return [
            'EUR' => [
                'value' => $eurValue,
                'formatted' => MoneyFormat::get_formatted_balance($eurSymbol, $eurValue),
                'plain' => number_format(abs($eurValue), 2) . ' ' . $eurSymbol,
            ],
            'USD' => [
                'value' => $usdValue,
                'formatted' => MoneyFormat::get_formatted_balance($usdSymbol, $usdValue),
                'plain' => number_format(abs($usdValue), 2) . ' ' . $usdSymbol,
            ],
        ];
    }

    /**
     * Transform a value for both currencies with color formatting (red/green for negative/positive)
     *
     * @param float $eurValue EUR value
     * @param float $usdValue USD value
     * @param string $eurSymbol EUR symbol (typically '€')
     * @param string $usdSymbol USD symbol (typically '$')
     * @return array Transformed value with colored formatting
     */
    public function transformValueColored(
        float $eurValue,
        float $usdValue,
        string $eurSymbol,
        string $usdSymbol
    ): array
    {
        return [
            'EUR' => [
                'value' => $eurValue,
                'formatted' => MoneyFormat::get_formatted_gain($eurSymbol, $eurValue),
            ],
            'USD' => [
                'value' => $usdValue,
                'formatted' => MoneyFormat::get_formatted_gain($usdSymbol, $usdValue),
            ],
        ];
    }
}
