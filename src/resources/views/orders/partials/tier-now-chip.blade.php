{{-- Today's tier in use next to a saved reading, when it differs ("now: Bronze"), with a warning icon when today's
     reading would raise a serious warning. $tier: today's tier; $serious: today's serious warnings. --}}
<span class="badge bg-warning-subtle text-warning-emphasis border border-warning fw-normal text-nowrap"
    data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
    title="Today's tier in use{{ empty($serious) ? '.' : '; today\'s reading would raise: '
        . implode('; ', array_map(fn ($w) => $w['label'] . ': ' . $w['text'], $serious)) }}">now:
    @include('myfinance2::orders.partials.tier-mini-badge', ['tier' => $tier])@if(!empty($serious))<i
        class="fa fa-exclamation-triangle fa-xs text-danger ms-1" aria-hidden="true"></i>@endif</span>
