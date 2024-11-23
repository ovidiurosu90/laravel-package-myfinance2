<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\ApiClientFactory;
use Scheb\YahooFinanceApi\Results\Quote;
use Scheb\YahooFinanceApi\Results\HistoricalData;
use GuzzleHttp\Client;

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
        $options = [/*...*/];
        $guzzleClient = new Client($options);
        $client = ApiClientFactory::createApiClient($guzzleClient);

        //NOTE We need the quote even for historical data (to get the currency and name)
        $quote;
        try {
            // Returns Scheb\YahooFinanceApi\Results\Quote
            $quote = $client->getQuote($symbol);
        } catch (Exception $e) {
            LOG::warning("Couldn't get quote for symbol $symbol. Exception message: " . $e->getMessage());
        }
        if (empty($quote) || !($quote instanceof Quote)) {
            return null;
        }

        // LOG::debug('quote'); LOG::debug(var_export($quote, true));
        $price = $quote->getRegularMarketPrice();
        $currency = $quote->getCurrency();
        $name = $quote->getLongName();
        $quoteTimestamp = $quote->getRegularMarketTime();
        $quoteTimezone = $quote->getExchangeTimezoneName();

        // LOG::debug('quote'); LOG::debug(var_export($quote, true));

        if (!empty($timestamp) && // Has timestamp
            date('Ymd') > date('Ymd', strtotime($timestamp)) // Timestamp is in the past
        ) {
            $timestamp1 = new \DateTime($timestamp);
            $timestamp1->setTime(0, 0, 0, 0);
            $timestamp2 = clone $timestamp1;
            $timestamp2->add(new \DateInterval('P1D'));
            $offset = self::get_timezone_offset($quoteTimezone);

            // LOG::debug('quoteTimezone'); LOG::debug(var_export($quoteTimezone, true));
            // LOG::debug('timestampTimezone'); LOG::debug(var_export($timestamp1->getTimezone()->getName(), true));
            // LOG::debug('offset'); LOG::debug(var_export($offset, true));

            //NOTE Adding 1 day when origin timezone is ahead of remote timezone
            if ($offset > 0) { // For stocks like GOOGL, AMZN, MSFT
                $timestamp1->add(new \DateInterval('P1D'));
                $timestamp2->add(new \DateInterval('P1D'));
            }

            $historicalData;
            try {
                // Returns an array of Scheb\YahooFinanceApi\Results\HistoricalData
                $historicalData = $client->getHistoricalData($symbol, ApiClient::INTERVAL_1_DAY,
                    $timestamp1, $timestamp2);
            } catch (Exception $e) {
                LOG::warning("Couldn't get historical data for symbol $symbol. Exception message: " .
                          $e->getMessage());
            }
            if (empty($historicalData) || !is_array($historicalData) ||
                !($historicalData[0] instanceof HistoricalData)
            ) {
                return null;
            }

            // LOG::debug('historicalData'); LOG::debug(var_export($historicalData, true));
            $price = $historicalData[0]->getClose();
            $quoteTimestamp = $historicalData[0]->getDate();
        }

        $offset2 = self::get_timezone_offset($quoteTimestamp->getTimezone()->getName());
        $quoteTimestamp->add(\DateInterval::createFromDateString((string)$offset2 . 'seconds'));
        // LOG::debug('offset2'); LOG::debug(var_export($offset2, true));

        return [
            'price'           => $price,
            'currency'        => $currency,
            'name'            => $name,
            'quote_timestamp' => $quoteTimestamp,

            'fiftyTwoWeekHigh'              => $quote->getFiftyTwoWeekHigh(),
            'fiftyTwoWeekHighChangePercent' => $quote->getFiftyTwoWeekHighChangePercent(),
            'fiftyTwoWeekLow'               => $quote->getFiftyTwoWeekLow(),
            'fiftyTwoWeekLowChangePercent'  => $quote->getFiftyTwoWeekLowChangePercent(),
        ];
    }


    /**
     * @param $exchangeRateData array(array(account_currency => 'EUR', trade_currency => 'USD'))
     *
     * @return $exchangeRateData array(array(account_currency => 'EUR', trade_currency => 'USD', exchange_rate => 1.1))
     */
    public function getExchangeRates(array $exchangeRateData): array
    {
        $options = [/*...*/];
        $guzzleClient = new Client($options);
        $client = ApiClientFactory::createApiClient($guzzleClient);

        $currenciesMapping = config('general.currencies_mapping');
        $currenciesReverseMapping = config('general.currencies_reverse_mapping');

        $currencyPairs = [];
        foreach ($exchangeRateData as $exchangeRateIndex => $exchangeRateDataItem) {
            if ($exchangeRateDataItem['account_currency'] == $exchangeRateDataItem['trade_currency']) {
                $exchangeRateData[$exchangeRateIndex]['exchange_rate'] = 1;
                continue;
            }
            $currencyPair = [$exchangeRateDataItem['account_currency'], $exchangeRateDataItem['trade_currency']];
            if (!empty($currenciesReverseMapping[$currencyPair[1]])) {
                $currencyPair[1] = $currenciesReverseMapping[$currencyPair[1]];
            }
            $currencyPairs[] = $currencyPair;
        }
        // LOG::debug('currencyPairs: ' . print_r($currencyPairs, true));

        $quotes = null;
        try {
            // Returns an array of Scheb\YahooFinanceApi\Results\Quote
            // LOG::debug("getExchangeRates for currencyPairs 134: " . print_r($currencyPairs, true));
            $quotes = $client->getExchangeRates($currencyPairs);
        } catch (Exception $e) {
            LOG::warning("Couldn't get exchange rates for currencyPairs" . print_r($currencyPairs, true) .
                      ". Exception message: " . $e->getMessage());
        }
        if (empty($quotes) || !is_array($quotes) || !($quotes[0] instanceof Quote)) {
            return null;
        }
        // LOG::debug('exchange rate quotes 143: ' . print_r($quotes, true));


        $i = 0;
        foreach ($quotes as $quote) {
            // LOG::debug('quote'); LOG::debug(var_export($quote, true));

            $exchangeRate = $quote->getRegularMarketPrice();
            if ($currencyPairs[$i][1] == 'GBp') { // The exchange rate is for GBP
                $exchangeRate *= 100;
            }
            if (!empty($currenciesMapping[$currencyPairs[$i][1]])) {
                $currencyPairs[$i][1] = $currenciesMapping[$currencyPairs[$i][1]];
            }

            $exchangeRateIndex = $currencyPairs[$i][0] . $currencyPairs[$i][1]; // EURUSD
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
        $options = [/*...*/];
        $guzzleClient = new Client($options);
        $client = ApiClientFactory::createApiClient($guzzleClient);

        $quotes = null;
        try {
            // Returns an array of Scheb\YahooFinanceApi\Results\Quote
            // LOG::debug("getQuotes for symbols 184: " . print_r($symbols, true));
            $quotes = $client->getQuotes($symbols);
        } catch (Exception $e) {
            LOG::warning("Couldn't get quotes for symbols " . join(', ', $symbols) .
                      ". Exception message: " . $e->getMessage());
        }
        if (empty($quotes) || !is_array($quotes) || !($quotes[0] instanceof Quote)) {
            return null;
        }
        // LOG::debug('quotes 190: ' . print_r($quotes, true));

        $quotesArray = [];
        foreach ($quotes as $quote) {
            $currency = $quote->getCurrency();
            $quoteTimestamp = $quote->getRegularMarketTime();
            $offset = self::get_timezone_offset($quoteTimestamp->getTimezone()->getName());
            $quoteTimestamp->add(\DateInterval::createFromDateString((string)$offset . 'seconds'));

            $quotesArray[$quote->getSymbol()] = [
                'price'                 => $quote->getRegularMarketPrice(),
                'currency'              => $currency,
                'name'                  => $quote->getLongName(),
                'quote_timestamp'       => $quoteTimestamp,
                'day_change'            => $quote->getRegularMarketChange(),
                'day_change_percentage' => $quote->getRegularMarketChangePercent(),

                'fiftyTwoWeekHigh'              => $quote->getFiftyTwoWeekHigh(),
                'fiftyTwoWeekHighChangePercent' => $quote->getFiftyTwoWeekHighChangePercent(),
                'fiftyTwoWeekLow'               => $quote->getFiftyTwoWeekLow(),
                'fiftyTwoWeekLowChangePercent'  => $quote->getFiftyTwoWeekLowChangePercent(),

                'marketUtils' => new MarketUtils($quote),
            ];
        }

        return $quotesArray;
    }

    /**
     * @param string $symbol
     * @param string $account
     * @param string $accountCurrency
     * @param string $timestamp
     * @param integer $tradeId
     *
     * @return integer $availableQuantity or null if failure
     */
    public function getAvailableQuantity($symbol, $account, $accountCurrency, $timestamp = null, $tradeId = null)
    {
        if (!in_array($account, array_keys(config('general.trade_accounts')))) {
            LOG::error('Invalid account: ' . $account);
            return null;
        }
        if (!in_array($accountCurrency, array_keys(config('general.ledger_currencies')))) {
            LOG::error('Invalid account currency: ' . $accountCurrency);
            return null;
        }
        if (empty($timestamp) || !\DateTime::createFromFormat(trans('myfinance2::general.datetime-format'), $timestamp)) {
            $timestamp = date(trans('myfinance2::general.datetime-format'));
        }

        $availableQuantity = 0;
        $tradesQuery = Trade::whereDate('timestamp', '<=', $timestamp)
            ->where('symbol', $symbol)
            ->where('account', $account)
            ->where('account_currency', $accountCurrency)
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
     * Returns the offset from the origin timezone to the remote timezone, in seconds.
     *
     * @param $remote_tz;
     * @param $origin_tz; If null the servers current timezone is used as the origin.
     *
     * @return int; Offset in seconds (positive when origin is ahead of remote; negative otherwise)
     *              e.g. for MSFT (America/New_York) to Europe/Amsterdam, offset is 21600s (6h)
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
        $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
        return $offset;
    }
}

