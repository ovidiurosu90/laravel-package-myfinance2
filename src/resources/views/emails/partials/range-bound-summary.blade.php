{{-- Summary of a range-bound alert (signal RANGE_HIGH / RANGE_LOW / RANGE_BUY): where today's price sits in the
     52-week range, why the holding is range-bound (the chip's text), the range as price levels (the line of the
     zone highlighted), today's move and the unrealized gain. Included by peak-proximity-alert, whose variables it
     reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\RangeBoundPattern')
<div class="section" style="border-left-color:#0dcaf0;">
    <h2>Summary</h2>
    <p>
        <strong>@if (!empty($tierLabel)){{ $tierLabel }}; @endif{{ $headline }}.</strong>
        Current price
        <strong class="text-nowrap">{!! $price !== null
            ? MoneyFormat::get_formatted_price_display($cur, $price, true) : 'n/a' !!}</strong>,
        {{ $triggerSummary[0] }}.
    </p>
    <p class="range-note">{{ $rangeBound['title'] }}</p>
    <table>
        <tr>
            <th>Level</th>
            <th>Price</th>
            <th>Date</th>
            <th>From current price</th>
        </tr>
        @foreach ($rangeLevels as $level)
        <tr @if ($level['in_zone']) class="in-zone" @endif>
            <td class="text-nowrap">{{ $level['label'] }}@if ($level['in_zone']) <span
                class="badge bg-info">in zone</span>@endif</td>
            <td class="text-nowrap">{!! MoneyFormat::get_formatted_price_display($cur, $level['price'], true) !!}</td>
            <td class="text-nowrap">{{ $level['date'] ?? '' }}</td>
            <td class="text-nowrap">{{ $signedPct($level['from_price_pct']) }}</td>
        </tr>
        @endforeach
    </table>
    @include('myfinance2::emails.partials.today-move', ['moveTarget' => 'its 52-week high'])
    @include('myfinance2::emails.partials.sell-gain', [
        'lockIn' => $rangeBound['zone'] === RangeBoundPattern::ZONE_HIGH,
    ])
</div>
