{{ csrf_field() }}

<div class="row m-1 currency-exchange-gain-estimator">
    <div class="col-12 col-md-4 pl-0">
        @include('myfinance2::home.forms.partials.debit_currency-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::home.forms.partials.credit_currency-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::home.forms.partials.exchange_rate-input')
    </div>
    <div class="col-12 col-md-4 pl-0">
        @include('myfinance2::home.forms.partials.amount-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::home.forms.partials.fee-input')
    </div>
    <div class="col-12 col-md-4">
        <label class="col-12 control-label pl-0">Estimate</label>
        <button class="btn btn-dark w-100 text-left" id="estimate-gain-button" data-bs-toggle="tooltip" title="Get Currency Exchange Gain Estimate"><i class="fa fa-sign-in"></i> Get Gain</button>
    </div>
</div>

