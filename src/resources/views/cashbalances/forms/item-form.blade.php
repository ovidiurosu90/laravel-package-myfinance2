{{ csrf_field() }}
<div class="row">
    <div class="col-12 col-md-3">
        @include('myfinance2::cashbalances.forms.partials.timestamp-picker')
    </div>
    <div class="col-12 col-md-3">
        @include('myfinance2::cashbalances.forms.partials.account-select')
    </div>
    <div class="col-12 col-md-3">
        @include('myfinance2::cashbalances.forms.partials.account_currency-select')
    </div>
    <div class="col-12 col-md-3">
        @include('myfinance2::cashbalances.forms.partials.amount-input')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-12">
        @include('myfinance2::cashbalances.forms.partials.description-input')
    </div>
</div>

