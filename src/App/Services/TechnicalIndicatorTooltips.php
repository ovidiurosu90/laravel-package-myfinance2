<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

class TechnicalIndicatorTooltips
{
    /**
     * Build tooltip strings for the technical indicators row.
     * Empty string means no tooltip should be rendered for that slot.
     *
     * @return array{label: string, analyst: string, rsi: string, ma50: string, ma200: string}
     */
    public static function build(array $indicators): array
    {
        return [
            'label'   => self::_labelTooltip(),
            'analyst' => self::_analystTooltip($indicators),
            'rsi'     => self::_rsiTooltip(),
            'ma50'    => self::_ma50Tooltip($indicators),
            'ma200'   => self::_ma200Tooltip($indicators),
        ];
    }

    private static function _labelTooltip(): string
    {
        return 'How to read these signals together:'
            . ' MA200 is the regime filter (is the long-term trend up or down?).'
            . ' RSI is the timing signal (how fast has price moved recently?).'
            . ' Signal priority: MA200 > MA50 > RSI.'
            . ' RSI below 30 above MA200: strong buy entry, oversold correction inside an uptrend.'
            . ' RSI below 30 below MA200: caution, a short-term bounce is likely but the downtrend has not reversed.'
            . ' When signals conflict, MA200 takes precedence for sizing decisions.'
            . ' Wait for price to recover above MA200 before adding significant size in a downtrend.';
    }

    private static function _analystTooltip(array $ind): string
    {
        $count = $ind['analyst_opinions_count'] ?? null;
        $high  = $ind['analyst_target_high'] ?? null;
        $low   = $ind['analyst_target_low'] ?? null;

        $tt = 'Analyst consensus target: the average Wall Street price target for this stock.';

        if ($count) {
            $tt .= ' Based on ' . $count . ' analyst opinion' . ($count > 1 ? 's' : '') . '.';
        }
        if ($high !== null && $low !== null) {
            $tt .= ' Range: ' . MoneyFormat::get_formatted_price_plain($low)
                . ' to ' . MoneyFormat::get_formatted_price_plain($high) . '.';
        }

        $tt .= ' Positive delta: analysts see upside from current price.'
            . ' Negative delta: may be overvalued.'
            . ' Caution: analysts rarely issue Sell ratings and targets can lag news.';

        return $tt;
    }

    private static function _rsiTooltip(): string
    {
        return 'RSI-14 (Relative Strength Index): a 0-100 momentum score.'
            . ' Calculated with Wilder\'s smoothed average of daily gains vs. losses'
            . ' over the last 14 trading days, using your stored historical prices.'
            . ' Below 30: oversold, price fell fast, possible bounce or buying opportunity.'
            . ' 30-70: neutral, no momentum extreme.'
            . ' Above 70: overbought, price rose fast, avoid chasing; wait for a pullback.'
            . ' Note: in strong uptrends a stock can stay above 70 for months.';
    }

    private static function _ma50Tooltip(array $ind): string
    {
        $tt = 'MA50: the 50-day moving average closing price, sourced from Yahoo Finance.';

        if (($ind['ma50'] ?? null) !== null) {
            $tt .= ' Current MA50: ' . MoneyFormat::get_formatted_price_plain($ind['ma50']) . '.';
        }

        $tt .= ' Price above MA50: short-to-medium term uptrend intact; pullbacks toward MA50'
            . ' can be buying opportunities.'
            . ' Price below MA50: recent weakness; short-term trend broken.'
            . ' Use alongside MA200 for fuller context.';

        return $tt;
    }

    private static function _ma200Tooltip(array $ind): string
    {
        $tt = 'MA200: the 200-day moving average closing price, the primary long-term trend'
            . ' indicator in markets, sourced from Yahoo Finance.';

        if (($ind['ma200'] ?? null) !== null) {
            $tt .= ' Current MA200: ' . MoneyFormat::get_formatted_price_plain($ind['ma200']) . '.';
        }

        $tt .= ' Above MA200: long-term uptrend intact, dips are lower-risk to buy.'
            . ' Below MA200: long-term downtrend, adding carries higher risk.'
            . ' Golden Cross (MA50 crosses above MA200): classic re-entry signal.'
            . ' Death Cross (MA50 crosses below MA200): bearish regime shift.';

        return $tt;
    }
}
