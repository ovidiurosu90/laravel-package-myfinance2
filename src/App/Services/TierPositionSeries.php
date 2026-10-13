<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * B. "Your holdings" of the tier history modal, one holding period at a time (a period runs from the buy
 * that opens a position to the sell that closes it), against the same trades made in VUSA.AS
 * (BenchmarkMirror: same amounts in, same share of the position out, same days). The gap isolates the
 * choice of stock from your timing and sizing.
 *
 * Both returns follow the tier's rule (raw under a year, annualized from a year), so they are coloured by
 * the tier they fall in, like the tier chart:
 * - points: on each stored day you held shares, the holding's return and the mirror's;
 * - periods: every holding period (including those before the stored history), and an "all periods" row
 *   weighted like your overall return (capital times time held per period).
 */
final class TierPositionSeries
{
    private TierCalculationService $_tiers;

    public function __construct()
    {
        $this->_tiers = new TierCalculationService();
    }

    /**
     * @param array $history  date => ['h' => holding return, 's' => holding start, ...] (TierHistoryRepository)
     * @param array $holdings ['trades', 'windows', 'lifetime_pct', 'benchmark'] (TierHistoryController)
     *
     * @return array|null null when you never held the symbol
     */
    public function build(array $history, array $holdings): ?array
    {
        $windows = $holdings['windows'] ?? [];
        if (empty($windows)) {
            return null;
        }
        $mirror = new BenchmarkMirror($holdings['benchmark'] ?? []);

        return [
            'points'  => $this->_points($history, $windows, $mirror),
            'periods' => array_map(fn ($w) => $this->_periodRow($w, $mirror), $windows),
            'total'   => $this->_totalRow($windows, $holdings['lifetime_pct'] ?? null, $mirror),
        ];
    }

    private function _points(array $history, array $windows, BenchmarkMirror $mirror): array
    {
        $byStart = array_column($windows, null, 'from');
        $points  = [];
        foreach ($history as $date => $row) {
            $since  = $row['s'] ?? null;
            $window = $since !== null ? ($byStart[$since] ?? null) : null;
            if (($row['h'] ?? null) === null || $window === null) {
                $points[] = ['time' => $date, 'value' => null];
                continue;
            }
            $days  = (int) Carbon::parse($since)->diffInDays(Carbon::parse($date));
            $raw   = $mirror->returnOn($window['trades'], $date);
            $bench = $raw !== null ? $this->_rate($raw, $days) : null;

            $points[] = array_merge(
                ['time' => $date, 'since_label' => Carbon::parse($since)->format('d M Y')],
                $this->_tierFields('', $this->_rate($row['h'], $days)),
                $this->_tierFields('bench_', $bench)
            );
        }

        return $points;
    }

    /**
     * value, tier label and colour of a return, under $prefix (e.g. "bench_value").
     */
    private function _tierFields(string $prefix, ?float $value): array
    {
        $tier = $this->_tiers->getTier($value);

        return [
            $prefix . 'value' => $value !== null ? round($value, 2) : null,
            $prefix . 'label' => TierCalculationService::tierLabel($tier),
            $prefix . 'color' => TierCalculationService::tierColor($tier),
        ];
    }

    private function _periodRow(array $w, BenchmarkMirror $mirror): array
    {
        $bench = $mirror->returnOn($w['trades'], $this->_end($w));
        $yours = $w['return_pct'];

        return [
            'from'          => Carbon::parse($w['from'])->format('d M Y'),
            'to'            => $w['open'] ? 'still held' : Carbon::parse($w['to'])->format('d M Y'),
            'days'          => $w['days'],
            'yours'         => $this->_signed($yours),
            'bench'         => $this->_signed($bench),
            'diff'          => ($bench !== null && $yours !== null) ? $this->_pts($yours - $bench) : 'n/a',
            'diff_positive' => $bench !== null && $yours !== null && $yours >= $bench,
        ];
    }

    /**
     * All periods together, weighted like your overall return: each period's rate (raw under a year,
     * annualized from a year) weighted by the capital invested times the years it was held.
     */
    private function _totalRow(array $windows, ?float $lifetime, BenchmarkMirror $mirror): ?array
    {
        $weights = 0.0;
        $sum     = 0.0;
        $days    = 0;
        foreach ($windows as $w) {
            $raw = $mirror->returnOn($w['trades'], $this->_end($w));
            if ($raw === null) {
                return null;
            }
            $rate     = $this->_rate($raw, $w['days']);
            $weight   = $w['invested'] * $w['days'] / 365.0;
            $sum     += $rate * $weight;
            $weights += $weight;
            $days    += $w['days'];
        }
        if ($lifetime === null || $weights <= 0.0) {
            return null;
        }
        // A single period is its own rate, as in SymbolPerformanceService.
        $bench   = count($windows) === 1 ? $rate : $sum / $weights;
        $perYear = $days >= SymbolPerformanceService::MIN_CAGR_DAYS ? '/y' : '';

        return [
            'from'          => Carbon::parse($windows[0]['from'])->format('d M Y'),
            'yours'         => $this->_signed($lifetime) . $perYear,
            'bench'         => $this->_signed($bench) . $perYear,
            'diff'          => $this->_pts($lifetime - $bench) . $perYear,
            'diff_positive' => $lifetime >= $bench,
        ];
    }

    private function _end(array $window): string
    {
        return $window['to'] ?? Carbon::today()->format('Y-m-d');
    }

    /**
     * The tier's rule for a holding's return: raw under a year, annualized (CAGR) from a year.
     */
    private function _rate(float $rawPct, int $days): float
    {
        return $days >= SymbolPerformanceService::MIN_CAGR_DAYS
            ? (pow(max(0.0, 1.0 + $rawPct / 100.0), 365.0 / $days) - 1.0) * 100.0
            : $rawPct;
    }

    private function _pts(float $diff): string
    {
        return rtrim($this->_signed($diff), '%') . ' pts';
    }

    private function _signed(?float $pct): string
    {
        if ($pct === null) {
            return 'n/a';
        }

        return ($pct >= 0.0 ? '+' : '-') . MoneyFormat::get_formatted_pct(abs($pct)) . '%';
    }
}
