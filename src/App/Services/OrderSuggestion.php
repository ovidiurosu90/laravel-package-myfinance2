<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The prefill figures the forms take from a symbol's quote.
 *
 * compute() guesses a side from the price's distance to the 52-week high and is still read by the alert form (alert
 * type and target, 2.5 % away) and the trade form (the "weak signal" note). The order form no longer guesses a side:
 * it asks forSide() for the figures of the side the user picked, with a limit 1 % from the price (price alerts already
 * cover the targets further away).
 */
class OrderSuggestion
{
    private const TARGET_AMOUNT          = 5000.0;
    private const PRICE_OFFSET_PCT       = 0.025;
    private const ORDER_PRICE_OFFSET_PCT = 0.01;
    private const STRONG_BUY_PCT         = 15.0;

    /**
     * Compute a suggested order based on finance data and account context.
     *
     * @param array       $financeData     Result from FinanceUtils::getFinanceDataBySymbol()
     * @param float       $openQuantity    Net open quantity for the symbol (BUY - SELL)
     * @param float       $eurRate         EUR → trade_currency exchange rate (1.0 if EUR)
     * @param string|null $accountCurrency  Account's ISO currency code; null = unknown
     * @param float|null  $availableQuantity Available qty to sell (from open trades); falls back to openQuantity
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
        ?string $accountCurrency,
        ?float $availableQuantity = null
    ): array
    {
        [$pctBelowHigh, $pctAboveLow] = $this->_distances($financeData);

        $hasStrongBuySignal = $pctBelowHigh > self::STRONG_BUY_PCT;
        $hasOpenPositions   = $openQuantity > 0;

        $action     = (!$hasStrongBuySignal && $hasOpenPositions) ? 'SELL' : 'BUY';
        $weakSignal = $action === 'BUY' && !$hasStrongBuySignal;

        $limitPrice    = self::_limitPrice($action, (float) $financeData['price'], self::PRICE_OFFSET_PCT);
        $targetInTrade = $this->_target($accountCurrency, (string) $financeData['currency'], $eurRate);
        $suggestedQty  = round($targetInTrade / $limitPrice);
        $isPartialSell = false;

        if ($action === 'SELL') {
            [$suggestedQty, $isPartialSell] = self::_sellQuantity(
                $suggestedQty, $availableQuantity ?? $openQuantity, $limitPrice, $targetInTrade
            );
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

    /**
     * The order form's figures for the side the user picked: a limit 1 % below the price for a BUY (1 % above for a
     * SELL) and a quantity worth about 5,000 in the account's currency, a SELL capped at what the account holds (and
     * all of it when the rest would be worth less than that). Weak signal: a BUY within 15 % of the 52-week high.
     *
     * @param string      $side            BUY / SELL
     * @param array       $financeData     Result from FinanceUtils::getFinanceDataBySymbol()
     * @param float       $eurRate         EUR → trade_currency exchange rate (1.0 if EUR)
     * @param string|null $accountCurrency the account's ISO currency code; null = unknown
     * @param float       $sellCap         what the account holds of the symbol (SELL only)
     *
     * @return array{limit_price: float, suggested_qty: float|null, is_partial_sell: bool, weak_signal: bool}
     */
    public function forSide(
        string $side,
        array $financeData,
        float $eurRate,
        ?string $accountCurrency,
        float $sellCap = 0.0
    ): array
    {
        [$pctBelowHigh] = $this->_distances($financeData);

        $limitPrice    = self::_limitPrice($side, (float) $financeData['price'], self::ORDER_PRICE_OFFSET_PCT);
        $targetInTrade = $this->_target($accountCurrency, (string) $financeData['currency'], $eurRate);
        $suggestedQty  = $limitPrice > 0.0 ? round($targetInTrade / $limitPrice) : null;
        $isPartialSell = false;

        if ($side === 'SELL') {
            [$suggestedQty, $isPartialSell] = $sellCap > 0.0 && $suggestedQty !== null
                ? self::_sellQuantity($suggestedQty, $sellCap, $limitPrice, $targetInTrade)
                : [null, false];
        }

        return [
            'limit_price'     => $limitPrice,
            'suggested_qty'   => $suggestedQty,
            'is_partial_sell' => $isPartialSell,
            'weak_signal'     => $side === 'BUY' && $pctBelowHigh <= self::STRONG_BUY_PCT,
        ];
    }

    /**
     * The price's distance below the 52-week high and above the 52-week low, in %. Measured against the
     * closing-based range (highest / lowest daily close over the past year), so the figures match the watchlist
     * columns and the form note text; Yahoo's intraday change percentages only when no closing range is available.
     *
     * @return array{0: float, 1: float}
     */
    private function _distances(array $financeData): array
    {
        $price       = (float) $financeData['price'];
        $closingHigh = $financeData['closingHigh'] ?? null;
        $closingLow  = $financeData['closingLow'] ?? null;

        $pctBelowHigh = ($closingHigh !== null && (float) $closingHigh > 0.0)
            ? ((float) $closingHigh - $price) / (float) $closingHigh * 100
            : -(float) ($financeData['fiftyTwoWeekHighChangePercent'] ?? 0) * 100;
        $pctAboveLow = ($closingLow !== null && (float) $closingLow > 0.0)
            ? ($price - (float) $closingLow) / (float) $closingLow * 100
            : (float) ($financeData['fiftyTwoWeekLowChangePercent'] ?? 0) * 100;

        return [$pctBelowHigh, $pctAboveLow];
    }

    private static function _limitPrice(string $side, float $price, float $offsetPct): float
    {
        return $side === 'BUY'
            ? round($price * (1 - $offsetPct), 4)
            : round($price * (1 + $offsetPct), 4);
    }

    /**
     * A SELL's quantity: the target's worth capped at what is held, and all of it when the rest would be worth less
     * than the target.
     *
     * @return array{0: float, 1: bool} the quantity, and whether it is a partial sell
     */
    private static function _sellQuantity(float $quantity, float $sellCap, float $limitPrice, float $target): array
    {
        $quantity       = min($quantity, $sellCap);
        $remainderQty   = $sellCap - $quantity;
        $remainderValue = $remainderQty * $limitPrice;

        if ($remainderQty > 0 && $remainderValue < $target) {
            $quantity = $sellCap; // sell all: the remainder is too small
        }

        return [$quantity, $quantity < $sellCap];
    }

    /**
     * The target amount (5,000 in the account's currency) in the trade currency; 5,000 in the trade currency when
     * the account is unknown.
     */
    private function _target(?string $accountCurrency, string $tradeCurrency, float $eurRate): float
    {
        if ($accountCurrency === null || $accountCurrency === $tradeCurrency) {
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
