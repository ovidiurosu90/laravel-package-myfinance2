{{-- "Today's move" line of the alert email: the position's gain or loss today and which way it moved the position
     relative to its peak ($moveTarget: "its 52-week high" for a range-bound alert). Included by the alert summaries,
     whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($todayGainEur !== null)
    @php
        $moveTarget      = $moveTarget ?? 'its peak';
        $todayMoveClause = $todayGainEur > 0 ? ", which nudged the position toward {$moveTarget} today."
            : ($todayGainEur < 0 ? ", which moved the position away from {$moveTarget} today." : '.');
    @endphp
    <p style="margin-top:8px;">
        Today's move:
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain($eur, $todayGainEur) !!}</span>{{--
        --}}@if ($todayGainPct !== null) ({{--
            --}}<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $todayGainPct) !!}</span>){{--
        --}}@endif{{--
        --}}{{ $todayMoveClause }}
    </p>
@endif
