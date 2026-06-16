<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Builds the quadrant chart view model (per-symbol rows + 1Y quadrant counts) from the
 * dashboard items. Kept in the BE so the chart markup partial and its script partial share
 * one computation instead of each rebuilding it, and so the blade stays presentation-only.
 */
class QuadrantChartBuilder
{
    /**
     * @param array $items symbol => dashboard quoteData (categorization, performance, positions)
     * @return array ['symbols' => row[], 'init_counts' => quadrant => ['total' => int, 'owned' => int]]
     */
    public function build(array $items): array
    {
        $symbols = [];
        foreach ($items as $symbol => $qd) {
            $row = $this->_buildSymbolRow($symbol, $qd);
            if ($row !== null) {
                $symbols[] = $row;
            }
        }

        return [
            'symbols'     => $symbols,
            'init_counts' => $this->_buildInitCounts($symbols),
        ];
    }

    private function _buildSymbolRow(string $symbol, array $qd): ?array
    {
        $cat     = $qd['categorization'] ?? null;
        $relDd   = $cat['relative_drawdown'] ?? null;
        $momenta = $cat['momenta'] ?? null;

        if ($relDd === null) {
            return null;
        }

        $isOwned   = !empty($qd['open_positions']);
        $ownedEver = !empty(($qd['performance'] ?? [])['has_data']);
        $isExited  = !$isOwned && empty($qd['is_on_watchlist']);

        $openGainPct      = null;
        $openIsAnnualized = false;
        $openDays         = null;
        $overallGainPct   = null;
        $overallIsAnn     = false;
        $overallPeriodDisplay = null;
        if ($ownedEver) {
            $perf = $qd['performance'] ?? [];

            $rawOverall = $perf['annualized_percentage_gain'] ?? null;
            if ($rawOverall !== null) {
                $overallGainPct = round((float) $rawOverall, 2);
                $overallIsAnn   = true;
            } else {
                $rawOverall = $perf['percentage_gain'] ?? null;
                $overallGainPct = $rawOverall !== null ? round((float) $rawOverall, 2) : null;
            }
            $rawPeriod = $perf['holding_period_display'] ?? null;
            $overallPeriodDisplay = $rawPeriod !== null
                ? str_replace([' (open)', ', open)'], ['', ')'], $rawPeriod)
                : null;

            if ($isOwned) {
                [$openGainPct, $openIsAnnualized, $openDays] = $this->_resolveOpenGain($perf);
            }
        }

        return [
            'symbol'               => $symbol,
            'isBenchmark'          => $cat['is_benchmark'] ?? false,
            'isOwned'              => $isOwned,
            'ownedEver'            => $ownedEver,
            'isExited'             => $isExited,
            'relDd'                => round($relDd, 2),
            'openGainPct'          => $openGainPct,
            'openIsAnn'            => $openIsAnnualized,
            'openDays'             => $openDays,
            'overallGainPct'       => $overallGainPct,
            'overallIsAnn'         => $overallIsAnn,
            'overallPeriodDisplay' => $overallPeriodDisplay,
            'periods'              => $this->_buildPeriods($momenta, $relDd),
        ];
    }

    /**
     * @return array{0: ?float, 1: bool, 2: ?int} [openGainPct, isAnnualized, openDays]
     */
    private function _resolveOpenGain(array $perf): array
    {
        $openInvestedEur = 0.0;
        $openGainEur     = 0.0;
        $openAnnEur      = 0.0;
        $totalOpenDays   = 0;
        $hasAnn          = true;
        foreach ($perf['windows'] ?? [] as $w) {
            if (!$w['is_open']) {
                continue;
            }
            $openInvestedEur += $w['invested_eur'];
            $openGainEur     += $w['total_gain_eur'];
            $totalOpenDays    = max($totalOpenDays, $w['duration_days']);
            if ($w['annualized_percentage_gain'] === null) {
                $hasAnn = false;
            } else {
                $openAnnEur += $w['annualized_gain_eur'] ?? 0.0;
            }
        }

        if ($openInvestedEur <= 0) {
            return [null, false, null];
        }
        if ($hasAnn) {
            return [round($openAnnEur / $openInvestedEur * 100, 2), true, $totalOpenDays];
        }
        return [round($openGainEur / $openInvestedEur * 100, 2), false, $totalOpenDays];
    }

    private function _buildPeriods(?array $momenta, float $relDd): array
    {
        $periods = [];
        foreach (['3m', '6m', '1y', '2y'] as $p) {
            $pAnn      = $momenta !== null ? ($momenta[$p] ?? null) : null;
            $pQuadrant = $pAnn !== null ? QuadrantClassifier::classify($pAnn, $relDd) : null;
            $periods[$p] = ['ann' => $pAnn !== null ? round($pAnn, 2) : null, 'quadrant' => $pQuadrant];
        }
        return $periods;
    }

    private function _buildInitCounts(array $symbols): array
    {
        $initCounts = [
            QuadrantClassifier::STEADY_GROWERS   => ['total' => 0, 'owned' => 0],
            QuadrantClassifier::VOLATILE_WINNERS => ['total' => 0, 'owned' => 0],
            QuadrantClassifier::DEAD_WEIGHT      => ['total' => 0, 'owned' => 0],
            QuadrantClassifier::DANGER_ZONE      => ['total' => 0, 'owned' => 0],
        ];
        foreach ($symbols as $sym) {
            $pd = $sym['periods']['1y'] ?? null;
            if (!$pd || !$pd['quadrant']) {
                continue;
            }
            $initCounts[$pd['quadrant']]['total']++;
            if ($sym['isOwned']) {
                $initCounts[$pd['quadrant']]['owned']++;
            }
        }
        return $initCounts;
    }
}
