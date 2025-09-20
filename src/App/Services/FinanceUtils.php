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
     * @param $exchangeRateData array(EURUSD => array(account_currency  => 'EUR',
     *                                                trade_currency    => 'USD'))
     *
     * @return $currencyPairs array(array('EUR', 'USD'))
     */
    public static function exchangeRateDataToCurrencyPairs(array $exchangeRateData)
        : array
    {
        if (empty($exchangeRateData)) {
            return [];
        }

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
        // LOG::debug('exchangeRateData: ' . print_r($exchangeRateData, true));
        // LOG::debug('currencyPairs: ' . print_r($currencyPairs, true));

        return $currencyPairs;
    }

    /**
     * @param $currencyPairs array(array('EUR', 'USD'))
     * @param $date \DateTimeInterface
     *
     * @return $results array(HistoricalData)
     */
    public function getLastAvailableExchangeRates(array $currencyPairs,
        \DateTimeInterface $date): ?array
    {
        if (empty($currencyPairs)) {
            return [];
        }

        //NOTE there is no historical data when market is closed
        //      so we look for the day before or the day before that
        $maxDaysBefore = 7;
        $currentDaysBefore = 1;
        $financeAPI = new FinanceAPI();

        do {
            $currentDate = clone $date;
            $currentDate = $currentDate
                ->modify('-' . $currentDaysBefore . ' days');

            $results = $financeAPI->getHistoricalExchangeRates(
                $currencyPairs,
                $currentDate
            );

            if (!empty($results)) {
                LOG::info('We were not able to get the exchange rates for the '
                          . 'given date: '
                          . $date->format('Y-m-d')
                          . ', but were able to get the '
                          . 'exchange rates for date: '
                          . $currentDate->format('Y-m-d'));
                break;
            } else {
                LOG::info('Could NOT get the historical exchange rates for '
                          . 'date: '
                          . $currentDate->format('Y-m-d'));
            }
            $currentDaysBefore++;
        } while (empty($results)
                 && $currentDaysBefore <= $maxDaysBefore);

        if (empty($results)) {
            LOG::error('Could NOT get the historical exchange rates for'
                       . ' date: ' . $date->format('Y-m-d')
                       . '! This should never happen! '
                       . 'We still failed after all these tries!');
            return null;
        }

        // LOG::debug('exchange rate results 165: ' . print_r($results, true));
        return $results;
    }


    public function getLastAvailableQuote(Quote $quote, \DateTimeInterface $date)
        : ?HistoricalData
    {
        //NOTE there is no historical data when market is closed
        //      so we look for the day before or the day before that
        $maxDaysBefore = 7;
        $currentDaysBefore = 0;
        $financeAPI = new FinanceAPI();

        do {
            $currentDate = clone $date;
            $currentDate = $currentDate
                ->modify('-'.$currentDaysBefore . ' days');

            $historicalQuoteData = $financeAPI->getHistoricalQuoteData(
                $quote,
                $currentDate
            );

            if (!empty($historicalQuoteData)) {
                /*
                LOG::debug('HistoricalQuoteData for symbol: '
                           . $quote->getSymbol() . ', date: '
                           . $currentDate->format('Y-m-d')
                           . ' => price: '
                           . $historicalQuoteData->getClose()
                           . ', quote_timestamp: '
                           . $historicalQuoteData->getDate()
                                                 ->format('Y-m-d'));
                */
            } else {
                LOG::info('Could NOT get the historical quote for symbol: '
                          . $quote->getSymbol() . ', date: '
                          . $currentDate->format('Y-m-d'));
            }
            $currentDaysBefore++;
        } while (empty($historicalQuoteData)
                 && $currentDaysBefore <= $maxDaysBefore);

        if (empty($historicalQuoteData)
            || !($historicalQuoteData instanceof HistoricalData)
        ) {
            LOG::error('Could NOT get the historical quote for symbol: '
                       . $quote->getSymbol() . ', date: ' . $date->format('Y-m-d')
                       . '! This should never happen! '
                       . 'We still failed after all these tries!');
            return null;
        }

        return $historicalQuoteData;
    }

    /**
     * @param $exchangeRateData array(EURUSD => array(account_currency  => 'EUR',
     *                                                trade_currency    => 'USD'))
     * @param $date \DateTimeInterface
     *
     * @return $exchangeRateData array(EURUSD => array(account_currency => 'EUR',
     *                                                 trade_currency   => 'USD',
     *                                                 exchange_rate    => 1.1))
     */
    public function getExchangeRates(array $exchangeRateData,
        \DateTimeInterface $date = null): ?array
    {
        if (empty($exchangeRateData)) {
            return [];
        }

        $currencyPairs = self::exchangeRateDataToCurrencyPairs($exchangeRateData);
        $financeAPI = new FinanceAPI();

        $results = [];
        if (!empty($date) && date('Y-m-d') != $date->format('Y-m-d')) {
            $results =
                $financeAPI->getHistoricalExchangeRates($currencyPairs, $date);
        } else {
            $results = $financeAPI->getExchangeRates($currencyPairs);
        }

        if (empty($date)) {
            $date = new \DateTime();
        }

        if (empty($results)) {
            $results = $this->getLastAvailableExchangeRates($currencyPairs, $date);
        }

        $currenciesMapping = config('general.currencies_mapping');
        $i = 0;
        foreach ($results as $result) {
            //NOTE If date is provided, we look at historical data
            if ($result instanceof Quote) {
                $exchangeRate = $result->getRegularMarketPrice();
            } else if ($result instanceof HistoricalData) {
                $exchangeRate = $result->getClose();
            } else {
                LOG::error('Unexpected result in FinanceUtils->getExchangeRates()! '
                           . print_r($result, true));
                return null;
            }
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

        // LOG::debug('exchangeRateData 232: ' . print_r($exchangeRateData, true));
        return $exchangeRateData;
    }


    /**
     * @param array $symbols
     * @param $date \DateTimeInterface
     *
     * @return array(symbol => (price, currency, name, quote_timestamp, day_change))
     *         or null if failure
     */
    public function getQuotes($symbols, \DateTimeInterface $date = null)
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

            //NOTE If we provide a date, we overwrite the price and quote timestamp
            if (!empty($date) && date('Y-m-d') != $date->format('Y-m-d')) {
                $historicalQuoteData = $this->getLastAvailableQuote($quote, $date);

                if (!empty($historicalQuoteData)) {
                    $quotesArray[$quote->getSymbol()]['price'] =
                        $historicalQuoteData->getClose();
                    $quotesArray[$quote->getSymbol()]['quote_timestamp'] =
                        $historicalQuoteData->getDate();
                } else {
                    $quotesArray[$quote->getSymbol()]['price'] = null;
                    $quotesArray[$quote->getSymbol()]['quote_timestamp'] = null;
                }
            }
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

