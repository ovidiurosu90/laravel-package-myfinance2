@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
{{ csrf_field() }}
@if (empty($id))
<div id="smart-prefill-suggestion" class="alert py-2 px-3 mb-3 small"
    style="display: none"></div>
@endif
<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::orders.forms.partials.symbol-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::orders.forms.partials.action-select')
    </div>
    <div class="col-12 col-md-4">
        @if (empty($id))
            @include('myfinance2::orders.forms.partials.status-select')
        @else
            <div class="mb-3 row">
                <label class="col-12 control-label">
                    {{ trans('myfinance2::orders.forms.item-form.status.label') }}
                </label>
                <div class="col-12">
                    <div class="form-control d-flex align-items-center gap-2">
                        <span class="badge {{ $orderModel->getStatusBadgeClass() }}">
                            {{ $status }}
                        </span>
                        <small class="text-muted">
                            Use the actions to change
                        </small>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::orders.forms.partials.account-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::orders.forms.partials.trade_currency-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::orders.forms.partials.exchange_rate-input')
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::orders.forms.partials.quantity-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::orders.forms.partials.limit_price-input')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::orders.forms.partials.placed_at-picker')
    </div>
</div>

@if (!empty($projectedGain))
@php
    $gainClass  = $projectedGain['gain_value'] >= 0 ? 'success' : 'danger';
    $gainSign   = $projectedGain['gain_value'] >= 0 ? '+' : '';
    $isLoss     = $projectedGain['gain_value'] < 0;
    $currCode   = !empty($tradeCurrencyModel) ? $tradeCurrencyModel->display_code : '';
    $priceLabel = $projectedGainPriceLabel ?? 'limit price';
@endphp
<div class="row">
    <div class="col-12">
        <div class="alert alert-{{ $gainClass }} py-2 px-3 mb-3 small"
             id="projected-gain-box"
             data-avg-cost="{{ $projectedGain['avg_cost'] }}"
             data-total-qty="{{ $projectedGain['total_qty'] }}">
            <strong>Projected <span id="projected-gain-label">{{ $isLoss ? 'Loss ⚠️' : 'Gain ✅' }}</span>
                at <span id="projected-gain-price-label">{{ $priceLabel }}</span>:</strong>
            <span id="projected-gain-value">{{ $gainSign }}{{ number_format($projectedGain['gain_value'], 2) }}</span>
            {!! $currCode !!}
            (<span id="projected-gain-pct">{{ $gainSign }}{{ number_format($projectedGain['gain_pct'], 2) }}</span>%)
            <span class="text-muted ms-2">
                {{ MoneyFormat::get_formatted_quantity_plain($projectedGain['total_qty']) }} shares
                @ avg {{ MoneyFormat::get_formatted_price($projectedGain['avg_cost']) }} {!! $currCode !!}
            </span>
            <div id="projected-gain-loss-note" class="mt-1 text-muted"
                 @if (!$isLoss) style="display:none" @endif>
                Selling at this price would still realize a loss vs your avg cost.
            </div>
        </div>
    </div>
</div>
<script type="module">
$(document).ready(function ()
{
    const $box    = $('#projected-gain-box');
    const avgCost = parseFloat($box.data('avg-cost'));
    const totalQty = parseFloat($box.data('total-qty'));

    function updateGain(price)
    {
        const gainPerUnit = price - avgCost;
        const totalGain   = gainPerUnit * totalQty;
        const gainPct     = avgCost > 0 ? (gainPerUnit / avgCost) * 100 : 0;
        const isLoss      = totalGain < 0;
        const sign        = totalGain >= 0 ? '+' : '';

        $('#projected-gain-value').text(sign + totalGain.toFixed(2));
        $('#projected-gain-pct').text(sign + gainPct.toFixed(2));
        $('#projected-gain-label').text(isLoss ? 'Loss ⚠️' : 'Gain ✅');
        $('#projected-gain-price-label').text('limit price');
        $box.removeClass('alert-success alert-danger').addClass(isLoss ? 'alert-danger' : 'alert-success');
        $('#projected-gain-loss-note').toggle(isLoss);
    }

    $('#limit_price').on('input', function ()
    {
        const val = parseFloat($(this).val());
        if (!isNaN(val) && val > 0) {
            updateGain(val);
        }
    });
});
</script>
@endif

<div class="row">
    <div class="col-12 col-md-12">
        @include('myfinance2::orders.forms.partials.description-input')
    </div>
</div>
