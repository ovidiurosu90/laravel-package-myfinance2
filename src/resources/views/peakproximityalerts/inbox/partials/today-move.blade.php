{{-- "Today's move" line of an inbox card, mirroring the alert email: the position's gain or loss on the day the
     alert ran and which way it moved the position relative to its peak ($moveTarget: "its 52-week high" for a
     range-bound card). Included by the card and the summaries, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($todayEur !== null)
    @php
        $moveTarget      = $moveTarget ?? 'its peak';
        $todayMoveClause = $todayEur > 0 ? ", which nudged the position toward {$moveTarget} today."
            : ($todayEur < 0 ? ", which moved the position away from {$moveTarget} today." : '.');
    @endphp
    <span class="d-block">
        Today's move
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('€', $todayEur) !!}</span>{{--
        --}}@if ($todayPct !== null) ({{--
            --}}<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $todayPct) !!}</span>){{--
        --}}@endif{{--
        --}}{{ $todayMoveClause }}
    </span>
@endif
