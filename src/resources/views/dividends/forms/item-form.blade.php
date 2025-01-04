{{ csrf_field() }}
<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::dividends.forms.partials.timestamp-picker')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::dividends.forms.partials.account-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::dividends.forms.partials.symbol-input')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::dividends.forms.partials.dividend_currency-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::dividends.forms.partials.exchange_rate-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::dividends.forms.partials.amount-input')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::dividends.forms.partials.fee-input')
    </div>
    <div class="col-6 col-md-8">
        @include('myfinance2::dividends.forms.partials.description-input')
    </div>
</div>

