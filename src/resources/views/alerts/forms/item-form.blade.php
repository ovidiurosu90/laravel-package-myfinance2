{{ csrf_field() }}
<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::alerts.forms.partials.symbol-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::alerts.forms.partials.alert_type-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::alerts.forms.partials.status-select')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::alerts.forms.partials.target_price-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::alerts.forms.partials.trade_currency-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::alerts.forms.partials.expires_at-picker')
    </div>
</div>

<div class="row">
    <div class="col-12">
        @include('myfinance2::alerts.forms.partials.notes-input')
    </div>
</div>
