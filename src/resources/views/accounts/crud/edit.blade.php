@extends('layouts.app')

@section('template_title'){{ trans('myfinance2::general.titles.edit-item',
                                   ['type' => 'Account']) }}@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection

@section('content')

    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card card-post" id="post_card">
                    <div class="card-header">
                        {!! trans('myfinance2::general.titles.edit-item',
                                  ['type' => 'Account']) !!}
                        <div class="pull-right">
                            <a href="{{ url('accounts') }}"
                               class="btn btn-outline-secondary btn-sm float-right"
                               data-bs-toggle="tooltip" data-placement="left"
                               title="Back to {{ trans('myfinance2::accounts.'
                                                 . 'titles.dashboard') }} Dashboard">
                                <i class="fa fa-fw fa-reply-all" aria-hidden="true">
                                </i>
                                Back to Index
                            </a>
                        </div>
                    </div>
                    @include('myfinance2::accounts.forms.edit-item-form')
                </div>
            </div>
        </div>
    </div>

@endsection

@section('footer_scripts')
    @include('myfinance2::accounts.scripts.selectize-item')
    @include('myfinance2::general.scripts.tooltips')
@endsection

