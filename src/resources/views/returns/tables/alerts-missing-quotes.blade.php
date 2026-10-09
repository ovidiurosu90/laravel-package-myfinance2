@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
{{-- Held positions without a price: left out of the start/end value (see ReturnsMissingQuoteAlerts) --}}
<table class="table table-sm table-bordered mb-2" style="background-color: white;">
    <thead class="table-light">
        <tr>
            <th>Symbol</th>
            <th class="text-end">Quantity</th>
            <th>Account</th>
            <th>Valuation Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($alert['positions'] as $position)
            <tr>
                <td><strong>{{ $position['symbol'] }}</strong></td>
                <td class="text-end">{{ MoneyFormat::get_formatted_quantity_plain($position['quantity']) }}</td>
                <td>{{ $position['account_name'] }}</td>
                <td>{{ $position['date'] }}</td>
                <td>
                    <span class="badge bg-danger">Valued at 0</span>
                    @if($position['delisted'])
                        <span class="badge bg-secondary">Delisted</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
<small class="text-muted">
    The finance API returned no price on or up to 7 days before the valuation date, so these positions are
    missing from the account value. If the symbol was delisted, add it to <code>delisted_symbols</code> and
    add a <code>price_overrides</code> entry for the config date.
</small>
