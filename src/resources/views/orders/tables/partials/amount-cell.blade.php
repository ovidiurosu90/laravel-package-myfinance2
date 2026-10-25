{{-- The order list's Amount cell: the principal amount (quantity × limit price), and for a SELL the projected gain at
     the limit price beneath (with its %, a warning when it would realize a loss, and the open quantity at its average
     cost). Sorts by the amount. $order: the Order; $gain: its projected gain (OrdersController), or null;
     $currencyCode: the trade currency's display code. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<td class="text-right text-nowrap"
    data-order="{{ (float) $order->quantity * (float) $order->limit_price }}">
    {!! $order->getFormattedPrincipleAmount() !!}
    @if ($gain)
        @php
            $gainClass = $gain['gain_value'] >= 0 ? 'text-success' : 'text-danger';
            $gainSign  = $gain['gain_value'] >= 0 ? '+' : '';
            $fmtQty    = MoneyFormat::get_formatted_quantity_plain($gain['total_qty']);
        @endphp
        <div class="{{ $gainClass }} small text-nowrap" data-bs-toggle="tooltip"
            title="Projected gain if sold at the {{ $gain['price_label'] ?? 'limit price' }}, against your average
                cost{{ $gain['gain_value'] < 0 ? '. Below your average cost: selling would realize a loss.' : '.' }}">
            {{ $gainSign }}{!! MoneyFormat::get_formatted_price_display($currencyCode, $gain['gain_value']) !!}
            ({{ $gainSign }}{{ MoneyFormat::get_formatted_pct($gain['gain_pct']) }}%)@if ($gain['gain_value'] < 0)
                ⚠️@endif
        </div>
        <div class="text-muted small text-nowrap">
            {{ $fmtQty }}x @ avg
            {!! MoneyFormat::get_formatted_price_display($currencyCode, $gain['avg_cost'], true) !!}
        </div>
    @endif
</td>
