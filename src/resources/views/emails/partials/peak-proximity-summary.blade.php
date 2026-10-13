{{-- Summary of a near-peak alert: every window with its peak, trigger target and distance, and for a tier signal
     its chip's reading (as on its own alert). Included by peak-proximity-alert, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<div class="section" style="border-left-color:#28a745;">
    <h2>Summary</h2>
    <p>
        @if (!empty($tierLabel))
            <strong>{{ $tierLabel }}@if (!empty($headAction)), {{ $headAction }}@endif.</strong>
        @endif
        Current price
        <strong class="text-nowrap">{!! $price !== null
            ? MoneyFormat::get_formatted_price_display($cur, $price, true) : 'n/a' !!}</strong>,
        near its peak in {{ $nearCount }} {{ $nearCount === 1 ? 'window' : 'windows' }}.
        Every window is shown below with the target price that would put it near peak
        (its threshold below the peak) and how far the price still has to run.
    </p>
    @if (!empty($tierSignal))
    <p class="signal-note">{{ $tierSignal['title'] }}</p>
    @endif
    @include('myfinance2::emails.partials.peak-windows-table')
    @include('myfinance2::emails.partials.today-move')
    @include('myfinance2::emails.partials.sell-gain')
</div>
