<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\FundingDashboard;
use ovidiuro\myfinance2\App\Services\FinanceUtils;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\CashBalancesUtils;
use ovidiuro\myfinance2\App\Services\CurrencyUtils;
use ovidiuro\myfinance2\App\Services\OrderSuggestion;
use ovidiuro\myfinance2\App\Services\ChartsBuilder;
use ovidiuro\myfinance2\App\Services\Stats;
use ovidiuro\myfinance2\App\Http\Requests\GetCurrencyExchangeGainEstimate;
use ovidiuro\myfinance2\App\Http\Controllers\MyFinance2Controller;
use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Trade;

class AjaxController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFinanceData(Request $request)
    {
        if (!$request->has('symbol') || !$request->symbol) {
            return response()->json([
                'message' => 'Missing parameter symbol!'
            ], 422);
        }
        $symbol = $request->symbol;

        $timestamp = $request->has('timestamp') ? $request->timestamp : null;

        $financeUtils = new FinanceUtils();
        $financeData = $financeUtils->getFinanceDataBySymbol($symbol, $timestamp);
        if (is_null($financeData)) {
            return response()->json(['message' => 'Finance data not found!'], 400);
        }

        $availableQuantity = null;
        if ($request->has('account_id') && !empty($request->account_id)) {
            $availableQuantity = $financeUtils->getAvailableQuantity($symbol,
                (int) $request->account_id,
                $timestamp,
                $request->has('trade_id') ? (int) $request->trade_id : null
            );

            if (is_null($availableQuantity)) {
                return response()->json([
                    'message' => 'Could not get the available quantity!'
                ], 400);
            }
        }

        // Log::debug($financeData);

        $qtySums      = Trade::where('symbol', $symbol)
            ->whereIn('action', ['BUY', 'SELL'])
            ->selectRaw('action, SUM(quantity) as total')
            ->groupBy('action')
            ->pluck('total', 'action');
        $openQuantity = (float) max(0, ($qtySums['BUY'] ?? 0) - ($qtySums['SELL'] ?? 0));

        // Yahoo reports some currencies under non-canonical codes (e.g. London pence
        // as "GBp"); normalize to the stored trade-currency code ("GBX") so the
        // exchange-rate lookup, trade-currency match, and reason text line up. The
        // rate fetch reverse-maps GBX -> GBp internally and returns it keyed by GBX.
        $currenciesMapping      = config('general.currencies_mapping');
        $currency               = $currenciesMapping[$financeData['currency']] ?? $financeData['currency'];
        $financeData['currency'] = $currency;

        $eurRate  = 1.0;
        if ($currency !== 'EUR') {
            $rateKey = 'EUR' . $currency;
            $rates   = $financeUtils->getExchangeRates([
                $rateKey => ['account_currency' => 'EUR', 'trade_currency' => $currency],
            ]);
            if (!empty($rates[$rateKey]['exchange_rate'])) {
                $eurRate = (float) $rates[$rateKey]['exchange_rate'];
            }
        }

        $accountCurrency    = null;
        $suggestedAccountId = null;

        if ($request->filled('account_id')) {
            $account = Account::with('currency')->find((int) $request->account_id);
            if ($account) {
                $accountCurrency = $account->currency->iso_code;
            }
        } else {
            $suggestedAccountId = $this->_suggestAccountForCurrency($currency);
            if ($suggestedAccountId) {
                $account = Account::with('currency')->find($suggestedAccountId);
                if ($account) {
                    $accountCurrency = $account->currency->iso_code;
                }
            }
        }

        $suggestion = (new OrderSuggestion())->compute(
            $financeData,
            $openQuantity,
            $eurRate,
            $accountCurrency,
            $availableQuantity
        );
        $suggestion['suggested_account_id'] = $suggestedAccountId;
        $suggestion['account_currency']     = $accountCurrency;

        // Exchange rate to prefill in the form: only when currencies differ and rate was fetched
        if ($currency !== 'EUR' && $accountCurrency && $accountCurrency !== $currency) {
            $suggestion['exchange_rate'] = round($eurRate, 4);
        }

        return response()->json([
            'price'           => round($financeData['price'], 2),
            // Current (last) price for the range-bar marker: reflects pre/post-market so it matches
            // the live price in the quote header. Falls back to the regular price when unavailable.
            'current_price'   => round($financeData['current_price'] ?? $financeData['price'], 2),
            'currency'        => $currency,
            'name'            => $financeData['name'],
            'quote_timestamp' => $financeData['quote_timestamp']
                ->format(trans('myfinance2::general.datetime-format')),

            'available_quantity' => $availableQuantity,
            'suggestion'         => $suggestion,

            'fiftyTwoWeekHigh'              => $financeData['fiftyTwoWeekHigh'],
            'fiftyTwoWeekHighChangePercent' =>
                $financeData['fiftyTwoWeekHighChangePercent'],
            'fiftyTwoWeekLow'               => $financeData['fiftyTwoWeekLow'],
            'fiftyTwoWeekLowChangePercent'  =>
                $financeData['fiftyTwoWeekLowChangePercent'],

            // Closing-based 52-week range (primary) shown alongside the intraday high/low.
            'closingHigh'     => $financeData['closingHigh'] ?? null,
            'closingHighDate' => $financeData['closingHighDate'] ?? null,
            'closingLow'      => $financeData['closingLow'] ?? null,
            'closingLowDate'  => $financeData['closingLowDate'] ?? null,
        ]);
    }

    /**
     * Symbol chart panel data: the stored chart series, a rendered quote header
     * (pre/post + at close, with green/red change), the current price, day gain
     * and 52-week range. Used by the orders form to show the same content as the
     * positions/watchlist "expand chart" modal for the chosen symbol.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSymbolChart(Request $request)
    {
        if (!$request->filled('symbol')) {
            return response()->json(['message' => 'Missing parameter symbol!'], 422);
        }
        $symbol = strtoupper(trim($request->symbol));

        $financeUtils = new FinanceUtils();
        $quotes = $financeUtils->getQuotes([$symbol]);
        if (empty($quotes[$symbol])) {
            return response()->json(['message' => 'Finance data not found!'], 400);
        }
        $q = $quotes[$symbol];

        // Normalize Yahoo's non-canonical currency codes (e.g. London pence "GBp") to the stored
        // trade-currency code ("GBX") before resolving the display code, the same way
        // getFinanceDataBySymbol's caller does, so both chart surfaces label the range bar
        // consistently. GBp and GBX are the same unit (pence), so the numeric figures are unchanged.
        $currenciesMapping = config('general.currencies_mapping');
        $q['currency']     = $currenciesMapping[$q['currency']] ?? $q['currency'];

        $currencyModel = (new CurrencyUtils(true))->getCurrencyByIsoCode($q['currency']);
        $displayCode   = $currencyModel ? $currencyModel->display_code : $q['currency'];

        $datetimeFormat = trans('myfinance2::general.datetime-format');
        $fmtTs = function ($ts) use ($datetimeFormat)
        {
            return ($ts instanceof \DateTimeInterface) ? $ts->format($datetimeFormat) : null;
        };

        $quoteHeader = view('myfinance2::general.partials.quote-header', [
            'currency'           => $displayCode,
            'price'              => $q['price'] ?? null,
            'timestamp'          => $fmtTs($q['quote_timestamp'] ?? null),
            'isPreMarket'        => !empty($q['pre_market_price']),
            'isPostMarket'       => !empty($q['post_market_price']),
            'dayChange'          => $q['day_change'] ?? null,
            'dayChangePct'       => $q['day_change_percentage'] ?? null,
            'regularPrice'       => $q['regular_market_price'] ?? null,
            'regularTimestamp'   => $fmtTs($q['regular_market_timestamp'] ?? null),
            'regularDayChange'   => $q['regular_market_day_change'] ?? null,
            'regularDayChangePct'=> $q['regular_market_day_change_pct'] ?? null,
            'postChange'         => $q['post_market_change'] ?? null,
            'postChangePct'      => $q['post_market_change_pct'] ?? null,
            'marketOpen'         => isset($q['marketUtils']) && $q['marketUtils']->isOpen(),
        ])->render();

        // Prefer the precomputed chart file. Deciding whether it is stale only needs
        // this symbol's latest data date, fetched with a cheap indexed query, so the
        // hot path never loads the full stats_* tables. The expensive live build runs
        // only when the cached file is actually behind (e.g. its position was closed
        // so the cron stopped rebuilding it), and then self-heals the file and warns.
        $series     = ChartsBuilder::getChartSymbolAsArray($symbol);
        $storedLast = !empty($series) ? (end($series)['time'] ?? null) : null;
        $liveLast   = Stats::getLatestSeriesDate($symbol);

        $isStale = $liveLast !== null && ($storedLast === null || $storedLast < $liveLast);

        if ($isStale) {
            Log::warning('getSymbolChart: stale chart cache self-healed for ' . $symbol
                . ' (cached last point ' . ($storedLast ?? 'none')
                . ', latest stats point ' . $liveLast . ').');
            // Rewrite the cached file from fresh stats so every consumer of the JSON
            // (not just this endpoint) sees the up-to-date series next time.
            ChartsBuilder::buildChartSymbol($symbol, Stats::getQuoteStats($symbol));
            $series = ChartsBuilder::getLiveChartSymbolAsArray($symbol);
        }

        // Pin the chart's final point to the live quote shown in the header so the
        // chart tip and the header agree. $q['price'] is the latest known price
        // including pre/post-market, dated by $q['quote_timestamp']; the series ends on
        // the last persisted close, so this overwrites or appends the live price.
        $series = ChartsBuilder::pinLiveQuote(
            $series,
            $q['price'] ?? null,
            ($q['quote_timestamp'] ?? null) instanceof \DateTimeInterface ? $q['quote_timestamp'] : null
        );

        // Flag gaps in the series larger than expected non-trading days (weekends,
        // plus a single-holiday tolerance). Missing trading days usually signal
        // incomplete price history, so warn in the logs and the UI.
        $gaps       = ChartsBuilder::detectSeriesGaps($series);
        $gapWarning = null;
        if (!empty($gaps)) {
            $totalMissing = array_sum(array_column($gaps, 'missing'));
            $widest       = $gaps[0];
            foreach ($gaps as $gap) {
                if ($gap['missing'] > $widest['missing']) {
                    $widest = $gap;
                }
            }
            $gapWarning = 'Price history has gaps: ' . $totalMissing
                . ' trading day(s) missing across ' . count($gaps) . ' gap(s); '
                . 'widest from ' . $widest['from'] . ' to ' . $widest['to']
                . ' (' . $widest['missing'] . ' missing).';
            Log::warning('getSymbolChart gap check for ' . $symbol . ': ' . $gapWarning);
        }

        // Closing-based 52-week range for the range bar's primary high/low (Yahoo's intraday
        // figures stay as the secondary). Null fields when there is no usable history.
        $closing = $financeUtils->closingExtremesNative($symbol);

        return response()->json([
            'name'         => $q['name'],
            'currency'     => html_entity_decode($displayCode, ENT_QUOTES | ENT_HTML5),
            'series'       => $series,
            'stale'        => $isStale,
            'gap_warning'  => $gapWarning,
            'quote_header' => $quoteHeader,
            // Raw price (number) for the 52W range bar; the formatted price is
            // already shown in the quote header.
            'price'        => $q['regular_market_price'] ?? $q['price'] ?? null,
            // Current (last) price for the range-bar marker: $q['price'] already includes
            // pre/post-market, so the marker matches the live price in the quote header.
            'current_price' => $q['price'] ?? $q['regular_market_price'] ?? null,

            'fiftyTwoWeekHigh'              => $q['fiftyTwoWeekHigh'] ?? null,
            'fiftyTwoWeekHighChangePercent' => $q['fiftyTwoWeekHighChangePercent'] ?? null,
            'fiftyTwoWeekLow'               => $q['fiftyTwoWeekLow'] ?? null,
            'fiftyTwoWeekLowChangePercent'  => $q['fiftyTwoWeekLowChangePercent'] ?? null,

            // Closing-based 52-week range (primary) shown alongside the intraday high/low.
            'closingHigh'     => $closing['high'] ?? null,
            'closingHighDate' => $closing['high_date'] ?? null,
            'closingLow'      => $closing['low'] ?? null,
            'closingLowDate'  => $closing['low_date'] ?? null,
        ]);
    }

    /**
     * Find the trade account with the highest cash balance for a given currency ISO code.
     */
    private function _suggestAccountForCurrency(string $currencyIsoCode): ?int
    {
        $accounts = Account::with('currency')
            ->where('is_trade_account', 1)
            ->whereHas('currency', fn($q) => $q->where('iso_code', $currencyIsoCode))
            ->get();

        $bestAccountId = null;
        $bestBalance   = null;

        foreach ($accounts as $account) {
            $balance = (new CashBalancesUtils($account->id, null, $account))->getAmount();
            if ($balance !== null && ($bestBalance === null || $balance > $bestBalance)) {
                $bestBalance   = $balance;
                $bestAccountId = $account->id;
            }
        }

        return $bestAccountId;
    }

    /**
     * @param GetCurrencyExchangeGainEstimate $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrencyExchangeGainEstimate(
        GetCurrencyExchangeGainEstimate $request
    ) {
        $currencyUtilsService = new CurrencyUtils(true);
        $service = new FundingDashboard();
        $currencyExchanges = $service->getCurrencyExchanges(
            $request->debit_currency,
            $request->credit_currency,
            [
                'exchange_rate' => $request->exchange_rate,
                'amount'        => $request->amount,
                'fee'           => $request->fee,
            ]);

        $estimatedGain = $currencyExchanges['estimated_gain'];
        $estimatedGain = array_merge($estimatedGain, [
            'formatted_cost'          => MoneyFormat::get_formatted_gain(
                $currencyUtilsService->getCurrencyByIsoCode(
                    $request->credit_currency)->display_code,
                -abs($estimatedGain['cost'])),
            'formatted_amount'        => MoneyFormat::get_formatted_gain(
                $currencyUtilsService->getCurrencyByIsoCode(
                    $request->credit_currency)->display_code,
                $estimatedGain['amount']),
            'formatted_credit_amount' => MoneyFormat::get_formatted_gain(
                $currencyUtilsService->getCurrencyByIsoCode(
                    $request->credit_currency)->display_code,
                $estimatedGain['credit_amount']),
            'formatted_gain'          => MoneyFormat::get_formatted_gain(
                $currencyUtilsService->getCurrencyByIsoCode(
                    $request->credit_currency)->display_code,
                $estimatedGain['gain']),
        ]);
        return response()->json($estimatedGain);
    }

    /**
     * Return all OPEN trades for a symbol (current user scope).
     * Used by the Stock Splits create form to preview what will be updated.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTrades(Request $request)
    {
        if (!$request->filled('symbol')) {
            return response()->json(['message' => 'Missing parameter symbol!'], 422);
        }

        $symbol = strtoupper(trim($request->symbol));

        $trades = Trade::where('symbol', $symbol)
            ->with(['accountModel', 'tradeCurrencyModel'])
            ->orderBy('timestamp', 'desc')
            ->get();

        $result = $trades->map(function ($trade)
        {
            $account      = $trade->accountModel?->name ?? '—';
            $currency     = $trade->tradeCurrencyModel
                ? strip_tags(html_entity_decode($trade->tradeCurrencyModel->display_code, ENT_HTML5, 'UTF-8'))
                : '';

            return [
                'id'             => $trade->id,
                'account'        => $account,
                'account_id'     => $trade->account_id,
                'date'           => $trade->timestamp?->format('Y-m-d') ?? '',
                'action'         => $trade->action,
                'quantity'       => (float) $trade->quantity,
                'unit_price'     => (float) $trade->unit_price,
                'trade_currency' => $currency,
            ];
        });

        return response()->json([
            'symbol' => $symbol,
            'trades' => $result,
        ]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCashBalances(Request $request)
    {
        if (!$request->has('account_id') || !$request->account_id
            || !is_numeric($request->account_id)
        ) {
            return response()->json([
                'message' => 'Missing or invalid parameter account_id!',
            ], 422);
        }
        $timestamp = $request->has('timestamp') ? $request->timestamp : null;

        $service = new CashBalancesUtils((int) $request->account_id);
        $cashBalances = $service->getCashBalances($timestamp);
        if (is_null($cashBalances)) {
            return response()->json([
                'message' => 'Cash Balances not found!',
            ], 400);
        }

        return response()->json([
            'cash_balances' => $cashBalances,
            'last_operation_timestamp' => $service->getLastOperationTimestamp()
                ->add(new \DateInterval('PT1S')) // adding 1 second
                ->format(trans('myfinance2::general.datetime-format')),
        ]);
    }
}

