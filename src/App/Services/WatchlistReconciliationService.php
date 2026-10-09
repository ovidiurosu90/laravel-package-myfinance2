<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Safety net for the /watchlist-symbols Portfolio Health card. Cross-checks the totals it shows
 * against each other and against the open-position rows they are built from, all in EUR and from
 * the same page load, so only rounding is tolerated:
 *
 * Check W1 (tiers): the card total (mvalue, cost) vs the sum of its tier groups.
 * Check W2 (symbols): the card total vs the sum of the rated symbol rows listed under the tiers.
 * Check W3 (portfolio): the card total plus the unrated symbols (left out of the totals by
 *   design) vs every open-position row converted to EUR at the rate /positions uses. Catches the
 *   card dropping or double counting a position, or converting a currency differently, so it
 *   keeps reconciling with the /positions User Overview.
 *
 * The rows of the card are rounded to the cent, so the sums of rows allow half a cent per row on
 * top of the tolerance.
 */
class WatchlistReconciliationService
{
    private const _METRICS = ['mvalue', 'cost'];
    private const _RATED_GROUPS = ['platinum_gold', 'silver', 'bronze_rust'];
    private const _HALF_CENT = 0.005;
    private const _SUBJECT = 'Portfolio Health';

    private float $_tolerance;

    /**
     * @param float|null $tolerance Absolute tolerance per figure; defaults to the config.
     */
    public function __construct(?float $tolerance = null)
    {
        $this->_tolerance = ($tolerance !== null
            ? new ReconciliationComparator($tolerance)
            : ReconciliationComparator::fromConfig())->tolerance();
    }

    /**
     * @param array|null $healthScore  PortfolioHealthScore::build() result, null when nothing is owned.
     * @param array $groupedItems      Open positions grouped by account id, then by symbol.
     * @param float|null $eurusd       The EURUSD rate the card converted USD with.
     * @return array<int, array<string, mixed>> One entry per failed check.
     */
    public function reconcile(?array $healthScore, array $groupedItems, ?float $eurusd): array
    {
        if (empty($healthScore) || empty($healthScore['total_mvalue_eur'])) {
            return [];
        }

        try {
            return array_values(array_filter(array_merge(
                $this->_checkTiers($healthScore),
                $this->_checkSymbols($healthScore),
                $this->_checkPortfolio($healthScore, $groupedItems, $eurusd)
            )));
        } catch (\Throwable $e) {
            Log::warning('Watchlist reconciliation failed: ' . $e->getMessage());
            return [];
        }
    }

    private function _checkTiers(array $healthScore): array
    {
        $comparator = $this->_comparator(count(self::_RATED_GROUPS));
        $issues = [];

        foreach (self::_METRICS as $metric) {
            $sum = 0.0;
            foreach (self::_RATED_GROUPS as $group) {
                $sum += (float) ($healthScore[$group . '_' . $metric . '_eur'] ?? 0);
            }
            $issues[] = $comparator->compare('tiers', self::_SUBJECT, '&euro;', $metric, $sum,
                (float) $healthScore['total_' . $metric . '_eur'], 'sum of the tiers', 'card total');
        }

        return $issues;
    }

    private function _checkSymbols(array $healthScore): array
    {
        $rows = [];
        foreach (self::_RATED_GROUPS as $group) {
            $rows = array_merge($rows, $healthScore[$group . '_symbols'] ?? []);
        }

        $comparator = $this->_comparator(count($rows));
        $issues = [];
        foreach (self::_METRICS as $metric) {
            $issues[] = $comparator->compare('symbols', self::_SUBJECT, '&euro;', $metric,
                $this->_sumRows($rows, $metric), (float) $healthScore['total_' . $metric . '_eur'],
                'sum of the symbol rows', 'card total');
        }

        return $issues;
    }

    private function _checkPortfolio(array $healthScore, array $groupedItems, ?float $eurusd): array
    {
        $portfolio = $this->_portfolioInEur($groupedItems, $eurusd);
        if ($portfolio === null) {
            return [];
        }

        $unrated = $healthScore['unrated_symbols'] ?? [];
        $comparator = $this->_comparator(1 + count($unrated));
        $issues = [];
        foreach (self::_METRICS as $metric) {
            $shown = (float) $healthScore['total_' . $metric . '_eur'] + $this->_sumRows($unrated, $metric);
            $issues[] = $comparator->compare('portfolio', self::_SUBJECT, '&euro;', $metric,
                $portfolio[$metric], $shown, 'open positions (EUR)', 'card total plus unrated');
        }

        return $issues;
    }

    /**
     * Every open-position row converted to EUR, or null when an account currency cannot be
     * converted (the comparison would then be partial, so it is skipped and logged).
     *
     * @return array{mvalue: float, cost: float}|null
     */
    private function _portfolioInEur(array $groupedItems, ?float $eurusd): ?array
    {
        $sums = ['mvalue' => 0.0, 'cost' => 0.0];

        foreach ($groupedItems as $items) {
            foreach ($items as $item) {
                $currency = $item['accountModel']->currency->iso_code ?? 'EUR';
                $factor = $this->_eurFactor($currency, $eurusd);
                if ($factor === null) {
                    Log::warning("Watchlist reconciliation: cannot convert {$currency} to EUR");
                    return null;
                }
                $sums['mvalue'] += (float) ($item['market_value_in_account_currency'] ?? 0) * $factor;
                $sums['cost'] += (float) ($item['cost2_in_account_currency'] ?? 0) * $factor;
            }
        }

        return $sums;
    }

    /**
     * Own copy of the EUR conversion, so a conversion bug in the card cannot cancel out here.
     */
    private function _eurFactor(string $currency, ?float $eurusd): ?float
    {
        if ($currency === 'EUR') {
            return 1.0;
        }

        return ($currency === 'USD' && !empty($eurusd)) ? 1.0 / $eurusd : null;
    }

    private function _sumRows(array $rows, string $metric): float
    {
        return array_sum(array_map(fn(array $row) => (float) ($row[$metric . '_eur'] ?? 0), $rows));
    }

    /**
     * Comparator allowing half a cent of rounding per rounded figure summed, on top of the tolerance.
     */
    private function _comparator(int $roundedFigures): ReconciliationComparator
    {
        return new ReconciliationComparator($this->_tolerance + self::_HALF_CENT * $roundedFigures);
    }
}
