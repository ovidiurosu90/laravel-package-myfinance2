@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
{{-- Target Price cell of the alerts list: the target first, labelled with the alert's direction, then the current
     price ("now") and how far it is from the target, both measured on the current price: "2.30% to go" (the move the
     price still needs) or "18.37% past" with a Reached badge (PriceAlert::isReachedAt(), the rule the engine fires
     on). Labelled in the cell, so it reads without the column header. $item: the alert; $qd: its quote data; $cp:
     the current price (null without a quote); $distance: $item->targetDistance($cp) (null without a quote);
     $currencyCode: the trade currency's display code. --}}
@php
    $below       = $item->alert_type === 'PRICE_BELOW';
    // Tooltips are plain text: decode the stored display code entity (e.g. "&dollar;").
    $plainAmount = fn (float $value) => html_entity_decode(
        MoneyFormat::get_formatted_monetary_display($currencyCode, $value), ENT_QUOTES | ENT_HTML5
    );
@endphp
<div class="text-nowrap">
    <span class="text-muted small">Target</span>
    {{ $below ? '▼' : '▲' }}
    {!! MoneyFormat::get_formatted_price_display($currencyCode, (float) $item->target_price, true) !!}
    @if ($distance && $distance['reached'])
        @php
            $reachedTitle = 'The price is at or ' . ($below ? 'below' : 'above') . ' the target';
            $reachedTitle .= $item->canFire()
                ? ': the alert fires on its next check and emails once a day until paused or changed.'
                : ', but the alert is ' . strtolower($item->getEffectiveStatus()) . ', so it does not fire.';
            if ($item->last_triggered_at) {
                $reachedTitle .= ' Last fired '
                    . $item->last_triggered_at->timezone(config('app.timezone'))->format('Y-m-d H:i') . '.';
            }
        @endphp
        {{-- On its own line below xl (the breakpoint the alerts table uses), next to the target from xl up. --}}
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
        @if ($distance['pct'] !== null)
            @php
                $pctText = MoneyFormat::get_formatted_pct($distance['pct']) . '%';
                $distanceTitle = $distance['reached']
                    ? 'The price is ' . $plainAmount($distance['amount']) . ' (' . $pctText . ') past the target'
                    : 'The price has to ' . ($below ? 'fall ' : 'rise ') . $plainAmount($distance['amount'])
                        . ' (' . $pctText . ') to reach the target';
            @endphp
            &middot;
            <span data-bs-toggle="tooltip" title="{{ $distanceTitle }}">
                {{ $pctText }} {{ $distance['reached'] ? 'past' : 'to go' }}
            </span>
        @endif
    @else
        <span>—</span>
    @endif
</div>
@include('myfinance2::alerts.tables.partials.relative-target-info', ['alert' => $item])
