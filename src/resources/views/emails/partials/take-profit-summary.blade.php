{{-- Summary of a take-profit alert (signal TAKE_PROFIT, WeakTierProfit): why it reads TAKE PROFIT (the chip's text),
     the open position's gain, and every window with its peak as context, the windows near peak highlighted.
     Included by peak-proximity-alert, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<div class="section" style="border-left-color:#198754;">
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
    <p class="profit-note">{{ $takeProfit['title'] }}</p>
    <p>
        The windows below are context for the timing: each with its peak, the target price that would put it near
        peak and how far the price still has to run. A long window (6M, 1Y, 2Y) reaching its peak emails again at
        once.
    </p>
    @include('myfinance2::emails.partials.peak-windows-table')
    @include('myfinance2::emails.partials.today-move')
    @include('myfinance2::emails.partials.sell-gain')
</div>
