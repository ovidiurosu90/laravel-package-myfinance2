@use('ovidiuro\myfinance2\App\Models\PortfolioPeakNotification')
@php
    // $active is one of: 'settings' | 'history' (passed by the including page).
    $active = $active ?? '';
    $alertTotal = PortfolioPeakNotification::where('user_id', auth()->id())
        ->where('status', 'SENT')
        ->count();
@endphp
<div class="btn-group btn-group-sm" role="group" aria-label="Portfolio-peak sections">
    <a href="{{ route('myfinance2::portfolio-peak-alerts.index') }}"
       class="btn {{ $active === 'settings' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip" title="Enable the alert and its per-metric toggles">
        <i class="fa fa-fw fa-sliders" aria-hidden="true"></i> Settings
    </a>
    <a href="{{ route('myfinance2::portfolio-peak-alerts.history') }}"
       class="btn {{ $active === 'history' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip" title="Log of alert emails that were sent">
        <i class="fa fa-fw fa-history" aria-hidden="true"></i> History
        @if ($alertTotal > 0)
            <span class="badge bg-secondary ms-1">{{ $alertTotal }}</span>
        @endif
    </a>
</div>
