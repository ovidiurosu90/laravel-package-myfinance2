<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between;
                    align-items: center;">
            <span id="card_title">
                {!! trans('myfinance2::watchlistsymbols.titles.dashboard') !!}
            </span>
            <div class="float-right">
                <a class="btn btn-sm"
                    href="{{ route('myfinance2::watchlist-symbols.create') }}">
                    <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                    {!! trans('myfinance2::general.buttons.create') !!}
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        @include('myfinance2::watchlistsymbols.tables.items-table')
    </div>
</div>

@if(!empty($items))
    @php $symbols = array_keys($items) @endphp
    @include('myfinance2::general.scripts.quote-price-graphs')
@endif

