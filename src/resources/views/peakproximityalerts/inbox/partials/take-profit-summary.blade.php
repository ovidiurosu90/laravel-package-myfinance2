{{-- Body of a take-profit inbox card (signal TAKE_PROFIT, a weak tier in profit), mirroring the alert email's
     summary: the open position's gain when the alert ran, every window with its peak as context (the windows near
     peak highlighted) and the unrealized gain. Included by the card, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\WeakTierProfit')
<span>
    Current price
    <strong class="text-nowrap">{!! $sumPrice !== null
        ? MoneyFormat::get_formatted_price_display($cur, $sumPrice, true) : 'n/a' !!}</strong>,
    {{ WeakTierProfit::gainText($takeProfit) }};
    @if ($nearCount > 0)
        near its peak in {{ $nearCount }} {{ $nearCount === 1 ? 'window' : 'windows' }}.
    @else
        not near a peak in any window.
    @endif
    The windows are context for the timing.
</span>
@if (!empty($sumWindows))
    @include('myfinance2::peakproximityalerts.inbox.partials.peak-windows-table')
@endif
@include('myfinance2::peakproximityalerts.inbox.partials.today-move')
@include('myfinance2::peakproximityalerts.inbox.partials.unrealized-gain')
