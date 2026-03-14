<?php

Route::group([
    'middleware' => ['web', 'activity', 'checkblocked', 'role:admin|financeadmin'],
    'as'         => 'myfinance2::',
    'namespace'  => 'ovidiuro\myfinance2\App\Http\Controllers',
], function ()
{
    #NOTE These have to be before Route::resource
    Route::patch('trades/{id}/close',
                 'TradesController@close')->name('trades.close');
    Route::patch('trades/close-symbol',
                 'TradesController@closeSymbol')->name('trades.close-symbol');

    #NOTE Orders custom action routes must be before Route::resource
    Route::post('orders/{id}/place',
                'OrdersController@place')->name('orders.place');
    Route::post('orders/{id}/fill',
                'OrdersController@fill')->name('orders.fill');
    Route::post('orders/{id}/expire',
                'OrdersController@expire')->name('orders.expire');
    Route::post('orders/{id}/cancel',
                'OrdersController@cancel')->name('orders.cancel');
    Route::post('orders/{id}/reopen',
                'OrdersController@reopen')->name('orders.reopen');
    Route::post('orders/{id}/link-trade',
                'OrdersController@linkTrade')->name('orders.link-trade');
    Route::post('orders/{id}/unlink-trade',
                'OrdersController@unlinkTrade')->name('orders.unlink-trade');

    Route::resource('orders', 'OrdersController');

    Route::resource('currencies', 'CurrenciesController');
    Route::resource('accounts', 'AccountsController');
    Route::resource('ledger-transactions', 'LedgerTransactionsController');
    Route::resource('trades', 'TradesController');
    Route::resource('cash-balances', 'CashBalancesController');
    Route::resource('dividends', 'DividendsController');
    Route::resource('watchlist-symbols', 'WatchlistSymbolsController');

    Route::get('finance-home', 'HomeController@index')->name('home');
    Route::get('positions', 'PositionsController@index');
    Route::get('returns', 'ReturnsController@index')->name('returns.index');
    Route::get('returns/refreshing', 'ReturnsController@refreshing')->name('returns.refreshing');
    Route::get('returns/refresh-status', 'ReturnsController@refreshStatus')->name('returns.refresh-status');
    Route::post('returns/clear-cache', 'ReturnsController@clearCache')->name('returns.clear-cache');
    Route::get('funding', 'FundingController@index');
    Route::get('timeline', 'TimelineController@index')->name('timeline');
    Route::get('overview', 'OverviewController@index')->name('overview');
});

