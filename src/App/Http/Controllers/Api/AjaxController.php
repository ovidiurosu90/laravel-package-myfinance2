<?php

namespace ovidiuro\myfinance2\App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\FundingDashboard;
use ovidiuro\myfinance2\App\Services\FinanceUtils;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\CashBalancesUtils;
use ovidiuro\myfinance2\App\Services\CurrencyUtils;
use ovidiuro\myfinance2\App\Http\Requests\GetCurrencyExchangeGainEstimate;
use ovidiuro\myfinance2\App\Http\Controllers\MyFinance2Controller;

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
        if ($request->has('account_id')) {
            $availableQuantity = $financeUtils->getAvailableQuantity($symbol,
                $request->account_id,
                $timestamp,
                $request->has('trade_id') ? $request->trade_id : null
            );

            if (is_null($availableQuantity)) {
                return response()->json([
                    'message' => 'Could not get the available quantity!'
                ], 400);
            }
        }

        // Log::debug($financeData);

        return response()->json([
            'price'              => @number_format($financeData['price'], 4),
            'currency'           => $financeData['currency'],
            'name'               => $financeData['name'],
            'quote_timestamp'    => $financeData['quote_timestamp']
                ->format(trans('myfinance2::general.datetime-format')),

            'available_quantity' => $availableQuantity,

            'fiftyTwoWeekHigh'              => $financeData['fiftyTwoWeekHigh'],
            'fiftyTwoWeekHighChangePercent' =>
                $financeData['fiftyTwoWeekHighChangePercent'],
            'fiftyTwoWeekLow'               => $financeData['fiftyTwoWeekLow'],
            'fiftyTwoWeekLowChangePercent'  =>
                $financeData['fiftyTwoWeekLowChangePercent'],
        ]);
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

        $service = new CashBalancesUtils($request->account_id);
        $cashBalances = $service->getCashBalances($timestamp);
        if (is_null($cashBalances)) {
            return response()->json([
                'message' => 'Cash Balances not found!',
            ], 400);
        }

        return response()->json([
            'cash_balances' => $cashBalances,
        ]);
    }
}

