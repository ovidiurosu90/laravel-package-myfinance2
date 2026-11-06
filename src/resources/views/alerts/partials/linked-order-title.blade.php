@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
{{-- Card title link to the open order keeping this alert in sync (PriceAlert::linkedOrder()). $linkedOrder: the
     order. --}}
@php
    $linkedOrderPrice = MoneyFormat::get_formatted_price_display(
        // The display code is stored as an HTML entity (e.g. "&dollar;"); decode it, as the title escapes it.
        html_entity_decode($linkedOrder->tradeCurrencyModel?->display_code ?? '', ENT_QUOTES | ENT_HTML5),
        (float) $linkedOrder->limit_price,
        true
    );
@endphp
<a href="{{ route('myfinance2::orders.edit', $linkedOrder->id) }}" target="_blank" rel="noopener" class="ms-2 small"
    data-bs-toggle="tooltip"
    title="{{ trans('myfinance2::alerts.linked-order.title-tooltip', [
        'id' => $linkedOrder->id, 'action' => $linkedOrder->action, 'symbol' => $linkedOrder->symbol,
        'price' => trim($linkedOrderPrice),
    ]) }}">
    <i class="fa fa-link fa-fw" aria-hidden="true"></i> {{ trans('myfinance2::alerts.linked-order.title', [
        'id' => $linkedOrder->id,
    ]) }}
</a>
