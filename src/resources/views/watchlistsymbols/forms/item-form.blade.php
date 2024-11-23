{{ csrf_field() }}
<div class="row">
    <div class="col-12 col-md-6 row">
            <div class="col-6 col-md-6">
                @include('myfinance2::watchlistsymbols.forms.partials.timestamp-picker')
            </div>
            <div class="col-6 col-md-6">
                @include('myfinance2::watchlistsymbols.forms.partials.symbol-input')
            </div>
            <div class="col-12 col-md-12">
                @include('myfinance2::watchlistsymbols.forms.partials.description-input')
            </div>
    </div>
    <div class="col-12 col-md-6 bg-light pt-3" id="fetched-symbol-data"></div>
</div>

