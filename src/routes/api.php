<?php

Route::group([
    'middleware'    => ['web'],
    'as'            => 'myfinance2::',
    'namespace'     => 'ovidiuro\myfinance2\App\Http\Controllers\Api',
], function ()
{
    Route::get('get-finance-data', 'AjaxController@getFinanceData');
    Route::get('get-cash-balances', 'AjaxController@getCashBalances');
    Route::get('get-currency-exchange-gain-estimate', 'AjaxController@getCurrencyExchangeGainEstimate');
});

