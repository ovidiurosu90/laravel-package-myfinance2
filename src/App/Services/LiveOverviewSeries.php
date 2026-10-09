<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Account and User Overview series for the live /positions view, ending on the figures of this
 * page load. The stored series are the cron's snapshot (up to a minute old), while the position
 * rows use the prices of this page load; pinning the last point to the live account totals makes
 * the headers, the charts and the rows show the same numbers. The stored series still supply all
 * the earlier points. Same idea as ChartsBuilder::pinLiveQuote for the symbol charts.
 */
class LiveOverviewSeries
{
    private const _AMOUNT_METRICS = ['cash', 'change', 'cost', 'mvalue'];
    private const _CURRENCIES = ['EUR', 'USD'];

    /**
     * @return array{accounts: array, user: array, eurusd: float|null}
     *   accounts: [accountId => [metric => points]] for the accounts with a card
     *   user: [metric_CURRENCY => points], empty when the EURUSD rate is unknown
     */
    public static function build(array $groupedItems, array $accountData, int $userId,
        ?float $eurusd): array
    {
        $today = date(trans('myfinance2::general.date-format'));
        $accounts = [];
        $liveByAccount = [];

        foreach (array_keys($groupedItems) as $accountId) {
            $account = $accountData[$accountId] ?? null;
            if (empty($account['accountModel'])) {
                continue;
            }

            $live = self::accountLiveValues($account);
            $liveByAccount[$accountId] = $live;
            foreach ($live as $metric => $value) {
                $accounts[$accountId][$metric] = self::pin(
                    self::_parse(ChartsBuilder::getChartAccountAsJsonString($account, $metric)),
                    $value, $today
                );
            }
        }

        $user = [];
        if ($eurusd !== null && $eurusd > 0) {
            $userLive = self::userLiveValues($liveByAccount, $accountData, $eurusd);
            foreach ($userLive as $metric => $value) {
                $user[$metric] = self::pin(
                    self::_parse(ChartsBuilder::getChartOverviewUserAsJsonString($userId, $metric)),
                    $value, $today
                );
            }
        }

        return ['accounts' => $accounts, 'user' => $user, 'eurusd' => $eurusd];
    }

    /**
     * Live EURUSD rate of this page load: the rate the rows were converted with when a EUR
     * account holds USD positions, else the last stored rate.
     */
    public static function liveEurusd(array $exchangeRateData): ?float
    {
        $rate = $exchangeRateData['EURUSD']['exchange_rate'] ?? null;
        if (!empty($rate)) {
            return (float) $rate;
        }

        return ChartsBuilder::getLatestSymbolValue('EURUSD=X');
    }

    /**
     * Header figures of an account, in its own currency, from this page load's totals.
     */
    public static function accountLiveValues(array $account): array
    {
        $cost = (float) ($account['total_cost'] ?? 0);
        $change = (float) ($account['total_change'] ?? 0);

        return [
            'cash' => PositionsSnapshot::cashOf($account),
            'change' => $change,
            'cost' => $cost,
            'mvalue' => (float) ($account['total_market_value'] ?? 0),
            'changePercentage' => self::_percentage($change, $cost),
        ];
    }

    /**
     * User Overview figures in EUR and USD: the live account figures summed after conversion,
     * mirroring how the cron sums the stored account series.
     */
    public static function userLiveValues(array $liveByAccount, array $accountData,
        float $eurusd): array
    {
        $sums = [];
        foreach (self::_CURRENCIES as $currency) {
            foreach (self::_AMOUNT_METRICS as $metric) {
                $sums[$metric . '_' . $currency] = 0.0;
            }
        }

        foreach ($liveByAccount as $accountId => $live) {
            $accountCurrency = $accountData[$accountId]['accountModel']->currency->iso_code;
            foreach (self::_CURRENCIES as $currency) {
                $factor = self::fxFactor($accountCurrency, $currency, $eurusd);
                if ($factor === null) {
                    continue;
                }
                foreach (self::_AMOUNT_METRICS as $metric) {
                    $sums[$metric . '_' . $currency] += $live[$metric] * $factor;
                }
            }
        }

        foreach (self::_CURRENCIES as $currency) {
            $sums['changePercentage_' . $currency] = self::_percentage(
                $sums['change_' . $currency], $sums['cost_' . $currency]
            );
        }

        return $sums;
    }

    /**
     * Factor converting an amount from one of EUR/USD to the other (EURUSD is USD per EUR).
     */
    public static function fxFactor(string $from, string $to, float $eurusd): ?float
    {
        return match (true) {
            $from === $to => 1.0,
            $from === 'USD' && $to === 'EUR' => 1.0 / $eurusd,
            $from === 'EUR' && $to === 'USD' => $eurusd,
            default => null,
        };
    }

    /**
     * Set a series' value for today: replace the last point when it is today's, else append one.
     *
     * @param array<int, array{time: string, value: float}> $series
     */
    public static function pin(array $series, float $value, string $today): array
    {
        if (!is_finite($value)) {
            return $series;
        }

        $last = end($series);
        if ($last !== false && ($last['time'] ?? null) === $today) {
            $series[array_key_last($series)]['value'] = $value;
            return $series;
        }

        $series[] = ['time' => $today, 'value' => $value];

        return $series;
    }

    /**
     * Same formula as the cron's changePercentage series (change / cost * 100, 0 without cost).
     */
    private static function _percentage(float $change, float $cost): float
    {
        return $cost != 0.0 ? ($change / $cost) * 100 : 0.0;
    }

    private static function _parse(string $literal): array
    {
        return ChartsBuilder::parseChartLiteral($literal);
    }
}
