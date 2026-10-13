{{-- Signal backtest (phase 4, TierSignalBacktestService): how symbols did against VUSA.AS after each tier signal,
     replayed on the user's stored tier history without look-ahead. $report is one parameter cell. --}}
@extends('layouts.app')
@section('template_title', 'Tier Signal Backtest')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-default">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Tier Signal Backtest</span>
                        @include('myfinance2::peakproximityalerts.partials.subnav', ['active' => 'backtest'])
                    </div>
                    <div class="card-body">
                        @include('myfinance2::peakproximityalerts.partials.backtest.params')
                        @include('myfinance2::peakproximityalerts.partials.backtest.summary')
                        @include('myfinance2::peakproximityalerts.partials.backtest.episodes')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
