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

        $currency = $financeData['currency'];
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
            $accountCurrency
        );
        $suggestion['suggested_account_id'] = $suggestedAccountId;
        $suggestion['account_currency']     = $accountCurrency;

        // Exchange rate to prefill in the form: only when currencies differ and rate was fetched
        if ($currency !== 'EUR' && $accountCurrency && $accountCurrency !== $currency) {
            $suggestion['exchange_rate'] = round($eurRate, 4);
        }

        return response()->json([
            'price'           => round($financeData['price'], 2),
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

