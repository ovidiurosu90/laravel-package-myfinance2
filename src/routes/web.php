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

    #NOTE Price Alert custom action routes must be before Route::resource
    Route::post('price-alerts/{id}/pause',
                'PriceAlertController@pause')->name('price-alerts.pause');
    Route::post('price-alerts/{id}/resume',
                'PriceAlertController@resume')->name('price-alerts.resume');
    Route::get('price-alerts/history',
               'PriceAlertNotificationController@index')->name('price-alerts.history');
    Route::delete('price-alerts/history/{id}',
                  'PriceAlertNotificationController@destroy')->name('price-alerts.history.destroy');
    Route::post('price-alerts/history/bulk-action',
                'PriceAlertNotificationController@bulkAction')->name('price-alerts.history.bulk-action');
    Route::post('price-alerts/suggest',
                'PriceAlertController@suggest')->name('price-alerts.suggest');
    Route::post('price-alerts/bulk-action',
                'PriceAlertController@bulkAction')->name('price-alerts.bulk-action');

    Route::resource('price-alerts', 'PriceAlertController');

    #NOTE Peak-proximity alerts: opt-in per-symbol management (no create/edit/delete)
    Route::get('peak-proximity-alerts/history',
               'PeakProximityNotificationController@index')->name('peak-proximity-alerts.history');
    Route::delete('peak-proximity-alerts/history/{id}',
                  'PeakProximityNotificationController@destroy')->name('peak-proximity-alerts.history.destroy');
    Route::post('peak-proximity-alerts/history/bulk-action',
                'PeakProximityNotificationController@bulkAction')->name('peak-proximity-alerts.history.bulk-action');
    Route::post('peak-proximity-alerts/history/clear-today',
                'PeakProximityNotificationController@clearToday')->name('peak-proximity-alerts.history.clear-today');
    Route::post('peak-proximity-alerts/history/rerun',
                'PeakProximityNotificationController@rerun')->name('peak-proximity-alerts.history.rerun');
    Route::get('peak-proximity-alerts',
               'PeakProximityAlertController@index')->name('peak-proximity-alerts.index');
    Route::post('peak-proximity-alerts/enable',
                'PeakProximityAlertController@enable')->name('peak-proximity-alerts.enable');
    Route::post('peak-proximity-alerts/disable',
                'PeakProximityAlertController@disable')->name('peak-proximity-alerts.disable');
    Route::post('peak-proximity-alerts/rearm',
                'PeakProximityAlertController@rearm')->name('peak-proximity-alerts.rearm');
    Route::get('peak-proximity-alerts/inbox',
               'PeakProximityAlertController@inbox')->name('peak-proximity-alerts.inbox');
    Route::post('peak-proximity-alerts/dismiss',
                'PeakProximityAlertController@dismiss')->name('peak-proximity-alerts.dismiss');
    Route::post('peak-proximity-alerts/dismiss-all',
                'PeakProximityAlertController@dismissAll')->name('peak-proximity-alerts.dismiss-all');

    #NOTE Dip Buying Plan: settings, the self-validation backtest report and email history
    Route::get('dip-buying-alerts/backtest',
               'DipBuyingBacktestController@index')->name('dip-buying-alerts.backtest');
    Route::get('dip-buying-alerts/history',
               'DipBuyingNotificationController@index')->name('dip-buying-alerts.history');
    Route::delete('dip-buying-alerts/history/{id}',
                  'DipBuyingNotificationController@destroy')->name('dip-buying-alerts.history.destroy');
    Route::post('dip-buying-alerts/history/bulk-action',
                'DipBuyingNotificationController@bulkAction')->name('dip-buying-alerts.history.bulk-action');
    Route::get('dip-buying-alerts',
               'DipBuyingAlertController@index')->name('dip-buying-alerts.index');
    Route::post('dip-buying-alerts',
                'DipBuyingAlertController@save')->name('dip-buying-alerts.save');

    Route::get('portfolio-peak-alerts/history',
               'PortfolioPeakNotificationController@index')->name('portfolio-peak-alerts.history');
    Route::delete('portfolio-peak-alerts/history/{id}',
                  'PortfolioPeakNotificationController@destroy')
        ->name('portfolio-peak-alerts.history.destroy');
    Route::post('portfolio-peak-alerts/history/bulk-action',
                'PortfolioPeakNotificationController@bulkAction')
        ->name('portfolio-peak-alerts.history.bulk-action');
    Route::get('portfolio-peak-alerts',
               'PortfolioPeakAlertController@index')->name('portfolio-peak-alerts.index');
    Route::post('portfolio-peak-alerts',
                'PortfolioPeakAlertController@save')->name('portfolio-peak-alerts.save');

    #NOTE Orders custom action routes must be before Route::resource
    Route::post('orders/{id}/place',
                'OrdersController@place')->name('orders.place');
    Route::post('orders/{id}/fill',
                'OrdersController@fill')->name('orders.fill');
    Route::post('orders/{id}/expire',
                'OrdersController@expire')->name('orders.expire');
    Route::post('orders/{id}/expire-and-clone',
                'OrdersController@expireAndClone')->name('orders.expire-and-clone');
    Route::post('orders/{id}/cancel',
                'OrdersController@cancel')->name('orders.cancel');
    Route::post('orders/{id}/reopen',
                'OrdersController@reopen')->name('orders.reopen');
    Route::post('orders/{id}/link-trade',
                'OrdersController@linkTrade')->name('orders.link-trade');
    Route::post('orders/{id}/unlink-trade',
                'OrdersController@unlinkTrade')->name('orders.unlink-trade');
    Route::get('orders/open-alerts-for-symbol',
               'OrdersController@openAlertsForSymbol')->name('orders.open-alerts-for-symbol');

    Route::resource('orders', 'OrdersController');

    #NOTE Stock split custom action routes must be before static routes to avoid ambiguity
    Route::get('stock-splits/{id}/revert-preview',
               'StockSplitsController@revertPreview')->name('stock-splits.revert-preview');
    Route::post('stock-splits/{id}/revert',
                'StockSplitsController@revert')->name('stock-splits.revert');
    Route::post('stock-splits/{id}/reapply',
                'StockSplitsController@reapply')->name('stock-splits.reapply');

    Route::get('stock-splits', 'StockSplitsController@index')->name('stock-splits.index');
    Route::get('stock-splits/create', 'StockSplitsController@create')->name('stock-splits.create');
    Route::post('stock-splits', 'StockSplitsController@store')->name('stock-splits.store');

    Route::resource('currencies', 'CurrenciesController');
    Route::resource('accounts', 'AccountsController');
    Route::resource('ledger-transactions', 'LedgerTransactionsController');
    Route::resource('trades', 'TradesController');
    Route::resource('cash-balances', 'CashBalancesController');
    Route::resource('dividends', 'DividendsController');
    Route::resource('watchlist-symbols', 'WatchlistSymbolsController');

    Route::post('symbol-tier-overrides',
                'SymbolTierOverrideController@store')->name('symbol-tier-overrides.store');
    Route::delete('symbol-tier-overrides/{symbol}',
                  'SymbolTierOverrideController@destroy')->name('symbol-tier-overrides.destroy');

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

