@php $currency = !empty(app('request')->input('currency_iso_code'))
    ? app('request')->input('currency_iso_code') : 'EUR' @endphp

<div class="position-relative">
    <div class="form-group row">
        <div class="col">
            <input id="toggle-currency-select" type="checkbox"
                {{ $currency == 'EUR' ? 'checked' : '' }}
                data-bs-toggle="toggle"
                data-onlabel="Euro (&euro;)" data-offlabel="US Dollar (&dollar;)" />
        </div>
        <div class="col pt-1 fs-5">
            <span id="currency_exchange-status" data-bs-toggle="tooltip"
                title=""></span>
        </div>
        <div class="col-6 pt-1 fs-5 fw-bold text-center">
            <span id="mvalue-status"></span> -
            <span id="cost-status"></span> =
            <span id="change-status"></span>
        </div>
        <div class="col pt-1 fs-5 fw-bold text-end" id="cash-status"></div>
    </div>
    <div id="chart-userOverview" data-currency_iso_code="{{ $currency }}"></div>
</div>

