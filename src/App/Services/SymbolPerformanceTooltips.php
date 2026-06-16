<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

class SymbolPerformanceTooltips
{
    /**
     * Build the full tooltips array for a symbol's performance data.
     * Empty string means no tooltip should be rendered for that slot.
     *
     * @return array{
     *     open_cost: string, open_dividends: string, open_gain: string,
     *     open_gain_big: bool, open_annualized_short: string, open_annualized: string,
     *     open_holding_period: string, open_projected_dividend: string,
     *     overall_cost: string, overall_dividends: string, overall_gain: string,
     *     overall_annualized_short: string, overall_annualized: string,
     *     overall_holding_period: string, overall_fees: string,
     *     gain_split: string, win_rate: string, time_pattern: string,
     *     xirr_short: bool, open_money: string, overall_money: string,
     *     windows: array<int, array{label: string, star: string, gain: string, peak: string}>
     * }
     */
    public static function build(
        array $symbolPerf,
        string $tradeCurrencyCode,
        string $tradeCurrencyDisplayCode = ''
    ): array {
        if (empty($symbolPerf['has_data'])) {
            return self::_emptyDefaults();
        }

        $isNonEur = $tradeCurrencyCode !== 'EUR';
        $openWin  = null;
        foreach ($symbolPerf['windows'] as $w) {
            if ($w['is_open']) {
                $openWin = $w;
                break;
            }
        }

        return array_merge(
            self::_openTooltips($openWin, $isNonEur, $tradeCurrencyCode),
            self::_overallTooltips($symbolPerf),
            self::_metricsTooltips(),
            self::_moneyTooltips($symbolPerf),
            [
                'windows' => self::_windowTooltips(
                    $symbolPerf['windows'],
                    $symbolPerf['best_window_index'],
                    $isNonEur,
                    $tradeCurrencyCode,
                    $tradeCurrencyDisplayCode
                ),
            ],
        );
    }

    private static function _emptyDefaults(): array
    {
        return [
            'open_cost' => '', 'open_dividends' => '', 'open_gain' => '',
            'open_gain_big' => false, 'open_annualized_short' => '',
            'open_annualized' => '', 'open_holding_period' => '',
            'open_projected_dividend' => '',
            'overall_cost' => '', 'overall_dividends' => '', 'overall_gain' => '',
            'overall_annualized_short' => '', 'overall_annualized' => '',
            'overall_holding_period' => '', 'overall_fees' => '',
            'gain_split' => '', 'win_rate' => '', 'time_pattern' => '',
            'xirr_short' => false, 'open_money' => '', 'overall_money' => '',
            'windows' => [],
        ];
    }

    /**
     * Tooltips and the short-period flag for the money-weighted XIRR (the "money/y" badge),
     * built in the BE so the view never has to reach for SymbolPerformanceService constants
     * or assemble these strings inline. xirr_short marks a sub-year hold whose annualized rate
     * is provisional; the open/overall strings cover the value, provisional and n/a cases.
     */
    private static function _moneyTooltips(array $symbolPerf): array
    {
        $xirr      = $symbolPerf['xirr_pct'] ?? null;
        $totalDays = (int) ($symbolPerf['total_days'] ?? 0);
        $short     = $totalDays < SymbolPerformanceService::MIN_CAGR_DAYS;

        $naReason = 'Money-weighted return (XIRR) is withheld for positions held under '
            . SymbolPerformanceService::MIN_ANNUALIZED_DAYS . ' days, where annualizing such a short '
            . 'window is meaningless, or when it cannot be solved from the cash flows.';
        $provisional = ' Held under a year, this rate annualizes a sub-year window and is provisional;'
            . ' it firms up once the position has been held a full year.';

        $openBase = 'Money-weighted annualized return (XIRR): how your actual euros did, crediting the'
            . ' timing and size of every buy, sell and dividend. The gain/y above is the time-weighted'
            . ' CAGR (the asset\'s performance while held), the figure comparable to an index; XIRR'
            . ' answers the separate question of how your money did.';
        $overallBase = 'Money-weighted annualized return (XIRR) across all windows: how your actual'
            . ' euros did, crediting the timing and size of every buy, sell and dividend. The gain/y'
            . ' above is the time-weighted CAGR (asset performance while held), the figure comparable'
            . ' to an index; the two diverge when capital was added or trimmed at different times.';

        $compose = static function (string $base) use ($xirr, $short, $naReason, $provisional): string
        {
            if ($xirr === null) {
                return $naReason;
            }
            return $short ? $base . $provisional : $base;
        };

        return [
            'xirr_short'    => $short,
            'open_money'    => $compose($openBase),
            'overall_money' => $compose($overallBase),
        ];
    }

    private static function _openTooltips(?array $openWin, bool $isNonEur, string $currencyCode): array
    {
        if ($openWin === null) {
            return [
                'open_cost' => '', 'open_dividends' => '', 'open_gain' => '',
                'open_gain_big' => false, 'open_annualized_short' => '',
                'open_annualized' => '', 'open_holding_period' => '',
                'open_projected_dividend' => '',
            ];
        }

        $hasPartialSells = abs($openWin['realized_gain_eur'] ?? 0) > 0.01;
        $hasDividends    = ($openWin['dividends_eur'] ?? 0) > 0.01;

        return [
            'open_cost'               => 'Current cost basis: amount still invested in the open position',
            'open_dividends'          => $hasDividends
                ? 'Dividends received during this position window. Already included in the gain figure.'
                : '',
            'open_gain'               => self::_openGainTooltip(
                $hasPartialSells, $hasDividends, $isNonEur, $currencyCode
            ),
            'open_gain_big'           => $hasPartialSells || $hasDividends || $isNonEur,
            'open_annualized_short'   => ($openWin['annualized_percentage_gain'] === null)
                ? self::_shortAnnualizedTooltip((int) $openWin['duration_days'])
                : '',
            'open_annualized'         => ($openWin['annualized_percentage_gain'] !== null)
                ? self::_annualizedTooltip((float) $openWin['total_gain_eur'], (int) $openWin['duration_days'])
                : '',
            'open_holding_period'     => 'Holding period of the current open position',
            'open_projected_dividend' => 'Estimated annual dividend income based on dividends received'
                . ' in the last 12 months. Dividends paid to date are already included in the gain figures shown.',
        ];
    }

    private static function _openGainTooltip(
        bool $hasPartialSells,
        bool $hasDividends,
        bool $isNonEur,
        string $currencyCode
    ): string {
        if (!$hasPartialSells && !$hasDividends) {
            $tooltip = 'Unrealized gain on the current open position.';
        } else {
            $parts = ['unrealized gain'];
            if ($hasPartialSells) {
                $parts[] = 'realized gain from partial sells';
            }
            if ($hasDividends) {
                $parts[] = 'dividends';
            }
            $tooltip  = 'Total return on the current open position: ' . implode(', ', $parts) . '.';
            $excluded = array_values(array_filter([
                $hasPartialSells ? 'realized gains' : null,
                $hasDividends    ? 'dividends'      : null,
            ]));
            $tooltip .= " The Overview's Unrealized Gain excludes " . implode(' and ', $excluded) . '.';
        }

        if ($isNonEur) {
            $tooltip .= ' The EUR cost basis reflects the EUR/' . $currencyCode
                . ' rate at each purchase date (the actual euros spent),'
                . ' while the current value uses today\'s rate. This means the EUR gain'
                . ' captures both the ' . $currencyCode . ' price move and any EUR/'
                . $currencyCode . ' currency shift since you bought,'
                . ' so the EUR% here can differ from the Overview\'s '
                . $currencyCode . '% (which ignores currency effects).';
        }

        return $tooltip;
    }

    /**
     * Explains why a per-year CAGR is withheld for a sub-year hold and what is shown instead.
     * Used wherever the gain/y figure is null because the position has not been held a full year.
     */
    private static function _shortAnnualizedTooltip(int $durationDays): string
    {
        $years = round($durationDays / 365.0, 1);

        return "Annualized return (CAGR) appears once a position has been held a full year"
            . " (this one: {$durationDays} days, about {$years}y). Compounding a sub-year return"
            . ' would extrapolate and inflate the yearly rate, so the raw cumulative gain shown in'
            . ' the gain badge is used instead. A CAGR is shown automatically once the holding'
            . ' period reaches one year.';
    }

    private static function _annualizedTooltip(float $totalGainEur, int $durationDays, int $windowCount = 1): string
    {
        $years     = round($durationDays / 365.0, 1);
        $formatted = MoneyFormat::get_formatted_number_plain($totalGainEur, 0);

        if ($windowCount > 1) {
            // Multi-window: blended total return annualized over the time held. The windows are
            // NOT geometrically chained (that would compound separate buy/sell episodes that were
            // never reinvested and inflate the rate); instead total profit over all capital
            // deployed is CAGR-annualized over the days actually held (gaps excluded).
            return "Annualized return (CAGR) across {$windowCount} holding windows"
                . " ({$years} years held, total gain: {$formatted} EUR)."
                . ' Total profit relative to all capital deployed, annualized over the time you'
                . ' actually held the position (gaps when you held nothing are excluded). The'
                . ' windows are not compounded together, since each was funded by fresh capital.'
                . ' The money-weighted view that accounts for your euro timing is the separate'
                . ' XIRR figure.';
        }

        // Single window: CAGR, the steady compound yearly rate that reproduces the period return.
        return "Annualized return (CAGR) over {$years} years (total gain: {$formatted} EUR)."
            . ' The steady yearly rate that, compounded, reproduces the actual total return:'
            . ' (1 + total return)^(1/years) - 1. Directly comparable to an index CAGR.';
    }

    private static function _overallTooltips(array $symbolPerf): array
    {
        $hasDividends = ($symbolPerf['total_dividends_eur'] ?? 0) > 0.01;

        return [
            'overall_cost'            => 'Total capital ever deployed in this symbol across all windows',
            'overall_dividends'       => $hasDividends
                ? 'Total dividends received across all windows. Already included in the gain figures above.'
                : '',
            'overall_gain'            => 'All-time total gain across all windows: realized + unrealized'
                . ($hasDividends ? ' + dividends' : ''),
            'overall_annualized_short' => ($symbolPerf['annualized_percentage_gain'] === null)
                ? self::_shortAnnualizedTooltip((int) $symbolPerf['total_days'])
                : '',
            'overall_annualized'      => ($symbolPerf['annualized_percentage_gain'] !== null)
                ? self::_annualizedTooltip(
                    (float) $symbolPerf['total_gain_eur'],
                    (int) $symbolPerf['total_days'],
                    (int) ($symbolPerf['window_count'] ?? 1)
                )
                : '',
            'overall_holding_period'  => 'Total holding period across all position windows',
            'overall_fees'            => ($symbolPerf['fees_eur'] ?? 0) > 50
                ? self::_feesTooltip($symbolPerf)
                : '',
        ];
    }

    private static function _feesTooltip(array $symbolPerf): string
    {
        $tooltip = 'Total fees paid across all trades and dividends for this symbol';
        if ($symbolPerf['fees_pct_of_gain'] !== null && $symbolPerf['fees_pct_of_gain'] > 5) {
            $pct      = MoneyFormat::get_formatted_number_plain($symbolPerf['fees_pct_of_gain'], 1);
            $tooltip .= " ({$pct}% of gain)";
        }
        return $tooltip . '. Already incorporated in the cost and gain figures shown.';
    }

    private static function _metricsTooltips(): array
    {
        return [
            'gain_split'   => 'How much of the total gain came from dividends vs. capital gains',
            'win_rate'     => 'Of all completed windows, how many were profitable',
            'time_pattern' => 'The calendar quarter with the most sell trades across all completed windows'
                . ' for this symbol. Indicates a seasonal exit pattern.',
        ];
    }

    /**
     * @param array $windows        All position windows for the symbol.
     * @param int|null $bestIndex   Index of the best-performing closed window.
     * @return array<int, array{label: string, star: string, gain: string, peak: string}>
     */
    private static function _windowTooltips(
        array $windows,
        ?int $bestIndex,
        bool $isNonEur,
        string $currencyCode,
        string $displayCode = ''
    ): array {
        $result = [];
        foreach ($windows as $win) {
            $idx          = $win['index'];
            $peakLabel    = self::_peakLabel(
                $win['peak_price_native'] ?? null,
                $win['peak_price_eur'] ?? null,
                $isNonEur,
                $displayCode
            );
            $peakValue    = $peakLabel !== '' ? ', peak ' . $peakLabel : '';
            $peakDate     = !empty($win['peak_gain_date'])
                ? ', best exit date: ' . $win['peak_gain_date']->format('d M Y')
                : '';
            $result[$idx] = [
                'label' => "Position window {$idx}: a continuous holding period that starts"
                    . ' with your first purchase and ends when you fully sell out.'
                    . ' A new window opens the next time you buy back in.',
                'star'  => $idx === $bestIndex
                    ? 'Best window: highest annualized return (CAGR) among completed positions.'
                    : '',
                'gain'  => $isNonEur ? self::_winGainTooltip($win, $currencyCode) : '',
                'peak'  => $win['peak_gain_eur'] !== null
                    ? 'Peak paper gain during this window based on historical prices'
                        . $peakValue . $peakDate
                    : '',
            ];
        }
        return $result;
    }

    /**
     * The window peak in its native trade currency first, then the EUR equivalent in parentheses;
     * EUR-quoted symbols (or those without a native figure) show only the EUR value. Mirrors the
     * quadrant's peak label (WatchlistTableMetaBuilder::_peakLabel) so both tooltips read the same.
     */
    private static function _peakLabel(
        ?float $peakNative,
        ?float $peakEur,
        bool $isNonEur,
        string $displayCode
    ): string {
        if ($peakEur === null) {
            return '';
        }

        $eur = MoneyFormat::get_formatted_price_plain($peakEur) . '&euro;';

        if ($isNonEur && $displayCode !== '' && $peakNative !== null) {
            return MoneyFormat::get_formatted_price_plain($peakNative) . $displayCode
                . ' (' . $eur . ')';
        }

        return $eur;
    }

    private static function _winGainTooltip(array $win, string $currencyCode): string
    {
        if ($win['is_open']) {
            return 'The EUR cost basis reflects the EUR/' . $currencyCode
                . ' rate at each purchase date (the actual euros spent),'
                . ' while the current value uses today\'s rate. This means the EUR gain'
                . ' captures both the ' . $currencyCode . ' price move and any EUR/'
                . $currencyCode . ' currency shift since you bought,'
                . ' so the EUR% here can differ from the Overview\'s '
                . $currencyCode . '% (which ignores currency effects).';
        }
        return 'Both buy and sell costs use the EUR/' . $currencyCode
            . ' rate recorded at each trade date, so the EUR gain reflects'
            . ' the actual euros spent and received, including the currency effect.';
    }
}
