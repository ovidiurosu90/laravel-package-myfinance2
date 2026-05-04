{{ csrf_field() }}
<div id="order-summary-banner" class="alert py-2 px-3 mb-3 small" style="display:none"
    data-trade-currencies="{{ json_encode($tradeCurrencies) }}"
    data-avg-cost="{{ !empty($projectedGain) ? $projectedGain['avg_cost'] : '' }}"
    data-total-open-qty="{{ !empty($projectedGain) ? $projectedGain['total_qty'] : '' }}">
</div>
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

<div class="row">
    <div class="col-12 col-md-12">
        @include('myfinance2::orders.forms.partials.description-input')
    </div>
</div>
