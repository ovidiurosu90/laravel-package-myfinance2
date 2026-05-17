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
     *     windows: array<int, array{label: string, star: string, gain: string, peak: string}>
     * }
     */
    public static function build(array $symbolPerf, string $tradeCurrencyCode): array
    {
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
            [
                'windows' => self::_windowTooltips(
                    $symbolPerf['windows'],
                    $symbolPerf['best_window_index'],
                    $isNonEur,
                    $tradeCurrencyCode
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
            'windows' => [],
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
            'open_annualized_short'   => !empty($openWin['annualized_gain_short_window'])
                ? 'Annualized gain is not shown for positions held less than 30 days:'
                    . ' extrapolating a short-term gain over a full year would produce a misleading figure.'
                : '',
            'open_annualized'         => ($openWin['annualized_gain_eur'] !== null
                    && empty($openWin['annualized_gain_short_window']))
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

    private static function _annualizedTooltip(float $totalGainEur, int $durationDays, bool $isOverall = false): string
    {
        $years     = round($durationDays / 365.25, 1);
        $formatted = MoneyFormat::get_formatted_number_plain($totalGainEur, 0);
        $suffix    = $isOverall ? ' across all windows' : '';
        return "Annualized gain: {$formatted} EUR total gain divided by {$years} years of holding{$suffix}."
            . ' Simple linear annualization (total gain / years held).';
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
            'overall_annualized_short' => !empty($symbolPerf['annualized_gain_short_window'])
                ? 'Annualized gain is not shown for positions held less than 30 days:'
                    . ' extrapolating a short-term gain over a full year would produce a misleading figure.'
                : '',
            'overall_annualized'      => ($symbolPerf['annualized_gain_eur'] !== null
                    && empty($symbolPerf['annualized_gain_short_window']))
                ? self::_annualizedTooltip(
                    (float) $symbolPerf['total_gain_eur'],
                    (int) $symbolPerf['total_days'],
                    true
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
        string $currencyCode
    ): array {
        $result = [];
        foreach ($windows as $win) {
            $idx          = $win['index'];
            $peakDate     = !empty($win['peak_gain_date'])
                ? ', best exit date: ' . $win['peak_gain_date']->format('d M Y')
                : '';
            $result[$idx] = [
                'label' => "Position window {$idx}: a continuous holding period that starts"
                    . ' with your first purchase and ends when you fully sell out.'
                    . ' A new window opens the next time you buy back in.',
                'star'  => $idx === $bestIndex
                    ? 'Best window: highest annualized return among completed positions.'
                    : '',
                'gain'  => $isNonEur ? self::_winGainTooltip($win, $currencyCode) : '',
                'peak'  => $win['peak_gain_eur'] !== null
                    ? 'Peak paper gain during this window based on historical prices' . $peakDate
                    : '',
            ];
        }
        return $result;
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
