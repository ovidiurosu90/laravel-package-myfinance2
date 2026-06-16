@use('ovidiuro\myfinance2\App\Models\DipBuyingNotification')
@php
    // $active is one of: 'settings' | 'backtest' | 'history' (passed by the including page).
    $active = $active ?? '';
    $alertTotal = DipBuyingNotification::where('user_id', auth()->id())
        ->where('status', 'SENT')
        ->count();
@endphp
<div class="btn-group btn-group-sm" role="group" aria-label="Dip-buying sections">
    <a href="{{ route('myfinance2::dip-buying-alerts.index') }}"
       class="btn {{ $active === 'settings' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip" title="Pool size, alerts and ladder settings">
        <i class="fa fa-fw fa-sliders" aria-hidden="true"></i> Settings
    </a>
    <a href="{{ route('myfinance2::dip-buying-alerts.backtest') }}"
       class="btn {{ $active === 'backtest' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip" title="Validate the ladder on your own trade history">
        <i class="fa fa-fw fa-flask" aria-hidden="true"></i> Backtest
    </a>
    <a href="{{ route('myfinance2::dip-buying-alerts.history') }}"
       class="btn {{ $active === 'history' ? 'btn-primary' : 'btn-outline-secondary' }}"
       data-bs-toggle="tooltip" title="Log of alert emails that were sent">
        <i class="fa fa-fw fa-history" aria-hidden="true"></i> History
        @if ($alertTotal > 0)
            <span class="badge bg-secondary ms-1">{{ $alertTotal }}</span>
        @endif
    </a>
</div>
