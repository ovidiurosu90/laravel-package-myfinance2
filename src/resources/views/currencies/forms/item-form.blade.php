{{ csrf_field() }}

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::currencies.forms.partials.iso_code-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::currencies.forms.partials.display_code-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::currencies.forms.partials.name-input')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::currencies.forms.partials.is_ledger_currency-checkbox')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::currencies.forms.partials.is_trade_currency-checkbox')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::currencies.forms.partials.'
                 . 'is_dividend_currency-checkbox')
    </div>
</div>

