<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * Reproduces, for a past trading day D, the tier the dashboard would have shown on D: the performance
 * map as of D (SymbolPerformanceService::computeAsOf), the 1Y market return as of D
 * (DrawdownService::momentumAsOf) and the same TierClassifier rules, without hysteresis and without a
 * manual override. One code path for the backfill, the hourly cron and --verify-today, so the stored
 * history is internally consistent.
 */
final class TierAsOfClassifier
{
    private TierClassifier $_classifier;
    private SymbolPerformanceService $_performance;

    public function __construct()
    {
        $this->_classifier  = TierClassifier::fromConfig(new TierCalculationService());
        $this->_performance = new SymbolPerformanceService();
    }

    /**
     * @return array{rows: array<string, array>, skipped: array<string, string>}
     *         rows: symbol => [basis, basis_value, market_value, own_value, holding_value, holding_since,
     *         tier, is_owned]; unrated
     *         symbols are left out.
     *         skipped: symbol => reason, for symbols held on D without a price on or before D.
     */
    public function classifyDay(PerformanceDataset $data, Carbon $day, ?string $onlySymbol = null): array
    {
        $performance = $this->_performance->computeAsOf($day, $data);
        $symbols     = array_values(array_unique(array_merge(
            array_keys($performance),
            $data->watchlistSymbols()
        )));

        $rows    = [];
        $skipped = [];
        foreach ($symbols as $symbol) {
            if (($onlySymbol !== null && $symbol !== $onlySymbol) || UnlistedSymbol::isUnlisted($symbol)) {
                continue;
            }
            $perf = $performance[$symbol] ?? ['has_data' => false];
            if ($this->_isHeld($perf) && $data->priceOnOrBefore($symbol, $day) === null) {
                // Held on D but no close on or before D (e.g. prices wiped by a split and not yet
                // refetched): the return would ignore the open position, so the day is not stored.
                $skipped[$symbol] = 'no price on or before ' . $day->format('Y-m-d');
                continue;
            }
            if (!$data->hasPriceOn($symbol, $day)) {
                // Not a trading day for this symbol's exchange (holiday): nothing to record.
                continue;
            }

            $market   = DrawdownService::momentumAsOf($data->momentumSeries($symbol), $day->format('Y-m-d'));
            $inputs   = TierInputs::fromData($symbol, $perf, ['momentum_annualized_pct' => $market], null);
            $decision = $this->_classifier->classify($inputs, null);
            if ($decision->basis === TierDecision::BASIS_NONE) {
                continue;
            }

            $candidates    = $this->_classifier->candidateValues($inputs);
            $holding       = $this->_openWindow($perf);
            $rows[$symbol] = [
                'basis'        => $decision->basis,
                'basis_value'  => $this->_round($decision->basisValue),
                'market_value' => $this->_round($candidates['market']),
                'own_value'    => $this->_round($candidates['own']),
                'holding_value' => $this->_round($holding['percentage_gain'] ?? null),
                'holding_since' => isset($holding['start_date']) ? $holding['start_date']->format('Y-m-d') : null,
                'tier'         => $decision->computedTier,
                'is_owned'     => $decision->isOwned,
            ];
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    private function _round(?float $value): ?float
    {
        return $value !== null ? round($value, 4) : null;
    }

    private function _isHeld(array $perf): bool
    {
        return $this->_openWindow($perf) !== null;
    }

    /**
     * The holding period still open on the day (shares held), or null.
     */
    private function _openWindow(array $perf): ?array
    {
        foreach ($perf['windows'] ?? [] as $window) {
            if (!empty($window['is_open'])) {
                return $window;
            }
        }

        return null;
    }
}
