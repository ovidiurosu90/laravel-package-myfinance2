{{-- "Your position's current unrealized gain" line of the alert email: the open position's gain if sold now. Included
     by the summaries, whose variables it reads ($sellGainEur, $sellGainPct, $eur); $lockIn (default true) adds
     "which is what you would lock in by selling now" (a range-bound alert only near the high). --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($sellGainEur !== null)
    <p style="margin-top:8px;">
        Your position's current unrealized gain is
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain($eur, $sellGainEur) !!}</span>{{--
        --}}@if ($sellGainPct !== null) ({{--
            --}}<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $sellGainPct) !!}</span>){{--
        --}}@endif{{ ($lockIn ?? true) ? ', which is what you would lock in by selling now' : '' }}.
    </p>
@endif
