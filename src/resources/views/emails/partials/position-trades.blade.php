{{-- The trades table of a position in the alert emails: its trades and the symbol's stock splits, newest first
     (PositionTimeline::merge()). Shared by the peak-proximity alert's Open positions card and the price alert's
     Position card. Expects $timeline; $showAccount (default false) adds an Account column, for a timeline whose
     trades span several accounts. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    $showAccount ??= false;
@endphp
@if (!empty($timeline))
<table>
    <tr>
        <th>Date</th>
        <th>Action</th>
        <th>Quantity</th>
        <th>Unit price</th>
        @if ($showAccount)
        <th>Account</th>
        @endif
    </tr>
    @foreach ($timeline as $event)
    @if ($event['type'] === 'split')
    @php $splitItem = $event['data']; @endphp
    <tr class="text-muted">
        <td class="text-nowrap">{{ $splitItem->split_date->format('Y-m-d') }}</td>
        <td colspan="{{ $showAccount ? 4 : 3 }}">&#9988; {{ $splitItem->getRatioLabel() }} split</td>
    </tr>
    @else
    @php $trade = $event['data']; @endphp
    <tr>
        <td class="text-nowrap">{{ $trade->timestamp->format('Y-m-d') }}</td>
        <td>{{ strtolower($trade->action) }}</td>
        <td class="text-nowrap">{{ MoneyFormat::get_formatted_quantity_plain($trade->quantity) }}x</td>
        <td class="text-nowrap">
            {!! html_entity_decode(MoneyFormat::get_formatted_price_display(
                $trade->tradeCurrencyModel->display_code,
                (float) $trade->unit_price,
                true
            ), ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}
        </td>
        @if ($showAccount)
        <td>{{ $trade->accountModel->name ?? '' }}</td>
        @endif
    </tr>
    @endif
    @endforeach
</table>
@endif
