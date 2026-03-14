<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

class OrderSuggestion
{
    private const TARGET_AMOUNT    = 5000.0;
    private const PRICE_OFFSET_PCT = 0.025;
    private const STRONG_BUY_PCT   = 15.0;

    /**
     * Compute a suggested order based on finance data and account context.
     *
     * @param array       $financeData     Result from FinanceUtils::getFinanceDataBySymbol()
     * @param float       $openQuantity    Net open quantity for the symbol (BUY - SELL)
     * @param float       $eurRate         EUR → trade_currency exchange rate (1.0 if EUR)
     * @param string|null $accountCurrency Account's ISO currency code; null = unknown
     *
     * @return array{
     *   action: string,
     *   limit_price: float,
     *   weak_signal: bool,
     *   suggested_qty: float|null,
     *   is_partial_sell: bool,
     *   open_quantity: float,
     *   pct_below_high: float,
     *   pct_above_low: float,
     * }
     */
    public function compute(
        array $financeData,
        float $openQuantity,
        float $eurRate,
        ?string $accountCurrency
    ): array
    {
        $price         = (float) $financeData['price'];
        $tradeCurrency = (string) $financeData['currency'];
        $pctBelowHigh  = -(float) $financeData['fiftyTwoWeekHighChangePercent'] * 100;
        $pctAboveLow   = (float) $financeData['fiftyTwoWeekLowChangePercent'] * 100;

        $hasStrongBuySignal = $pctBelowHigh > self::STRONG_BUY_PCT;
        $hasOpenPositions   = $openQuantity > 0;

        $action     = (!$hasStrongBuySignal && $hasOpenPositions) ? 'SELL' : 'BUY';
        $weakSignal = $action === 'BUY' && !$hasStrongBuySignal;

        $limitPrice = $action === 'BUY'
            ? round($price * (1 - self::PRICE_OFFSET_PCT), 4)
            : round($price * (1 + self::PRICE_OFFSET_PCT), 4);

        $targetInTrade = $accountCurrency !== null
            ? $this->_targetInTradeCurrency($accountCurrency, $tradeCurrency, $eurRate)
            : self::TARGET_AMOUNT; // fallback: 5000 in trade currency

        $suggestedQty  = round($targetInTrade / $limitPrice);
        $isPartialSell = false;

        if ($action === 'SELL') {
            $suggestedQty = min($suggestedQty, $openQuantity);

            $remainderQty   = $openQuantity - $suggestedQty;
            $remainderValue = $remainderQty * $limitPrice;

            if ($remainderQty > 0 && $remainderValue < $targetInTrade) {
                $suggestedQty = $openQuantity; // sell all — remainder too small
            }

            $isPartialSell = $suggestedQty < $openQuantity;
        }

        return [
            'action'          => $action,
            'limit_price'     => $limitPrice,
            'weak_signal'     => $weakSignal,
            'suggested_qty'   => $suggestedQty,
            'is_partial_sell' => $isPartialSell,
            'open_quantity'   => $openQuantity,
            'pct_below_high'  => round($pctBelowHigh, 1),
            'pct_above_low'   => round($pctAboveLow, 1),
        ];
    }

    private function _targetInTradeCurrency(
        string $accountCurrency,
        string $tradeCurrency,
        float $eurRate
    ): float
    {
        if ($accountCurrency === $tradeCurrency) {
            return self::TARGET_AMOUNT;
        }
        if ($accountCurrency === 'EUR') {
            return self::TARGET_AMOUNT * $eurRate; // e.g. 5000 EUR → USD
        }
        if ($accountCurrency === 'USD' && $tradeCurrency === 'EUR') {
            return self::TARGET_AMOUNT / $eurRate; // e.g. 5000 USD → EUR
        }
        return self::TARGET_AMOUNT;
    }
}
