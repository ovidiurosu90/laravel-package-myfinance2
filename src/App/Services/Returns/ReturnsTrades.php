<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Services\MoneyFormat;

/**
 * Returns Trades Service
 *
 * Handles fetching, formatting, and categorizing trades (purchases, sales, excluded)
 * for returns calculations.
 */
class ReturnsTrades
{
    /**
     * Fetch both purchases and sales in a single query to reduce DB round-trips
     * Returns array with 'purchases' and 'sales' keys
     */
    public function getPurchasesAndSales(int $accountId, int $year): array
    {
        $startDate = "$year-01-01 00:00:00";
        $endDate = "$year-12-31 23:59:59";

        // Fetch all BUY and SELL trades in a single query with relationships eager-loaded once
        $trades = Trade::with('accountModel.currency', 'tradeCurrencyModel')
            ->where('account_id', $accountId)
            ->whereIn('action', ['BUY', 'SELL'])
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp', 'ASC')
            ->get();

        // Get excluded trade IDs from config
        $excludedTradeIds = config('trades.exclude_trades_from_returns', []);

        $purchases = [];
        $sales = [];

        foreach ($trades as $trade) {
            // Skip excluded trades
            if (in_array($trade->id, $excludedTradeIds)) {
                continue;
            }

            $formattedTrade = $this->formatTradeForDisplay($trade);

            if ($trade->action === 'BUY') {
                $purchases[] = $formattedTrade;
            } else {
                $sales[] = $formattedTrade;
            }
        }

        return [
            'purchases' => $purchases,
            'sales' => $sales,
        ];
    }

    /**
     * Get stock purchases (BUY trades) for a year
     */
    public function getPurchases(int $accountId, int $year): array
    {
        $tradesData = $this->getPurchasesAndSales($accountId, $year);
        return $tradesData['purchases'];
    }

    /**
     * Get stock sales (SELL trades) for a year
     */
    public function getSales(int $accountId, int $year): array
    {
        $tradesData = $this->getPurchasesAndSales($accountId, $year);
        return $tradesData['sales'];
    }

    /**
     * Get excluded trades (BUY and SELL) for a year - for informational display
     */
    public function getExcludedTrades(int $accountId, int $year): array
    {
        $startDate = "$year-01-01 00:00:00";
        $endDate = "$year-12-31 23:59:59";

        // Get excluded trade IDs from config
        $excludedTradeIds = config('trades.exclude_trades_from_returns', []);

        if (empty($excludedTradeIds)) {
            return [];
        }

        $trades = Trade::with('accountModel.currency', 'tradeCurrencyModel')
            ->where('account_id', $accountId)
            ->whereIn('id', $excludedTradeIds)
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->orderBy('timestamp', 'ASC')
            ->get();

        $excluded = [];
        foreach ($trades as $trade) {
            $timestamp = $trade->timestamp;
            if (is_string($timestamp)) {
                $timestamp = new \DateTime($timestamp);
            }

            $principalAmount = $trade->quantity * $trade->unit_price;

            $numDecimals = MoneyFormat::get_price_decimals($trade->unit_price);
            $quantityFormatted = MoneyFormat::get_formatted_quantity_plain($trade->quantity);
            $exchangeRateFormatted = MoneyFormat::get_formatted_rate_plain($trade->exchange_rate);

            $excluded[] = [
                'id' => $trade->id,
                'date' => $timestamp->format('Y-m-d'),
                'symbol' => $trade->symbol,
                'quantity' => $trade->quantity,
                'quantityFormatted' => $quantityFormatted,
                'unit_price' => $trade->unit_price,
                'unitPriceFormatted' => MoneyFormat::get_formatted_amount(
                    $trade->tradeCurrencyModel->display_code,
                    $trade->unit_price,
                    strtolower($trade->action),
                    $numDecimals
                ),
                'principal_amount' => $principalAmount,
                'fee' => $trade->fee,
                'action' => $trade->action,
                'tradeCurrencyCode' => $trade->tradeCurrencyModel->display_code,
                'tradeCurrencyIsoCode' => $trade->tradeCurrencyModel->iso_code,
                'accountCurrencyCode' => $trade->accountModel->currency->display_code,
                'accountCurrencyIsoCode' => $trade->accountModel->currency->iso_code,
                'exchangeRate' => (float)($trade->exchange_rate ?: 1),
                'exchangeRateFormatted' => $exchangeRateFormatted,
                'formatted' => MoneyFormat::get_formatted_balance(
                    $trade->tradeCurrencyModel->display_code,
                    $principalAmount
                ),
                'feeFormatted' => $trade->fee > 0 ? MoneyFormat::get_formatted_fee(
                    $trade->accountModel->currency->display_code,
                    $trade->fee
                ) : '',
            ];
        }

        return $excluded;
    }

    /**
     * Format a single trade for display
     */
    private function formatTradeForDisplay(Trade $trade): array
    {
        $timestamp = $trade->timestamp;
        if (is_string($timestamp)) {
            $timestamp = new \DateTime($timestamp);
        }

        $principalAmount = $trade->quantity * $trade->unit_price;
        $numDecimals = MoneyFormat::get_price_decimals($trade->unit_price);
        $quantityFormatted = MoneyFormat::get_formatted_quantity_plain($trade->quantity);
        $exchangeRateFormatted = MoneyFormat::get_formatted_rate_plain($trade->exchange_rate);

        return [
            'id' => $trade->id,
            'date' => $timestamp->format('Y-m-d'),
            'symbol' => $trade->symbol,
            'quantity' => $trade->quantity,
            'quantityFormatted' => $quantityFormatted,
            'unit_price' => $trade->unit_price,
            'unitPriceFormatted' => MoneyFormat::get_formatted_amount(
                $trade->tradeCurrencyModel->display_code,
                $trade->unit_price,
                strtolower($trade->action),
                $numDecimals
            ),
            'principalAmountFormatted' => MoneyFormat::get_formatted_amount(
                $trade->tradeCurrencyModel->display_code,
                $principalAmount,
                strtolower($trade->action),
                2
            ),
            'principal_amount' => $principalAmount,
            'fee' => $trade->fee,
            'tradeCurrencyCode' => $trade->tradeCurrencyModel->display_code,
            'tradeCurrencyIsoCode' => $trade->tradeCurrencyModel->iso_code,
            'accountCurrencyCode' => $trade->accountModel->currency->display_code,
            'accountCurrencyIsoCode' => $trade->accountModel->currency->iso_code,
            'exchangeRate' => (float)($trade->exchange_rate ?: 1),
            'exchangeRateFormatted' => $exchangeRateFormatted,
            'formatted' => MoneyFormat::get_formatted_balance(
                $trade->tradeCurrencyModel->display_code,
                $principalAmount
            ),
            'feeFormatted' => $trade->fee > 0 ? MoneyFormat::get_formatted_fee(
                $trade->accountModel->currency->display_code,
                $trade->fee
            ) : '',
        ];
    }
}

