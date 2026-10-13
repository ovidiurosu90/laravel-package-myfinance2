{{-- Body of a tier signal inbox card (signal RARE_HIGH / RARE_LOW / DETERIORATING, TierSignalChip), mirroring the
     alert email's summary: why it holds (the chip's tooltip has the full reading), every window with its peak as
     context (the windows near peak highlighted) and the unrealized gain when held. Included by the card, whose
     variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\TierSignalChip')
<span>
    Current price
    <strong class="text-nowrap">{!! $sumPrice !== null
        ? MoneyFormat::get_formatted_price_display($cur, $sumPrice, true) : 'n/a' !!}</strong>,
    {{ TierSignalChip::shortText($tierSignal) }};
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
