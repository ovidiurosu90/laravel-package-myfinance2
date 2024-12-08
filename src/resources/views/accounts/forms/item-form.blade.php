{{ csrf_field() }}

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::accounts.forms.partials.currency-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::accounts.forms.partials.name-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::accounts.forms.partials.description-input')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::accounts.forms.partials.is_ledger_account-checkbox')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::accounts.forms.partials.is_trade_account-checkbox')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::accounts.forms.partials.is_dividend_account-checkbox')
    </div>
</div>

