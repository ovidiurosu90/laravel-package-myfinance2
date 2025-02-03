<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\Results\Quote;
use Scheb\YahooFinanceApi\Results\HistoricalData;
use ovidiuro\myfinance2\App\Models\Trade;

class FinanceUtils
{
    /**
     * @param string $symbol
     * @param string $timestamp
     *
     * @return array(price, currency, name, quote_timestamp) or null if failure
     */
    public function getFinanceDataBySymbol($symbol, $timestamp = null)
    {
        $financeAPI = new FinanceAPI();
        $quote = $financeAPI->getQuote($symbol);
        if (empty($quote)) {
            return null;
        }

        // LOG::debug('quote'); LOG::debug(var_export($quote, true));
        $price = $quote->getRegularMarketPrice();
        $currency = $quote->getCurrency();
        $name = $quote->getLongName();
        $quoteTimestamp = $quote->getRegularMarketTime();

        if (!empty($timestamp) && // Has timestamp
            date('Ymd') > date('Ymd', strtotime($timestamp)) // in the past
        ) {
            $historicalData = $financeAPI->getHistoricalQuoteData(
                $quote, new \DateTime($timestamp));

            if (empty($historicalData)) {
                return null;
            }

            // LOG::debug('historicalData');
            // LOG::debug(var_export($historicalData, true));
            $price = $historicalData->getClose();
            $quoteTimestamp = $historicalData->getDate();
        }

        $offset = self::get_timezone_offset(
            $quoteTimestamp->getTimezone()->getName());
        $quoteTimestamp->add(
            \DateInterval::createFromDateString((string)$offset . 'seconds'));
        // LOG::debug('offset'); LOG::debug(var_export($offset, true));

        return [
            'price'           => $price,
            'currency'        => $currency,
            'name'            => $name,
            'quote_timestamp' => $quoteTimestamp,

            'fiftyTwoWeekHigh'              => $quote->getFiftyTwoWeekHigh(),
            'fiftyTwoWeekHighChangePercent' =>
                $quote->getFiftyTwoWeekHighChangePercent(),
            'fiftyTwoWeekLow'               => $quote->getFiftyTwoWeekLow(),
            'fiftyTwoWeekLowChangePercent'  =>
                $quote->getFiftyTwoWeekLowChangePercent(),
        ];
    }


    /**
     * @param $exchangeRateData array(array(account_currency  => 'EUR',
     *                                      trade_currency    => 'USD'))
     *
     * @return $exchangeRateData array(array(account_currency => 'EUR',
     *                                       trade_currency   => 'USD',
     *                                       exchange_rate    => 1.1))
     */
    public function getExchangeRates(array $exchangeRateData): ?array
    {
        if (empty($exchangeRateData)) {
            return $exchangeRateData;
        }

        $currenciesMapping = config('general.currencies_mapping');
        $currenciesReverseMapping = config('general.currencies_reverse_mapping');

        $currencyPairs = [];
        foreach ($exchangeRateData as $exchangeRateIndex => $exchangeRateDataItem) {
            if ($exchangeRateDataItem['account_currency'] ==
                $exchangeRateDataItem['trade_currency']
            ) {
                $exchangeRateData[$exchangeRateIndex]['exchange_rate'] = 1;
                continue;
            }
            $currencyPair = [
                $exchangeRateDataItem['account_currency'],
                $exchangeRateDataItem['trade_currency']
            ];
            if (!empty($currenciesReverseMapping[$currencyPair[0]])) {
                $currencyPair[0] = $currenciesReverseMapping[$currencyPair[0]];
            }
            if (!empty($currenciesReverseMapping[$currencyPair[1]])) {
                $currencyPair[1] = $currenciesReverseMapping[$currencyPair[1]];
            }
            $currencyPairs[] = $currencyPair;
        }
        // LOG::debug('currencyPairs: ' . print_r($currencyPairs, true));

        $financeAPI = new FinanceAPI();
        $quotes = $financeAPI->getExchangeRates($currencyPairs);
        if (empty($quotes)) {
            return null;
        }
        // LOG::debug('exchange rate quotes 133: ' . print_r($quotes, true));

        $i = 0;
        foreach ($quotes as $quote) {
            $exchangeRate = $quote->getRegularMarketPrice();
            if ($currencyPairs[$i][1] == 'GBp') { // The exchange rate is for GBP
                $exchangeRate *= 100;
            }
            if (!empty($currenciesMapping[$currencyPairs[$i][1]])) {
                $currencyPairs[$i][1] = $currenciesMapping[$currencyPairs[$i][1]];
            }

            // EURUSD
            $exchangeRateIndex = $currencyPairs[$i][0] . $currencyPairs[$i][1];
            $exchangeRateData[$exchangeRateIndex]['exchange_rate'] = $exchangeRate;

            $i++;
        }

        return $exchangeRateData;
    }


    /**
     * @param array $symbols
     *
     * @return array(symbol => (price, currency, name, quote_timestamp, day_change))
     *         or null if failure
     */
    public function getQuotes($symbols)
    {
        $quotesArray = [];
        if (empty($symbols)) {
            return $quotesArray;
        }

        $financeAPI = new FinanceAPI();
        $quotes = $financeAPI->getQuotes($symbols);
        if (empty($quotes)) {
            return null;
        }
        // LOG::debug('quotes 190: ' . print_r($quotes, true));

        foreach ($quotes as $quote) {
            $currency = $quote->getCurrency();
            $quoteTimestamp = $quote->getRegularMarketTime();
            $offset = self::get_timezone_offset(
                $quoteTimestamp->getTimezone()->getName());
            $quoteTimestamp->add(
                \DateInterval::createFromDateString((string)$offset . 'seconds'));

            $quotesArray[$quote->getSymbol()] = [
                'price'                 => $quote->getRegularMarketPrice(),
                'currency'              => $currency,
                'name'                  => $quote->getLongName(),
                'quote_timestamp'       => $quoteTimestamp,
                'day_change'            => $quote->getRegularMarketChange(),
                'day_change_percentage' => $quote->getRegularMarketChangePercent(),

                'fiftyTwoWeekHigh'              => $quote->getFiftyTwoWeekHigh(),
                'fiftyTwoWeekHighChangePercent' =>
                    $quote->getFiftyTwoWeekHighChangePercent(),
                'fiftyTwoWeekLow'               => $quote->getFiftyTwoWeekLow(),
                'fiftyTwoWeekLowChangePercent'  =>
                    $quote->getFiftyTwoWeekLowChangePercent(),

                'marketUtils' => new MarketUtils($quote),
            ];
        }

        return $quotesArray;
    }

    /**
     * @param string  $symbol
     * @param integer $account_id
     * @param string  $timestamp
     * @param integer $tradeId
     *
     * @return integer $availableQuantity or null if failure
     */
    public function getAvailableQuantity($symbol, $account_id,
        $timestamp = null, $tradeId = null
    ) {
        if (empty($account_id) || !is_numeric($account_id)) {
            LOG::error('Invalid account_id: ' . $account_id);
            return null;
        }
        if (empty($timestamp) || !\DateTime::createFromFormat(
            trans('myfinance2::general.datetime-format'), $timestamp)
        ) {
            $timestamp = date(trans('myfinance2::general.datetime-format'));
        }

        $availableQuantity = 0;
        $tradesQuery = Trade::whereDate('timestamp', '<=', $timestamp)
            ->where('symbol', $symbol)
            ->where('account_id', $account_id)
            ->orderBy('timestamp', 'asc');
        if (!empty($tradeId)) {
            $tradesQuery->where('id', '!=', $tradeId);
        }

        $trades = $tradesQuery->get();
        $trades->each(function ($trade) use (&$availableQuantity) {
            switch($trade->action) {
                case 'BUY':
                    $availableQuantity += $trade->quantity;
                    break;
                case 'SELL':
                    $availableQuantity -= $trade->quantity;
                    break;
                default:
                    LOG::error('Unexpected action ' . $trade->action);
                    return null;
            }
        });

        $availableQuantity = round($availableQuantity) == $availableQuantity ?
            $availableQuantity : round($availableQuantity, 8);

        return $availableQuantity;
    }

    /**
     * Returns the offset from the origin timezone to the remote timezone,
     *      in seconds.
     *
     * @param $remote_tz;
     * @param $origin_tz; If null the servers current timezone is used
     *          as the origin.
     *
     * @return int; Offset in seconds (positive when origin is ahead of remote;
     *                                 negative otherwise)
     *              e.g. for MSFT (America/New_York) to Europe/Amsterdam,
     *                  offset is 21600s (6h)
     */
    public static function get_timezone_offset($remote_tz, $origin_tz = null)
    {
        if ($origin_tz === null) {
            if(!is_string($origin_tz = date_default_timezone_get())) {
                return false; // A UTC timestamp was returned -- bail out!
            }
        }
        $origin_dtz = new \DateTimeZone($origin_tz);
        $remote_dtz = new \DateTimeZone($remote_tz);
        $origin_dt = new \DateTime("now", $origin_dtz);
        $remote_dt = new \DateTime("now", $remote_dtz);
        $offset = $origin_dtz->getOffset($origin_dt)
            - $remote_dtz->getOffset($remote_dt);
        return $offset;
    }
}

