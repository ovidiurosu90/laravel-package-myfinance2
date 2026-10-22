{{-- The Position card of the price alert email: quantity, average cost (fees included) and current price; the gain
     itself is the unrealized gain line of the Summary. "No open position" when the symbol is not held. Included by
     price-alert-triggered, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($quantity !== null)
<div class="section {{ $isLoss ? 'loss' : 'triggered' }}">
    <h2>Position {{ $isLoss ? '⚠️' : '✅' }}</h2>
    <table>
        <tr>
            <th>Quantity</th>
            <th>Avg Cost</th>
            <th>Current Price</th>
        </tr>
        <tr>
            <td class="text-nowrap">{{ MoneyFormat::get_formatted_quantity_plain($quantity) }}</td>
            <td class="text-nowrap">
                @if ($avgUnitCost !== null)
                    {{ MoneyFormat::get_formatted_price($avgUnitCost, true) }} {!! $currencyHtml !!}
                @endif
            </td>
            <td class="text-nowrap">{{ MoneyFormat::get_formatted_price($currentPrice, true) }}
                {!! $currencyHtml !!}</td>
        </tr>
    </table>
    @if ($isLoss)
    <p class="text-muted" style="margin-top:8px;font-size:12px;">
        ⚠️ This is a loss-minimizing alert: the best available exit based on the recent high.
    </p>
    @endif
</div>
@else
<div class="section">
    <h2>Position</h2>
    <p>No open position found for {{ $alert->symbol }}.</p>
</div>
@endif
