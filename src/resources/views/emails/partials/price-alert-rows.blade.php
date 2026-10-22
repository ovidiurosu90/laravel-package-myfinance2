{{-- The alerts table of the price alert created and paused / resumed emails: id, symbol, type, target (with its
     relative label), accounts, optionally the new status, and the notes on a line of their own. Expects $alerts,
     $accountNames (symbol => names) and $showStatus; with it, $actionRaw (paused / resumed). --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php $columns = $showStatus ? 6 : 5; @endphp
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Symbol</th>
            <th>Type</th>
            <th class="num">Target Price</th>
            <th>Accounts</th>
            @if ($showStatus)
            <th>New Status</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($alerts as $alert)
        @php
            $currency  = html_entity_decode(strip_tags($alert->tradeCurrencyModel?->display_code ?? ''),
                ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $typeLabel = $alert->alert_type === 'PRICE_ABOVE' ? '▲ Above' : '▼ Below';
            $typeClass = $alert->alert_type === 'PRICE_ABOVE' ? 'bg-danger' : 'bg-primary';
            $accounts  = $accountNames[$alert->symbol] ?? [];
        @endphp
        <tr @if (!empty($alert->notes)) class="has-notes" @endif>
            <td>{{ $alert->id }}</td>
            <td>
                <a class="sym-link" href="https://finance.yahoo.com/quote/{{ $alert->symbol }}" target="_blank">
                    <strong>{{ $alert->symbol }}</strong>
                </a>
            </td>
            <td><span class="badge {{ $typeClass }}">{{ $typeLabel }}</span></td>
            <td class="num">
                {{ MoneyFormat::get_formatted_price_display($currency, (float) $alert->target_price, true) }}
                @include('myfinance2::emails.partials.price-alert-relative-target', [
                    'alert'    => $alert,
                    'currency' => $currency,
                ])
            </td>
            <td>
                @forelse ($accounts as $accountName)
                    <div class="text-nowrap">{{ $accountName }}</div>
                @empty
                    <span class="text-muted">n/a</span>
                @endforelse
            </td>
            @if ($showStatus)
            <td>
                <span class="badge {{ $actionRaw === 'paused' ? 'bg-secondary' : 'bg-success' }}">
                    {{ $actionRaw === 'paused' ? 'PAUSED' : 'ACTIVE' }}</span>
            </td>
            @endif
        </tr>
        @if (!empty($alert->notes))
        <tr>
            <td class="notes" colspan="{{ $columns }}"><span class="label">Notes</span> {{ $alert->notes }}</td>
        </tr>
        @endif
        @endforeach
    </tbody>
</table>
