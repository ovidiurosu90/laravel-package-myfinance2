{{ csrf_field() }}
<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.timestamp-picker')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.action-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.account-select')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.symbol-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.trade_currency-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.exchange_rate-input')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.quantity-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.unit_price-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::trades.forms.partials.fee-input')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-12">
        @include('myfinance2::trades.forms.partials.description-input')
    </div>
</div>

