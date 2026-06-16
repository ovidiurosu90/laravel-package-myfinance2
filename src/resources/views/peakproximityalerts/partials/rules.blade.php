@php
    $periodMap   = ['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'];
    $exitTiers   = config('alerts.peak_proximity.exit_tiers', ['RUST', 'BRONZE']);
    $exitLabels  = collect($exitTiers)->map(fn ($t) => ucfirst(strtolower((string) $t)))->implode(' or ');
    $meaningful  = config('alerts.peak_proximity.meaningful_windows', ['6m', '1y', '2y']);
    $longLabels  = collect($meaningful)->map(fn ($w) => $periodMap[$w] ?? strtoupper($w))->implode(' / ');
    $shortLabels = collect(config('alerts.peak_proximity.short_windows', ['3m']))
        ->map(fn ($w) => $periodMap[$w] ?? strtoupper($w))->implode(', ');
    $cadence     = config('alerts.peak_proximity.reminder_days_by_confluence', [1 => 7, 2 => 3, 3 => 1]);
    $cadenceText = collect($cadence)
        ->map(function ($days, $count) {
            $every = (int) $days === 1 ? 'daily' : "every {$days} days";
            $win   = (int) $count === 1 ? 'window' : 'windows';
            return "{$count} {$win} near peak: {$every}";
        })->implode('; ');
@endphp
<div class="card border-info mb-2">
    <div class="card-body py-2">
        <p class="mb-1 fw-semibold">
            <i class="fa fa-fw fa-info-circle text-info" aria-hidden="true"></i>
            How these alerts work
        </p>
        <ul class="small text-muted mb-0 ps-3">
            <li>
                <strong>Off by default, opt-in per symbol.</strong> Nothing emails until you enable a
                held symbol below. Each symbol can be enabled permanently or "until" a date.
            </li>
            <li>
                <strong>An exit aid, not a buy/hold signal.</strong> "Near peak" only matters for a
                position you would consider trimming, so an email needs a <strong>weak tier</strong>
                ({{ $exitLabels }}). Strong holdings (Platinum, Gold, Silver) never email; they show
                in the inbox as informational only.
            </li>
            <li>
                <strong>Tier gates, action does not.</strong> The gain-based tier decides whether a
                symbol can email. The HOLD / EXIT action is shown for context but never gates.
            </li>
            <li>
                <strong>{{ $longLabels }} matter; {{ $shortLabels }} is context.</strong> An email
                needs one of the longer windows near peak. A {{ $shortLabels }}-only signal is shown
                for context and never emails on its own.
            </li>
            <li>
                <strong>Escalating reminders.</strong> You get one email when an alert opens, then
                reminders that come faster the more long windows are near peak at once
                ({{ $cadenceText }}). A new window reaching peak emails right away. There is no cap;
                reminders continue until you dismiss the alert.
            </li>
            <li>
                <strong>The inbox persists until you dismiss.</strong> Alerts stay in the
                <a href="{{ route('myfinance2::peak-proximity-alerts.inbox') }}">inbox</a> even after a
                symbol is no longer near peak. Dismissing one ends its email reminders; if it later
                returns to peak, a fresh alert opens.
            </li>
        </ul>
    </div>
</div>
