@extends('layouts.app')
@section('template_title'){!! trans('myfinance2::splits.titles.dashboard') !!}@endsection
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        {!! trans('myfinance2::splits.titles.dashboard') !!}
                        <div class="pull-right">
                            <a href="{{ route('myfinance2::stock-splits.create') }}"
                               class="btn btn-success btn-sm float-right"
                               data-bs-toggle="tooltip" data-bs-placement="left"
                               title="Record a new stock split">
                                <i class="fa fa-fw fa-plus" aria-hidden="true"></i> Record Split
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('myfinance2::splits.tables.items-table')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::splits.scripts.datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection
