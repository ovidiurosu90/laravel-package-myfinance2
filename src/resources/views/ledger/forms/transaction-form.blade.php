{{ csrf_field() }}
<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::ledger.forms.partials.transaction.timestamp-picker')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::ledger.forms.partials.transaction.type-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::ledger.forms.partials.transaction.parent-select')
    </div>
</div>
<div class="row">
    <div class="col-12 col-md-4">
    @include('myfinance2::ledger.forms.partials.transaction.debit_account-select')
    </div>
    <div class="col-12 col-md-4">
    @include('myfinance2::ledger.forms.partials.transaction.credit_account-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::ledger.forms.partials.transaction.exchange_rate-input')
    </div>
</div>
<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::ledger.forms.partials.transaction.amount-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::ledger.forms.partials.transaction.fee-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::ledger.forms.partials.transaction.description-input')
    </div>
</div>

