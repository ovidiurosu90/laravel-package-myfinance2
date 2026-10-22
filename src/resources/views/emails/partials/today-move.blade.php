{{-- "Today's move" line of the alert email: the position's gain or loss today and which way it moved the position
     relative to its peak ($moveTarget: "its 52-week high" for a range-bound alert). A symbol not held shows only the
     day's % and reads "the price" instead of "the position". Included by the alert summaries and the price alert,
     whose variables it reads ($todayGainEur, $todayGainPct, $eur). --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($todayGainEur !== null || $todayGainPct !== null)
    @php
        $moveTarget      = $moveTarget ?? 'its peak';
        $moveSubject     = $todayGainEur !== null ? 'the position' : 'the price';
        $todayMove       = $todayGainEur ?? $todayGainPct;
        $todayMoveClause = $todayMove > 0 ? ", which nudged {$moveSubject} toward {$moveTarget} today."
            : ($todayMove < 0 ? ", which moved {$moveSubject} away from {$moveTarget} today." : '.');
        $todayPctHtml    = $todayGainPct !== null
            ? '<span class="text-nowrap">' . MoneyFormat::get_formatted_gain('%', $todayGainPct) . '</span>' : '';
        $todayMoveHtml   = $todayGainEur === null ? $todayPctHtml
            : '<span class="text-nowrap">' . MoneyFormat::get_formatted_gain($eur, $todayGainEur) . '</span>'
                . ($todayPctHtml !== '' ? " ({$todayPctHtml})" : '');
    @endphp
    <p style="margin-top:8px;">
        Today's move: {!! $todayMoveHtml !!}{{ $todayMoveClause }}
    </p>
@endif
