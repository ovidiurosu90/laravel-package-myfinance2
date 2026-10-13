{{-- Summary of a tier signal alert (signal RARE_HIGH / RARE_LOW / DETERIORATING, TierSignalChip): why it holds (the
     chip's tooltip, severity and caution flags included), and every window with its peak as context, the windows
     near peak highlighted. Included by peak-proximity-alert, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<div class="section" style="border-left-color:#fd7e14;">
    <h2>Summary</h2>
    <p>
        <strong>@if (!empty($tierLabel)){{ $tierLabel }}; @endif{{ $headline }}.</strong>
        Current price
        <strong class="text-nowrap">{!! $price !== null
            ? MoneyFormat::get_formatted_price_display($cur, $price, true) : 'n/a' !!}</strong>,
        {{ $triggerSummary[0] }};
        @if ($nearCount > 0)
            near its peak in {{ $nearCount }} {{ $nearCount === 1 ? 'window' : 'windows' }}.
        @else
            not near a peak in any window.
        @endif
    </p>
    <p class="signal-note">{{ $tierSignal['title'] }}</p>
    <p>
        The windows below are context for the timing: each with its peak, the target price that would put it near
        peak and how far the price still has to run.
    </p>
    @include('myfinance2::emails.partials.peak-windows-table')
    @include('myfinance2::emails.partials.today-move')
    @include('myfinance2::emails.partials.sell-gain')
</div>
