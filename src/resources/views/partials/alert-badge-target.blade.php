{{-- Second line of a price alert badge (watchlist, orders): the target price for a FIXED alert, or
     the short relative label ("6M H -8%") for a RELATIVE one, so every badge keeps the same width.
     The relative alert's price and full reference belong in the badge tooltip.
     Expects: $alert (PriceAlert), $priceHtml (formatted target price, may contain HTML entities). --}}
@if ($alert->isRelative())
    @if ($alert->isReferenceStale())
        <i class="fa fa-exclamation-triangle fa-xs" aria-hidden="true"></i>
    @endif
    {{ $alert->getShortRelativeLabel() }}
@else
    {!! $priceHtml !!}
@endif
