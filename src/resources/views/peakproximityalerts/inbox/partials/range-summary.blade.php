{{-- Body of a range-bound inbox card (signal RANGE_HIGH / RANGE_LOW), mirroring the alert email's summary: where the
     price sat in its 52-week range when the alert ran, the range as price levels (the line of the zone highlighted),
     today's move and the open position's unrealized gain. Included by the card, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\RangeBoundPattern')
<span>
    Current price
    <strong class="text-nowrap">{!! $sumPrice !== null
        ? MoneyFormat::get_formatted_price_display($cur, $sumPrice, true) : 'n/a' !!}</strong>,
    {{ RangeBoundPattern::positionText($rangeBound) }}.
</span>
<table class="table table-sm table-borderless mb-1 mt-1" style="width:auto;">
    <thead>
        <tr class="text-nowrap">
            <th class="pe-3 fw-semibold">Level</th>
            <th class="pe-3 fw-semibold">Price</th>
            <th class="pe-3 fw-semibold">Date</th>
            <th class="fw-semibold">From current price</th>
        </tr>
    </thead>
    <tbody>
        @foreach (RangeBoundPattern::levelRows($rangeBound, $sumPrice) as $level)
            <tr class="text-nowrap @if ($level['in_zone']) table-info @endif">
                <td class="pe-3">{{ $level['label'] }}@if ($level['in_zone']) <span
                    class="badge bg-info text-dark ms-1">in zone</span>@endif</td>
                <td class="pe-3">{!! MoneyFormat::get_formatted_price_display($cur, $level['price'], true) !!}</td>
                <td class="pe-3">{{ $level['date'] ?? '' }}</td>
                <td>{{ $signedPct($level['from_price_pct']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@include('myfinance2::peakproximityalerts.inbox.partials.today-move', ['moveTarget' => 'its 52-week high'])
@include('myfinance2::peakproximityalerts.inbox.partials.unrealized-gain', [
    'lockIn' => $rangeBound['zone'] === RangeBoundPattern::ZONE_HIGH,
])
