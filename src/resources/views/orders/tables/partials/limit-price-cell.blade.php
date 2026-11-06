@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
{{-- Limit cell of the orders list, laid out like the alerts' Target Price cell
     (alerts/tables/partials/target-price-cell): the limit first, labelled with the side it fills on (▼ a BUY fills at
     or below it, ▲ a SELL at or above), then the current price ("now") and, for an open order, how far it is from
     the limit, measured on the current price: "1.01% to go" (the move the price still needs) or "2.30% past" with a
     Reached badge (Order::isLimitReachedAt()). Labelled in the cell, so it reads without the column header.
     $item: the order; $qd: its quote data; $cp: the current price (null without a quote); $distance:
     $item->limitDistance($cp) for an open order with a quote, else null; $currencyCode: the trade currency's
     display code. --}}
@php
    $sell        = $item->action === 'SELL';
    // Tooltips are plain text: decode the stored display code entity (e.g. "&dollar;").
    $plainAmount = fn (float $value) => html_entity_decode(
        MoneyFormat::get_formatted_monetary_display($currencyCode, $value), ENT_QUOTES | ENT_HTML5
    );
@endphp
<div class="text-nowrap">
    {{-- The label only from xl up: below it the column is tight and the ▼ / ▲ keeps the side. --}}
    <span class="text-muted small d-none d-xl-inline">Limit</span>
    @if ($item->limit_price)
        <span data-bs-toggle="tooltip"
            title="{{ $sell ? 'A SELL fills at or above the limit' : 'A BUY fills at or below the limit' }}"
            >{{ $sell ? '▲' : '▼' }}</span>
    @endif
    {!! $item->getFormattedLimitPrice() !!}
    @if ($distance && $distance['reached'])
        @php
            $reachedTitle = 'The price is at or ' . ($sell ? 'above' : 'below') . ' the limit';
            $reachedTitle .= $item->status === 'PLACED'
                ? ': the order can fill at the limit or better.'
                : ', but the order is a draft, so it is not at the broker yet.';
        @endphp
        {{-- On its own line below xl, next to the limit from xl up (as on the alerts list). --}}
        <span class="d-block d-xl-inline">
            <span class="badge bg-success ms-xl-1" data-bs-toggle="tooltip" title="{{ $reachedTitle }}">
                <i class="fa fa-check" aria-hidden="true"></i> Reached
            </span>
        </span>
    @endif
</div>
<div class="text-muted small text-nowrap">
    now
    @if ($cp !== null)
        @include('myfinance2::partials.price-tooltip', [
            'currency'           => $currencyCode,
            'price'              => $qd['price'] ?? null,
            'timestamp'          => $qd['quote_timestamp'] ?? null,
            'isPreMarket'        => !empty($qd['pre_market_price']),
            'isOvernight'        => !empty($qd['overnight_market']),
            'isPostMarket'       => !empty($qd['post_market_price']),
            'dayChange'          => $qd['day_change'] ?? null,
            'dayChangePct'       => $qd['day_change_pct'] ?? null,
            'regularPrice'       => $qd['regular_market_price'] ?? null,
            'regularTimestamp'   => $qd['regular_market_timestamp'] ?? null,
            'regularDayChange'   => $qd['regular_market_day_change'] ?? null,
            'regularDayChangePct'=> $qd['regular_market_day_change_pct'] ?? null,
            'postChange'         => $qd['post_market_change'] ?? null,
            'postChangePct'      => $qd['post_market_change_pct'] ?? null,
            'marketOpen'         => $qd['market_open'] ?? false,
            'content'            => MoneyFormat::get_formatted_price_display($currencyCode, $cp, true),
        ])
        @if ($distance && $distance['pct'] !== null)
            @php
                $pctText = MoneyFormat::get_formatted_pct($distance['pct']) . '%';
                $distanceTitle = $distance['reached']
                    ? 'The price is ' . $plainAmount($distance['amount']) . ' (' . $pctText . ') past the limit'
                    : 'The price has to ' . ($sell ? 'rise ' : 'fall ') . $plainAmount($distance['amount'])
                        . ' (' . $pctText . ') to reach the limit';
            @endphp
            {{-- On its own line below xl (like the Reached badge), after a "·" next to the price from xl up. --}}
            <span class="d-none d-xl-inline">&middot;</span>
            <span class="d-block d-xl-inline" data-bs-toggle="tooltip" title="{{ $distanceTitle }}">
                {{ $pctText }} {{ $distance['reached'] ? 'past' : 'to go' }}
            </span>
        @endif
    @else
        <span>—</span>
    @endif
</div>
@if ($item->exchange_rate && $item->exchange_rate != 1)
    <div class="text-nowrap text-muted small">
        FX {{ $item->exchange_rate + 0 }}
    </div>
@endif
