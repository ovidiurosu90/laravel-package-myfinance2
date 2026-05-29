<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Presentation strings for the per-year "returns" tooltips, the money-weighted XIRR and the alpha
 * versus the VUSA.AS benchmark, shared by the tier card and the portfolio-health gain cell. The
 * wording and the short-period caveats live here instead of being rebuilt in each Blade @php block,
 * so the two views stay consistent and carry no string-assembly logic.
 */
class ReturnsTooltips
{
    /**
     * Label tooltip for the per-year "Money-weighted:" line. Summarizes that both figures on the line
     * reflect the user's actual holdings (the XIRR money-weighted return and the alpha versus VUSA.AS),
     * in contrast to the market-only quadrant table shown below it.
     */
    public static function moneyWeightedLabel(): string
    {
        return 'Your actual holdings, per year. XIRR is your money-weighted return, crediting the timing '
            . 'and size of every buy, sell and dividend; the VUSA.AS figure is how that return compares '
            . 'to the benchmark over the same dates you held the position. Unlike the market-only '
            . 'quadrant table below, these reflect your real positions.';
    }

    /**
     * XIRR tooltip. Adds a provisional caveat when the position has been held under a year, where
     * the annualized rate extrapolates a sub-year window.
     */
    public static function xirr(bool $isShort): string
    {
        $base = 'XIRR (money-weighted return): how your actual euros performed, crediting the timing '
            . 'and size of every buy, sell and dividend. The CAGR figures shown are time-weighted '
            . '(the asset\'s performance while held, the figure comparable to an index); the two '
            . 'diverge when capital was added or trimmed at different times.';

        if (!$isShort) {
            return $base;
        }

        return $base . ' Held under a year, this rate annualizes a sub-year window and is provisional; '
            . 'it firms up once the position has been held a full year.';
    }

    /**
     * Reason shown when XIRR is withheld: held under the annualization floor, or unsolvable.
     */
    public static function xirrNa(int $totalDays, int $minDays): string
    {
        if ($totalDays > 0 && $totalDays < $minDays) {
            return 'XIRR is an annualized money-weighted return; it is withheld for positions held under '
                . $minDays . ' days, where annualizing such a short window would produce a meaningless '
                . 'figure. It appears once the position has been held at least ' . $minDays . ' days.';
        }

        return 'XIRR could not be solved from this position\'s cash flows.';
    }

    /**
     * Alpha versus VUSA.AS tooltip. Under a year both sides are raw (non-annualized) returns over the
     * same dates and the figure is provisional; from a year both sides are CAGRs (apples-to-apples).
     */
    public static function alpha(bool $isShort, ?float $vusaPct, ?float $vusaRawPct): string
    {
        if ($isShort) {
            return 'Short period (under 1 year): your raw return minus VUSA.AS raw return over the same '
                . 'dates you held this position (VUSA returned '
                . MoneyFormat::get_formatted_pct($vusaRawPct) . '% over that span). Not annualized yet, '
                . 'so treat it as provisional; it becomes a CAGR comparison once held a full year.';
        }

        return 'Your CAGR minus the VUSA.AS CAGR over the SAME dates you held this position (VUSA '
            . 'returned ' . MoneyFormat::get_formatted_pct($vusaPct) . '%/y over that span). Positive '
            . 'means you beat the index; both sides are CAGRs over identical dates, so it is '
            . 'apples-to-apples.';
    }
}
