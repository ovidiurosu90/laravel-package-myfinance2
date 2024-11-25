<?php

Route::group([
    'middleware'    => ['web'],
    'as'            => 'myfinance2::',
    'namespace'     => 'ovidiuro\myfinance2\App\Http\Controllers',
], function ()
{
    #NOTE These have to be before Route::resource
    Route::patch('trades/{id}/close', 'TradesController@close')->name('trades.close');
    Route::patch('trades/close-symbol', 'TradesController@closeSymbol')->name('trades.close-symbol');

    Route::resource('ledger-transactions', 'LedgerTransactionsController');
    Route::resource('trades', 'TradesController');
    Route::resource('cash-balances', 'CashBalancesController');
    Route::resource('dividends', 'DividendsController');
    Route::resource('watchlist-symbols', 'WatchlistSymbolsController');

    Route::get('/', 'HomeController@index')->name('home');
    Route::get('positions', 'PositionsController@index');
    Route::get('funding', 'FundingController@index');
    Route::get('timeline', 'TimelineController@index')->name('timeline');
});

