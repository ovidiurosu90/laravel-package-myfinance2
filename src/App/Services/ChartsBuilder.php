<?php

namespace ovidiuro\myfinance2\App\Services;

use Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChartsBuilder
{
    private static function _checkUser(array $accountData)
    {
        if (php_sapi_name() === 'cli') { // in browser we have 'apache2handler'
            return; // We don't check
        }

        if (!Auth::check()
            || Auth::user()->id != $accountData['accountModel']->user_id
        ) {
            abort(403, 'Access denied in ChartsBuilder');
        }
    }

    private static function _checkUserForChartOverviewUser(int $userId)
    {
        if (php_sapi_name() === 'cli') { // in browser we have 'apache2handler'
            return; // We don't check
        }

        if (!Auth::check()
            || Auth::user()->id != $userId
        ) {
            abort(403, 'Access denied in ChartsBuilder for chart user');
        }
    }

    public static function getAccountMetrics(): array
    {
        return [
            'cash' => [
                'line_color' => 'rgba(255, 192, 0, 1)',
                'title' => 'Cash',
            ],
            'change' => [
                'line_color' => 'rgba(67, 83, 254, 1)',
                'title' => 'Change',
            ],
            'cost' => [
                'line_color' => 'rgba(38, 166, 154, 1)',
                'title' => 'Cost',
            ],
            'mvalue' => [
                'line_color' => 'rgba(239, 83, 80, 1)',
                'title' => 'Market Value',
            ],
        ];
    }

    public static function getOverviewUserMetricPath(int $userId, string $metric)
        :string
    {
        self::_checkUserForChartOverviewUser($userId);

        return 'charts' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR
               . $userId . DIRECTORY_SEPARATOR . 'overview' . DIRECTORY_SEPARATOR
               . $metric . '.json';
    }

    public static function getAccountMetricPath(array $accountData, string $metric)
        :string
    {
        self::_checkUser($accountData);
        $userId = $accountData['accountModel']->user_id;
        $accountId = $accountData['accountModel']->id;

        return 'charts' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR
               . $userId . DIRECTORY_SEPARATOR . $accountId . DIRECTORY_SEPARATOR
               . $metric . '.json';
    }

    public static function getSymbolPath(string $symbol)
        :string
    {
        return 'charts' . DIRECTORY_SEPARATOR . 'symbols' . DIRECTORY_SEPARATOR
               . $symbol . '.json';
    }

    public static function buildChartOverviewUser(int $userId,
        string $metric, array $stats)
    {
        self::_checkUserForChartOverviewUser($userId);

        $path = self::getOverviewUserMetricPath($userId, $metric);
        $contents = self::getOverviewStatsAsJsonString($stats);
        Storage::disk('local')->put($path, $contents);
    }

    public static function buildChartAccount(array $accountData,
        string $metric, array $stats)
    {
        self::_checkUser($accountData);
        $path = self::getAccountMetricPath($accountData, $metric);
        $contents = self::getStatsAsJsonString($stats);
        Storage::disk('local')->put($path, $contents);
    }

    public static function buildChartSymbol(string $symbol, array $stats)
    {
        $path = self::getSymbolPath($symbol);
        $contents = self::getStatsAsJsonString($stats);
        Storage::disk('local')->put($path, $contents);
    }

    public static function getChartOverviewUserAsJsonString(int $userId,
        string $metric): string
    {
        self::_checkUserForChartOverviewUser($userId);

        $path = self::getOverviewUserMetricPath($userId, $metric);
        $contents = '[]';
        if (Storage::disk('local')->exists($path)) {
            $contents = Storage::disk('local')->get($path);
        }
        return $contents;
    }

    public static function getChartAccountAsJsonString(array $accountData,
        string $metric): string
    {
        self::_checkUser($accountData);
        $path = self::getAccountMetricPath($accountData, $metric);
        $contents = '[]';
        if (Storage::disk('local')->exists($path)) {
            $contents = Storage::disk('local')->get($path);
        }
        return $contents;
    }

    public static function getChartSymbolAsJsonString(string $symbol): string
    {
        $path = self::getSymbolPath($symbol);
        $contents = '[]';
        if (Storage::disk('local')->exists($path)) {
            $contents = Storage::disk('local')->get($path);
        }
        return $contents;
    }

    public static function convertAccountStatsToCurrency(array $accountData,
        string $metric, array $stats): array
    {
        self::_checkUser($accountData);
        $accountCurrency = $accountData['accountModel']->currency->iso_code;
        $convertedCurrency = null;
        switch ($accountCurrency) {
            case 'EUR':
                $convertedCurrency = 'USD';
                break;
            case 'USD':
                $convertedCurrency = 'EUR';
                break;
            default:
                Log::error('Unexpected account currency ' . $accountCurrency
                           . ' in convertAccountStatsToCurrency()');
                return null;
        }

        $convertedMetric = $metric . '_' . $convertedCurrency;
        $convertedStats = Stats::convertStatsToCurrency($stats, $convertedCurrency);
        return array($convertedMetric, $convertedStats);
    }

    public static function convertPositionStatsToCurrency(array $position,
        array $stats): array
    {
        $symbolCurrency = $position['tradeCurrencyModel']->iso_code;
        $symbol = $position['symbol'];
        $convertedCurrency = null;
        switch ($symbolCurrency) {
            case 'EUR':
                $convertedCurrency = 'USD';
                break;
            case 'USD':
                $convertedCurrency = 'EUR';
                break;
            case 'GBX':
                $convertedCurrency = 'EUR';
                break;
            default:
                Log::error('Unexpected symbol currency ' . $symbolCurrency
                           . ' in convertPositionStatsToCurrency()');
                return null;
        }

        $convertedSymbol = $symbol . '_' . $convertedCurrency;
        $convertedStats = Stats::convertStatsToCurrency($stats, $convertedCurrency);
        return array($convertedSymbol, $convertedStats);
    }

    public static function getStatsAsJsonString(array $stats)
    {
        $return = '';
        if (!empty($stats['historical']) && is_array($stats['historical'])) {
            foreach ($stats['historical'] as $stat) {
                if (empty($stat) || empty($stat['date'])) {
                    continue;
                }
                if ($stat['date'] == date(trans('myfinance2::general.date-format'))
                    && !empty($stats['today_last'])
                ) {
                    // skip historical for today if I have another entry
                    continue;
                }

                $return .= "{ time: '" . $stat['date']
                    . "', value: " . $stat['unit_price'] . "},";
            }
        }
        if (!empty($stats['today_last'])) {
            $return .= "{ time: '" . date(trans('myfinance2::general.date-format'))
                . "', value: "
                . $stats['today_last']['unit_price'] . "},";
        }

        return '[' . rtrim($return, ',') . ']';
    }

    public static function getOverviewStatsAsJsonString(array $stats)
    {
        $return = '';
        if (!empty($stats) && is_array($stats)) {
            foreach ($stats as $date => $stat) {
                $return .= "{ time: '" . $date
                    . "', value: " . $stat['unit_price'] . "},";
            }
        }

        return '[' . rtrim($return, ',') . ']';
    }
}

