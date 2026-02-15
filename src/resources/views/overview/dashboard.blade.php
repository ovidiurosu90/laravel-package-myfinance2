@extends('layouts.app')

@section('template_title')
    {!! trans('myfinance2::overview.titles.dashboard') !!}
@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection

@section('content')

    <div class="container-fluid">

        {{-- Row 1: Funding Sources | Open Positions --}}
        <div class="row">
            @include('myfinance2::overview.cards.funding-sources')
            @include('myfinance2::overview.cards.investment-accounts')
        </div>

        <div class="clearfix mb-2"></div>

        {{-- Row 2: Other / Uncategorized Accounts --}}
        <div class="row">
            @include('myfinance2::overview.cards.other-accounts')
        </div>

        <div class="clearfix mb-2"></div>

        {{-- Row 3: Gains Per Year | Top Winners --}}
        <div class="row">
            @include('myfinance2::overview.cards.gains-per-year')
            @include('myfinance2::overview.cards.top-winners')
        </div>

        <div class="clearfix mb-2"></div>

        {{-- Row 4: Transfer Details (collapsed) --}}
        <div class="row">
            @include('myfinance2::overview.cards.transferred-positions')
        </div>

        <div class="clearfix mb-2"></div>

        {{-- Row 5: Returns Overview (All Years) --}}
        <div class="row">
            <div class="col-12">
                @include('myfinance2::returns.returns-overview')
            </div>
        </div>

    </div>

@endsection

@section('footer_scripts')
    @include('myfinance2::general.scripts.tooltips')
    @include('myfinance2::returns.scripts.returns-overview-graph')
@endsection

