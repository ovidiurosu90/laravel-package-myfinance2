{{-- "Today's move" line of an inbox card, mirroring the alert email: the position's gain or loss on the day the
     alert ran and which way it moved the position relative to its peak ($moveTarget: "its 52-week high" for a
     range-bound card). A symbol not held shows only the day's % and reads "the price" instead of "the position".
     Included by the card and the summaries, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($todayEur !== null || $todayPct !== null)
    @php
        $moveTarget      = $moveTarget ?? 'its peak';
        $moveSubject     = $todayEur !== null ? 'the position' : 'the price';
        $todayMove       = $todayEur ?? $todayPct;
        $todayMoveClause = $todayMove > 0 ? ", which nudged {$moveSubject} toward {$moveTarget} today."
            : ($todayMove < 0 ? ", which moved {$moveSubject} away from {$moveTarget} today." : '.');
        $todayPctHtml    = $todayPct !== null
            ? '<span class="text-nowrap">' . MoneyFormat::get_formatted_gain('%', $todayPct) . '</span>' : '';
        $todayMoveHtml   = $todayEur === null ? $todayPctHtml
            : '<span class="text-nowrap">' . MoneyFormat::get_formatted_gain('€', $todayEur) . '</span>'
                . ($todayPctHtml !== '' ? " ({$todayPctHtml})" : '');
    @endphp
    <span class="d-block">
        Today's move: {!! $todayMoveHtml !!}{{ $todayMoveClause }}
    </span>
@endif
