{{-- "Unrealized gain" line of an inbox card, mirroring the alert email: the open position's gain if sold now when the
     alert ran. Included by the card and its summaries, whose variables it reads ($gainEur, $gainPct); $lockIn
     (default true) adds "what you would lock in by selling now" (a range-bound card only near the high). --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($gainEur !== null)
    <span class="d-block">
        Unrealized gain
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('€', $gainEur) !!}</span>{{--
        --}}@if ($gainPct !== null) ({{--
            --}}<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $gainPct) !!}</span>){{--
        --}}@endif{{ ($lockIn ?? true) ? ', what you would lock in by selling now' : '' }}.
    </span>
@endif
