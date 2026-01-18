<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

class Positions
{
    /**
     * @var bool
     */
    private $_withUser = true;

    /**
     * @var array
     */
    private array $_extraSymbols = [];

    /**
     * @var bool
     * Controls whether to include CLOSED trades in historical queries
     * - true: Include closed trades (for Returns page - realized gains calculation)
     * - false: Only OPEN trades (for Positions page - current holdings snapshot)
     */
    private bool $_includeClosedTrades = false;

    public function setWithUser(bool $withUser = true): void
    {
        if (!$withUser
            && php_sapi_name() !== 'cli' // in browser we have 'apache2handler'
        ) {
            abort(403, 'Access denied in Account Model');
        }

        $this->_withUser = $withUser;
    }

    public function setExtraSymbols(array $extraSymbols = array()): void
    {
        $this->_extraSymbols = $extraSymbols;
    }

    public function setIncludeClosedTrades(bool $includeClosedTrades = true): void
    {
        $this->_includeClosedTrades = $includeClosedTrades;
    }

    /**
     * @return Collection<Trade>
     */
    public function getTrades(\DateTimeInterface $date = null) : Collection
    {
        $queryBuilder = Trade::with('accountModel', 'tradeCurrencyModel');
        if (!$this->_withUser) {
            $queryBuilder = Trade::with('accountModelNoUser',
                'tradeCurrencyModelNoUser')
                ->withoutGlobalScope(AssignedToUserScope::class);
        }

        // Filter by status based on context:
        // - Current positions: always OPEN only
        // - Historical + $_includeClosedTrades=true: all trades (for Returns page)
        // - Historical + $_includeClosedTrades=false: OPEN only (for Positions/Charts)
        if (empty($date)) {
            // Current positions: always filter by OPEN
            $queryBuilder = $queryBuilder->where('status', 'OPEN');
        } else {
            // Historical positions: depends on $_includeClosedTrades flag
            if (!$this->_includeClosedTrades) {
                $queryBuilder = $queryBuilder->where('status', 'OPEN');
            }
            // else: include both OPEN and CLOSED for Returns calculation
        }

        // For historical queries, include all trades UP TO and INCLUDING the given date
        // Use DATE() comparison to include all trades during that day
        if (!empty($date)) {
            $trades = $queryBuilder
                ->whereRaw('DATE(timestamp) <= ?', [$date->format('Y-m-d')])
                ->orderBy('timestamp')
                ->get();
        } else {
            $trades = $queryBuilder
                ->where('timestamp', '<=', \DB::raw('NOW()'))
                ->orderBy('timestamp')
                ->get();
        }

        if (!$this->_withUser) {
            foreach ($trades as $trade) {
                if (!empty($trade->accountModelNoUser)) {
                    $trade->accountModel = $trade->accountModelNoUser;
                }
                if (!empty($trade->accountModel->currencyNoUser)) {
                    $trade->accountModel->currency =
                        $trade->accountModel->currencyNoUser;
                }
                if (!empty($trade->tradeCurrencyModelNoUser)) {
                    $trade->tradeCurrencyModel = $trade->tradeCurrencyModelNoUser;
                }
            }
        }

        return $trades;
    }

    /**
     * @param Collection<Trade> $trades
     *
     * @return array<accountId: array<symbol: array symbolData>>
     */
    public static function tradesToPositions(Collection $trades): array
    {
        $positions = []; // positions grouped by account & symbol

        foreach ($trades as $trade) {
            $accountId = $trade->accountModel->id;
            $symbol = $trade->symbol;

            if (empty($positions[$accountId])) {
                $positions[$accountId] = [];
            }
            if (empty($positions[$accountId][$symbol])) {
                $positions[$accountId][$symbol] = [
                    'symbol'                    => $symbol,
                    'accountModel'              => $trade->accountModel,
                    'tradeCurrencyModel'        => $trade->tradeCurrencyModel,
                    'unit_price'                => $trade->unit_price,
                    'quantity'                  => 0,
                    'cost_in_account_currency'  => 0,
                    'cost_in_trade_currency'    => 0,
                    'quantity2'                 => 0,
                    'cost2_in_account_currency' => 0,
                    'cost2_in_trade_currency'   => 0,
                    'trades'                    => [],
                ];
            } else {
                // Check if trade_currency changed
                if ($trade->tradeCurrencyModel->iso_code !=
                    $positions[$accountId][$symbol]['tradeCurrencyModel']->iso_code
                ) {
                    LOG::error('Inconsistent trade currency for accountId: '
                               . $accountId . ', symbol: ' . $symbol
                               . '! Ignoring trade with id ' . $trade->id);
                    continue;
                }
            }

            //NOTE We use the inversed exchange rate
            $principleAmount = 1 /
                $trade->exchange_rate * $trade->quantity * $trade->unit_price;
            $principleAmountInTradeCurrency = $trade->quantity * $trade->unit_price;

            switch ($trade->action) {
                case 'BUY':
                    $positions[$accountId][$symbol]['quantity'] +=
                        $trade->quantity;
                    $positions[$accountId][$symbol]['cost_in_account_currency'] +=
                        $principleAmount + $trade->fee;
                    $positions[$accountId][$symbol]['cost_in_trade_currency'] +=
                        $principleAmountInTradeCurrency +
                        ($trade->fee * $trade->exchange_rate);

                    // We compute cost2 that won't be affected by the sell actions
                    // The other cost has gains factored in
                    // (if you sold half your stocks for double the value,
                    // the remaining cost becomes 0)
                    $positions[$accountId][$symbol]['quantity2'] +=
                        $trade->quantity;
                    $positions[$accountId][$symbol]['cost2_in_account_currency'] +=
                        $principleAmount + $trade->fee;
                    $positions[$accountId][$symbol]['cost2_in_trade_currency'] +=
                        $principleAmountInTradeCurrency +
                        ($trade->fee * $trade->exchange_rate);

                    break;
                case 'SELL':
                    $positions[$accountId][$symbol]['quantity'] -=
                        $trade->quantity;
                    $positions[$accountId][$symbol]['cost_in_account_currency'] -=
                        $principleAmount - $trade->fee;
                    $positions[$accountId][$symbol]['cost_in_trade_currency'] -=
                        $principleAmountInTradeCurrency -
                        ($trade->fee * $trade->exchange_rate);

                    break;
                default:
                    LOG::warning('Unknown trade action ' . $trade->action
                                 . '! Ignoring trade with id ' . $trade->id);
            }

            $positions[$accountId][$symbol]['trades'][] = $trade;
        }

        return $positions;
    }

    /**
     * @param Collection<Trade> $trades
     *
     * @return array<accountId: array accountData>
     */
    public static function tradesToAccountData(Collection $trades): array
    {
        $accountData = []; // currency & totals grouped by account
        foreach ($trades as $trade) {
            $accountId = $trade->accountModel->id;
            $accountData[$accountId] = [
                'accountModel'                 => $trade->accountModel,
                'total_change'                 => 0,
                'total_change_formatted'       => '',
                'total_cost'                   => 0,
                'total_cost_formatted'         => '',
                'total_market_value'           => 0,
                'total_market_value_formatted' => '',
            ];
        }

        return $accountData;
    }

    /**
     * @param Collection<Trade> $trades
     *
     * @return array<EURUSD: array<account_currency: EUR, trade_currency: EUR>>
     */
    public static function tradesToExchangeRateData(Collection $trades): array
    {
        $exchangeRateData = []; // exchange rates grouped by exchange rate index
        foreach ($trades as $trade) {
            $exchangeRateIndex = $trade->accountModel->currency->iso_code .
                $trade->tradeCurrencyModel->iso_code; // EURUSD

            if (empty($exchangeRateData[$exchangeRateIndex])
                && $trade->accountModel->currency->iso_code !=
                        $trade->tradeCurrencyModel->iso_code
            ) {
                $exchangeRateData[$exchangeRateIndex] = [
                    'account_currency' => $trade->accountModel->currency->iso_code,
                    'trade_currency'   => $trade->tradeCurrencyModel->iso_code,
                ];
            }
        }

        return $exchangeRateData;
    }

    /**
     * @param Collection<Trade> $trades
     *
     * @return array<symbol: 1>
     */
    public static function tradesToSymbols(Collection $trades): array
    {
        $symbols = []; // symbols grouped by symbol
        foreach ($trades as $trade) {
            $symbol = $trade->symbol;
            $symbols[$symbol] = 1;
        }

        return $symbols;
    }

    public static function getTradeCurrency(string $symbol,
        array $position, array $quote = null): ?string
    {
        $currenciesMapping = config('general.currencies_mapping');

        $isUnlisted = FinanceAPI::isUnlisted($symbol);

        // Use quote currency if available, otherwise use position's trade currency
        $tradeCurrency = (!$isUnlisted && !empty($quote))
            ? $quote['currency']
            : $position['tradeCurrencyModel']->iso_code;

        if (!empty($currenciesMapping[$tradeCurrency])) {
            $tradeCurrency = $currenciesMapping[$tradeCurrency];
        }

        if ($tradeCurrency != $position['tradeCurrencyModel']->iso_code) {
            LOG::error("Inconsistent quote trade currency for symbol: "
                . "$symbol, tradeCurrency: "
                . $tradeCurrency . ", positionTradeCurrency: "
                . $position['tradeCurrencyModel']->iso_code
                . '! Ignoring position...');
            return null;
        }

        return $tradeCurrency;
    }

    /**
     * @return number
     */
    public static function getExchangeRate(array $position, array $exchangeRateData)
    {
        if ($position['tradeCurrencyModel']->iso_code ==
            $position['accountModel']->currency->iso_code
        ) {
            return 1;
        }

        $exchangeRateIndex = $position['accountModel']->currency->iso_code
                             . $position['tradeCurrencyModel']->iso_code;

        if (empty($exchangeRateData[$exchangeRateIndex]) ||
           empty($exchangeRateData[$exchangeRateIndex]['exchange_rate'])
        ) {
            LOG::error("Exchange rate not found for account_currency: "
                . $position['accountModel']->currency->iso_code
                . ", trade_currency: "
                . $position['tradeCurrencyModel']->iso_code
                . ", exchangeRateData: "
                . print_r($exchangeRateData, true)
                . '! Ignoring position...');
            return null;
        }

        return $exchangeRateData[$exchangeRateIndex]['exchange_rate'];
    }

    /**
     * @param array &$position
     * @param float $exchangeRate
     */
    public static function addExchangeRate(array &$position, float $exchangeRate): void
    {
        $position['exchange_rate'] = $exchangeRate;
    }

    public static function addMarketUtils(array &$position, array $quote = null)
    {
        $symbol = $position['symbol'];
        $isUnlisted = FinanceAPI::isUnlisted($symbol);

        $position['marketUtils'] = (!$isUnlisted && !empty($quote['marketUtils']))
            ? $quote['marketUtils'] : null;
    }

    public static function addSymbolName(array &$position, array $quote = null)
    {
        $symbol = $position['symbol'];
        $isUnlisted = FinanceAPI::isUnlisted($symbol);

        $position['symbol_name'] = $symbol;
        if ($isUnlisted) {
            $unlistedFMV = config('trades.unlisted_fmv');
            $position['symbol_name'] = !empty($unlistedFMV[$symbol]['symbol_name'])
                ? $unlistedFMV[$symbol]['symbol_name']
                : $symbol;
        } else {
            $position['symbol_name'] = !empty($quote['name'])
                ? $quote['name'] : $symbol;
        }
    }

    public static function getUnlistedFMV(array $unlistedFMVData,
        \DateTimeInterface $date = null): array
    {
        if (empty($date)) {
            $date = new \DateTime();
        }
        $price = 0;
        $priceTimestamp = $date;

        if (empty($unlistedFMVData['quotes'])) {
            return [
                $price,
                $priceTimestamp,
            ];
        }

        $price = $unlistedFMVData['quotes'][0]['price'];
        $priceTimestamp = new \DateTime($unlistedFMVData['quotes'][0]['timestamp']);

        foreach ($unlistedFMVData['quotes'] as $quote) {
            $quoteTimestamp = new \DateTime($quote['timestamp']);
            if ($quoteTimestamp <= $date && $quoteTimestamp > $priceTimestamp) {
                $price = $quote['price'];
                $priceTimestamp = $quoteTimestamp;
            }
        }

        return [
            $price,
            $priceTimestamp,
        ];
    }

    public static function addPrice(array &$position, array $quote = null,
        \DateTimeInterface $date = null)
    {
        $symbol = $position['symbol'];
        $isUnlisted = FinanceAPI::isUnlisted($symbol);

        if ($isUnlisted) {
            $unlistedFMV = config('trades.unlisted_fmv');
            if (!empty($unlistedFMV[$symbol])) {
                list($position['price'], $position['price_timestamp']) =
                    self::getUnlistedFMV($unlistedFMV[$symbol], $date);
            }
        } else {
            if (!empty($quote) && !empty($quote['price'])) {
                $position['price'] = $quote['price'];
                $position['price_timestamp'] = $quote['quote_timestamp'];

                $position['post_market_price'] =
                    !empty($quote['post_market_price'])
                    ? $quote['post_market_price'] : false;
                $position['post_market_day_change'] =
                    !empty($quote['post_market_day_change'])
                    ? $quote['post_market_day_change'] : false;
                $position['post_market_day_change_percentage'] =
                    !empty($quote['post_market_day_change_percentage'])
                    ? $quote['post_market_day_change_percentage'] : false;

                $position['pre_market_price'] = !empty($quote['pre_market_price']) ?
                     $quote['pre_market_price'] : false;
                $position['pre_market_day_change'] =
                    !empty($quote['pre_market_day_change'])
                    ? $quote['pre_market_day_change'] : false;
                $position['pre_market_day_change_percentage'] =
                    !empty($quote['pre_market_day_change_percentage'])
                    ? $quote['pre_market_day_change_percentage'] : false;
            } else {
                $position['price'] = $position['unit_price'];
                $position['price_timestamp'] = new \DateTime();

                // Only warn if symbol is not obsolete or delisted
                $obsoleteSymbols = config('general.obsolete_symbols', []);
                $delistedSymbols = config('trades.delisted_symbols', []);
                if (!in_array($symbol, $obsoleteSymbols)
                    && !in_array($symbol, $delistedSymbols, true)
                ) {
                    LOG::warning("No quote price for symbol $symbol! "
                                 ."Defaulting to unit position price...");
                }
            }
            // LOG::debug("quote: " . print_r($quote, true));
        }

        $position['current_unit_price_in_trade_currency_formatted'] =
            MoneyFormat::get_formatted_balance(
                $position['tradeCurrencyModel']->display_code,
                $position['price']);

        $position['quote_timestamp_formatted'] =
            $position['price_timestamp']
                ->format(trans('myfinance2::general.datetime-format'));
    }

    public static function addDayChange(array &$position, array $quote = null)
    {
        $symbol = $position['symbol'];
        $isUnlisted = FinanceAPI::isUnlisted($symbol);

        if ($isUnlisted) {
            $position['day_change'] = 0;
            $position['day_change_percentage'] = 0;
        } else {
            if (!empty($quote) && !empty($quote['day_change'])) {
                $position['day_change'] = $quote['day_change'];
                $position['pre_market_day_change'] =
                    !empty($quote['pre_market_day_change'])
                    ? $quote['pre_market_day_change'] : false;
            } else {
                $position['day_change'] = 0;

                // Only warn if symbol is not obsolete or delisted
                $obsoleteSymbols = config('general.obsolete_symbols', []);
                $delistedSymbols = config('trades.delisted_symbols', []);
                if (!in_array($symbol, $obsoleteSymbols)
                    && !in_array($symbol, $delistedSymbols, true)
                ) {
                    LOG::warning("No quote day change for symbol $symbol! "
                                 ."Defaulting to 0...");
                }
            }
            if (!empty($quote) && !empty($quote['day_change_percentage'])) {
                $position['day_change_percentage'] = $quote['day_change_percentage'];
                $position['pre_market_day_change_percentage'] =
                    !empty($quote['pre_market_day_change_percentage'])
                    ? $quote['pre_market_day_change_percentage'] : false;
            } else {
                $position['day_change_percentage'] = 0;

                // Only warn if symbol is not obsolete or delisted
                $obsoleteSymbols = config('general.obsolete_symbols', []);
                $delistedSymbols = config('trades.delisted_symbols', []);
                if (!in_array($symbol, $obsoleteSymbols)
                    && !in_array($symbol, $delistedSymbols, true)
                ) {
                    LOG::warning("No quote day change percentage for symbol $symbol! "
                                 ."Defaulting to 0...");
                }
            }
        }

        $position['day_change_in_account_currency'] =
            !$position['quantity']
            ? 0
            : $position['quantity'] * $position['day_change']
              / $position['exchange_rate'];

        $position['day_change_in_account_currency_formatted'] =
            !$position['quantity']
            ? ''
            : MoneyFormat::get_formatted_gain(
                $position['accountModel']->currency->display_code,
                $position['day_change_in_account_currency']);

        $position['day_change_in_percentage_formatted'] =
            !$position['quantity']
            ? ''
            : MoneyFormat::get_formatted_gain_percentage(
                $position['day_change_percentage']);

    }

    public static function addMarketValue(array &$position)
    {
        $position['market_value_in_account_currency'] = $position['price']
            * $position['quantity'] / $position['exchange_rate'];

        $position['market_value_in_account_currency_formatted'] =
            MoneyFormat::get_formatted_balance(
                $position['accountModel']->currency->display_code,
                $position['market_value_in_account_currency']);
    }

    public static function addOverallChange(array &$position)
    {
        $position['overall_change_in_account_currency'] =
            $position['market_value_in_account_currency']
            - $position['cost_in_account_currency'];

        $position['overall_change_in_account_currency_formatted'] =
            MoneyFormat::get_formatted_gain(
                $position['accountModel']->currency->display_code,
                $position['overall_change_in_account_currency']);

        $position['overall_change_in_percentage'] =
            !$position['quantity']
            ? 0
            : -100
              + $position['market_value_in_account_currency']
              * 100
              / $position['cost_in_account_currency'];

        $position['overall_change_in_percentage_formatted'] =
            !$position['quantity']
            ? ''
            : MoneyFormat::get_formatted_gain_percentage(
                $position['overall_change_in_percentage']);
    }

    public static function addCost(array &$position)
    {
        $position['cost_in_account_currency_formatted'] =
            MoneyFormat::get_formatted_balance(
                $position['accountModel']->currency->display_code,
                $position['cost_in_account_currency']);

        $position['average_unit_cost_in_trade_currency'] =
            !$position['quantity']
            ? null
            : $position['cost_in_trade_currency'] / $position['quantity'];

        $position['average_unit_cost_in_trade_currency_formatted'] =
            !$position['quantity']
            ? ''
            : MoneyFormat::get_formatted_balance(
                $position['tradeCurrencyModel']->display_code,
                $position['average_unit_cost_in_trade_currency']);
    }

    public static function addCost2(array &$position)
    {
        // We compute cost2 that won't be affected by the sell actions
        // cost2 and quantity2 only measure BUY actions
        $position['cost2_in_account_currency'] = $position['quantity'] *
            $position['cost2_in_account_currency'] / $position['quantity2'];
        $position['cost2_in_trade_currency'] = $position['quantity'] *
            $position['cost2_in_trade_currency'] / $position['quantity2'];

        #NOTE comparing floats
        $hasCost2 = round($position['cost_in_account_currency'], 4) !=
            round($position['cost2_in_account_currency'], 4);

        $position['cost2_in_account_currency_formatted']  = !$hasCost2
            ? ''
            : MoneyFormat::get_formatted_balance(
                $position['accountModel']->currency->display_code,
                $position['cost2_in_account_currency']);

        $position['average_unit_cost2_in_trade_currency_formatted'] =
            !$position['quantity'] || !$hasCost2
            ? ''
            : MoneyFormat::get_formatted_balance(
                $position['tradeCurrencyModel']->display_code,
                $position['cost2_in_trade_currency'] / $position['quantity']);
    }

    public static function addOverallChange2(array &$position)
    {
        $position['overall_change2_in_account_currency'] =
            $position['market_value_in_account_currency']
            - $position['cost2_in_account_currency'];

        #NOTE comparing floats
        $hasCost2 = round($position['cost_in_account_currency'], 4) !=
            round($position['cost2_in_account_currency'], 4);

        $position['overall_change2_in_account_currency_formatted'] =
            !$hasCost2
            ? ''
            : MoneyFormat::get_formatted_gain(
                $position['accountModel']->currency->display_code,
                $position['overall_change2_in_account_currency']);

        $position['overall_change2_in_percentage_formatted'] =
            (!$position['quantity'] || !$hasCost2)
            ? ''
            : MoneyFormat::get_formatted_gain_percentage(
                -100
                + $position['market_value_in_account_currency']
                * 100
                / $position['cost2_in_account_currency']);
    }

    public static function updateAccountDataTotal(array &$positionAccountData,
        array $position)
    {
        $positionAccountData['total_change'] +=
            $position['overall_change_in_account_currency'];
        $positionAccountData['total_cost'] +=
            $position['cost_in_account_currency'];
        $positionAccountData['total_market_value'] +=
            $position['market_value_in_account_currency'];

        $positionAccountData['total_change_formatted'] =
            MoneyFormat::get_formatted_gain(
                $position['accountModel']->currency->display_code,
                $positionAccountData['total_change']);

        $positionAccountData['total_cost_formatted'] =
            MoneyFormat::get_formatted_balance(
                $position['accountModel']->currency->display_code,
                $positionAccountData['total_cost']);

        $positionAccountData['total_market_value_formatted'] =
            MoneyFormat::get_formatted_balance(
                $position['accountModel']->currency->display_code,
                $positionAccountData['total_market_value']);
    }

    public static function addCashBalancesUtils(array &$positionAccountData,
        CashBalancesUtils $cashBalancesUtils)
    {
        $positionAccountData['cashBalanceUtils'] = $cashBalancesUtils;
    }

    public function getTradeAccountsWithoutOpenPositions(
        array &$accountData, \DateTimeInterface $date = null): array
    {
        $queryBuilder = Account::with('currency');
        if (!$this->_withUser) {
            $queryBuilder = Account::with('currencyNoUser')
                ->withoutGlobalScope(AssignedToUserScope::class);
        }

        $accounts = $queryBuilder
            ->where('is_trade_account', '1')
            ->where('created_at', '<=', !empty($date) ? $date : \DB::raw('NOW()'))
            ->whereNotIn('id', array_keys($accountData))
            ->orderBy('name')
            ->get();

        if (!$this->_withUser) {
            foreach ($accounts as $account) {
                if (!empty($account->currencyNoUser)) {
                    $account->currency = $account->currencyNoUser;
                }
            }
        }

        $groupedAccounts = [];
        foreach ($accounts as $account) {
            $accountId = $account->id;
            $groupedAccounts[$accountId] = $account;
            $accountData[$accountId] = [
                'accountModel'                 => $account,
                'total_change'                 => 0,
                'total_change_formatted'       => '',
                'total_cost'                   => 0,
                'total_cost_formatted'         => '',
                'total_market_value'           => 0,
                'total_market_value_formatted' => '',
                'trade_account_without_open_positions' => true,
            ];
            $cashBalancesUtils = new CashBalancesUtils($accountId,
                $this->_withUser, $date);
            self::addCashBalancesUtils($accountData[$accountId], $cashBalancesUtils);
        }

        return $groupedAccounts;
    }

    /**
     * Execute the job.
     *
     * @param $date \DateTimeInterface
     *
     * @return array (positions => array(positionData))
     */
    public function handle(\DateTimeInterface $date = null) : ?array
    {
        $trades = $this->getTrades($date);
        $positions = self::tradesToPositions($trades);
        $accountData = self::tradesToAccountData($trades);
        $symbols = self::tradesToSymbols($trades);

        $financeUtils = new FinanceUtils();

        $exchangeRateData = self::tradesToExchangeRateData($trades);
        $exchangeRateData = $financeUtils->getExchangeRates($exchangeRateData,
            $date);
        // LOG::debug("exchangeRateData 535: " . print_r($exchangeRateData, true));

        $quoteSymbols = array_merge(
            array_keys(self::tradesToSymbols($trades)),
            $this->_extraSymbols
        );
        $quotes = $financeUtils->getQuotes($quoteSymbols, $date);

        foreach ($positions as $accountId => &$symbols) {
            foreach ($symbols as $symbol => &$position) {
                $isUnlisted = FinanceAPI::isUnlisted($symbol);
                // Check if quote exists (handles delisted, unlisted, or missing quotes)
                $quote = (!$isUnlisted && isset($quotes[$symbol]))
                    ? $quotes[$symbol]
                    : null;

                $tradeCurrency = self::getTradeCurrency($symbol, $position, $quote);
                if (empty($tradeCurrency)) {
                    continue;
                }
                $exchangeRate = self::getExchangeRate($position, $exchangeRateData);
                if (empty($exchangeRate)) {
                    continue;
                }

                self::addExchangeRate($position, $exchangeRate);
                self::addMarketUtils($position, $quote);
                self::addSymbolName($position, $quote);
                self::addPrice($position, $quote, $date);
                self::addDayChange($position, $quote);
                self::addMarketValue($position);
                self::addOverallChange($position);
                self::addCost($position);
                self::addCost2($position);
                self::addOverallChange2($position);
                self::updateAccountDataTotal($accountData[$accountId], $position);
            }

            $cashBalancesUtils = new CashBalancesUtils($accountId,
                $this->_withUser, $date);
            self::addCashBalancesUtils($accountData[$accountId], $cashBalancesUtils);
        }

        $tradeAccountsWithoutOpenPositions =
            $this->getTradeAccountsWithoutOpenPositions($accountData, $date);
        foreach ($tradeAccountsWithoutOpenPositions as $accountId => $data) {
            $cash = $accountData[$accountId]['cashBalanceUtils']->getAmount();
            if (!empty($cash) && $cash > 0) {
                $positions[$accountId] = [];
            }
        }

        // LOG::debug('groupedItems: ' . print_r($positions, true));
        // LOG::debug('accountData: ' . print_r($accountData, true));
        // LOG::debug('tradeAccountsWithoutOpenPositions: '
        //            . print_r($tradeAccountsWithoutOpenPositions, true));

        return [
            'groupedItems' => $positions,
            'accountData'  => $accountData,
            'quotes'       => $quotes,
            'symbols'      => $quoteSymbols,
            'tradeAccountsWithoutOpenPositions' =>
                $tradeAccountsWithoutOpenPositions,
        ];
    }

}

