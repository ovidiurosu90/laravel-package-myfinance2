@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 text-center">
            <div class="card">
                <div class="card-body py-5">
                    <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h4 class="mb-2">Refreshing Returns Data</h4>
                    <p class="text-muted mb-0">
                        Recalculating returns for all years. This may take a minute&hellip;
                    </p>
                    <p class="text-muted mt-3 mb-0" id="refresh-elapsed" style="font-size: 0.85rem;"></p>
                    <p class="text-danger mt-3 mb-0 d-none" id="refresh-timeout-msg" style="font-size: 0.85rem;">
                        This is taking longer than expected.
                        You can <a href="{{ route('myfinance2::returns.index', ['year' => $selectedYear]) }}">
                            go to the returns page
                        </a> and it will load once the background refresh finishes.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('footer_scripts')
    @include('myfinance2::returns.scripts.refreshing')
@endsection
