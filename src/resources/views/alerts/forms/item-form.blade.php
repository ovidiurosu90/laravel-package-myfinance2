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

{{-- The target value sits next to its mode and swaps with it (alerts/scripts/relative-target): a FIXED target is
     the target price; a RELATIVE one is the reference ± offset, whose target price the server resolves on save. --}}
@php($relativeTarget = old('target_mode', $target_mode ?? 'FIXED') === 'RELATIVE')
<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::alerts.forms.partials.target_mode-select')
    </div>
    <div class="col-12 col-md-4" id="fixed-target-col" @if ($relativeTarget) style="display: none" @endif>
        @include('myfinance2::alerts.forms.partials.target_price-input')
    </div>
    <div class="col-12 col-md-8" id="relative-target-col" @if (!$relativeTarget) style="display: none" @endif>
        @include('myfinance2::alerts.forms.partials.relative-target-inputs')
    </div>
</div>

<div class="row">
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

@include('myfinance2::general.partials.symbol-chart-panel')
