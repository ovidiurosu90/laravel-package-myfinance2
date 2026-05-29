<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * Money-weighted annualized return (XIRR / internal rate of return) over a set of
 * dated cash flows.
 *
 * WHY THIS EXISTS (and how it differs from CAGR)
 * ----------------------------------------------
 * CAGR (computed in SymbolPerformanceService) answers "how did the asset perform
 * while I held it" and deliberately ignores when you added or removed money, so it
 * is comparable to an index. XIRR answers a different question: "how did MY actual
 * money do", crediting or penalising the timing and size of every buy, sell and
 * dividend. For a clean buy-and-hold the two nearly coincide; for buy/sell/buy/sell
 * or partial-sell histories they diverge, and XIRR is the only single annual rate
 * that correctly accounts for the gaps and the varying amounts of capital deployed.
 *
 * CONVENTION
 * ----------
 * Cash flows are from the investor's point of view, in a single currency (EUR here):
 *   - BUYs are negative (money leaving your pocket),
 *   - SELLs and dividends are positive (money returning),
 *   - for a still-open position, the current market value is added as a final
 *     positive flow dated today (as if you sold at today's price).
 *
 * XIRR is the rate r that makes the net present value of those flows zero:
 *     sum( cf_i / (1 + r)^(years_i) ) = 0
 * where years_i is the time from the earliest flow to flow i (Act/365).
 *
 * The result is returned as a percentage per year, or null when it cannot be
 * computed (fewer than two flows, no sign change, or no convergence).
 */
final class Xirr
{
    private const DAYS_PER_YEAR = 365.0;
    private const MAX_ITERATIONS = 100;
    private const TOLERANCE      = 1.0e-7;

    // Newton-Raphson can diverge for irregular flows; a guaranteed-convergence
    // bisection over this rate band is the fallback. -0.9999 avoids the (1+r)=0
    // singularity; a +1000%/yr ceiling is well beyond any realistic position.
    private const BISECTION_LOW  = -0.9999;
    private const BISECTION_HIGH = 10.0;

    /**
     * @param array<int, array{date: Carbon, amount: float}> $cashFlows
     * @return float|null  Annual rate as a percentage (e.g. 12.5 for +12.5%/yr), or null.
     */
    public function compute(array $cashFlows): ?float
    {
        if (count($cashFlows) < 2) {
            return null;
        }

        // Normalise to (yearsFromStart, amount) pairs and require both an outflow and
        // an inflow; without a sign change there is no rate that zeroes the NPV.
        usort($cashFlows, fn ($a, $b) => $a['date'] <=> $b['date']);
        $start = $cashFlows[0]['date'];

        $flows = [];
        $hasPositive = false;
        $hasNegative = false;
        foreach ($cashFlows as $cf) {
            $years = (int) $start->diffInDays($cf['date']) / self::DAYS_PER_YEAR;
            $amount = (float) $cf['amount'];
            $flows[] = ['years' => $years, 'amount' => $amount];
            if ($amount > 0.0) {
                $hasPositive = true;
            } elseif ($amount < 0.0) {
                $hasNegative = true;
            }
        }

        if (!$hasPositive || !$hasNegative) {
            return null;
        }

        $rate = $this->_newton($flows);
        if ($rate === null) {
            $rate = $this->_bisection($flows);
        }

        return $rate !== null ? $rate * 100.0 : null;
    }

    /**
     * @param array<int, array{years: float, amount: float}> $flows
     */
    private function _newton(array $flows): ?float
    {
        $rate = 0.1; // 10%/yr seed; close enough for most equity positions.

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $npv      = $this->_npv($flows, $rate);
            $deriv    = $this->_npvDerivative($flows, $rate);

            if (abs($deriv) < self::TOLERANCE) {
                return null; // flat slope: Newton cannot make progress, fall back.
            }

            $next = $rate - $npv / $deriv;

            if (!is_finite($next) || $next <= self::BISECTION_LOW) {
                return null; // stepped into the singularity, fall back.
            }

            if (abs($next - $rate) < self::TOLERANCE) {
                return $next;
            }

            $rate = $next;
        }

        return null;
    }

    /**
     * @param array<int, array{years: float, amount: float}> $flows
     */
    private function _bisection(array $flows): ?float
    {
        $low  = self::BISECTION_LOW;
        $high = self::BISECTION_HIGH;

        $npvLow  = $this->_npv($flows, $low);
        $npvHigh = $this->_npv($flows, $high);

        // The root must be bracketed (NPV changes sign across the band) for bisection.
        if ($npvLow * $npvHigh > 0.0) {
            return null;
        }

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $mid    = ($low + $high) / 2.0;
            $npvMid = $this->_npv($flows, $mid);

            if (abs($npvMid) < self::TOLERANCE || ($high - $low) / 2.0 < self::TOLERANCE) {
                return $mid;
            }

            if ($npvLow * $npvMid < 0.0) {
                $high    = $mid;
                $npvHigh = $npvMid;
            } else {
                $low    = $mid;
                $npvLow = $npvMid;
            }
        }

        return ($low + $high) / 2.0;
    }

    /**
     * @param array<int, array{years: float, amount: float}> $flows
     */
    private function _npv(array $flows, float $rate): float
    {
        $sum = 0.0;
        foreach ($flows as $flow) {
            $sum += $flow['amount'] / pow(1.0 + $rate, $flow['years']);
        }
        return $sum;
    }

    /**
     * d(NPV)/d(rate). Used by Newton-Raphson.
     *
     * @param array<int, array{years: float, amount: float}> $flows
     */
    private function _npvDerivative(array $flows, float $rate): float
    {
        $sum = 0.0;
        foreach ($flows as $flow) {
            $sum -= $flow['years'] * $flow['amount'] / pow(1.0 + $rate, $flow['years'] + 1.0);
        }
        return $sum;
    }
}
