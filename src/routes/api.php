<?php

Route::group([
    'middleware' => ['web', 'activity', 'checkblocked', 'role:admin|financeadmin'],
    'as'         => 'myfinance2::',
    'namespace'  => 'ovidiuro\myfinance2\App\Http\Controllers\Api',
], function ()
{
    Route::get('get-finance-data', 'AjaxController@getFinanceData');
    Route::get('get-symbol-chart', 'AjaxController@getSymbolChart');
    Route::get('get-cash-balances', 'AjaxController@getCashBalances');
    Route::get('get-currency-exchange-gain-estimate',
                    'AjaxController@getCurrencyExchangeGainEstimate');
    Route::get('get-trades', 'AjaxController@getTrades');
    Route::post('populate-historical-data', 'AjaxController@populateHistoricalData');
});

