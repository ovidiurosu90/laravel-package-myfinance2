<?php

Route::group([
    // 'web' first, the access gate last (always), the app's own middleware in between (config, 'middleware')
    'middleware' => array_merge(['web'], config('myfinance2.middleware', []), ['can:myfinance2.access']),
    'as'         => 'myfinance2::',
    'prefix'     => config('myfinance2.route_prefix', ''),
    'namespace'  => 'ovidiuro\myfinance2\App\Http\Controllers\Api',
], function ()
{
    Route::get('get-finance-data', 'AjaxController@getFinanceData')->name('get-finance-data');
    Route::get('get-symbol-chart', 'AjaxController@getSymbolChart')->name('get-symbol-chart');
    Route::get('get-cash-balances', 'AjaxController@getCashBalances')->name('get-cash-balances');
    Route::get('get-currency-exchange-gain-estimate', 'AjaxController@getCurrencyExchangeGainEstimate')
        ->name('get-currency-exchange-gain-estimate');
    Route::get('get-trades', 'AjaxController@getTrades')->name('get-trades');
    Route::post('populate-historical-data', 'AjaxController@populateHistoricalData')->name('populate-historical-data');
});

