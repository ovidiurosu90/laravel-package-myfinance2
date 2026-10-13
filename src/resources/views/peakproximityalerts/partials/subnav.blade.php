@use('ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent')
@php
    // $active is one of: 'manage' | 'inbox' | 'history' | 'backtest' (passed by the including page).
    $active = $active ?? '';
    $openActionable = PeakProximityAlertEvent::where('user_id', auth()->id())
        ->where('status', PeakProximityAlertEvent::STATUS_OPEN)
        ->where('classification', PeakProximityAlertEvent::CLASS_ACTIONABLE)
        ->count();
@endphp
<div class="btn-group btn-group-sm" role="group" aria-label="Peak-proximity sections">
    <a href="{{ route('myfinance2::peak-proximity-alerts.index') }}"
       class="btn {{ $active === 'manage' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip" title="Choose which symbols can alert">
        <i class="fa fa-fw fa-sliders" aria-hidden="true"></i> Manage
    </a>
    <a href="{{ route('myfinance2::peak-proximity-alerts.inbox') }}"
       class="btn {{ $active === 'inbox' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip"
       title="Current near-peak, TAKE PROFIT, tier signal and range-bound alerts to act on or dismiss">
        <i class="fa fa-fw fa-inbox" aria-hidden="true"></i> Inbox
        @if ($openActionable > 0)
            <span class="badge bg-danger ms-1">{{ $openActionable }}</span>
        @endif
    </a>
    <a href="{{ route('myfinance2::peak-proximity-alerts.history') }}"
       class="btn {{ $active === 'history' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip" title="Log of alert emails that were sent">
        <i class="fa fa-fw fa-history" aria-hidden="true"></i> History
    </a>
    <a href="{{ route('myfinance2::peak-proximity-alerts.backtest') }}"
       class="btn {{ $active === 'backtest' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip" title="How symbols did against VUSA.AS after each tier signal (rare high, rare low,
       deteriorating), on your stored tier history">
        <i class="fa fa-fw fa-flask" aria-hidden="true"></i> Backtest
    </a>
</div>
