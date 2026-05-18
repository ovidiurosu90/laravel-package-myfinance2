@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($openAlerts->isNotEmpty())
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    <strong>
        <i class="fa fa-bell fa-fw" aria-hidden="true"></i>
        {{ $openAlerts->count() === 1 ? '1 active price alert' : $openAlerts->count() . ' active price alerts' }}
        for {{ $bannerSymbol }}
    </strong>
    : review before saving your order.
    <ul class="mb-0 mt-2">
        @foreach ($openAlerts as $alert)
            @php
                $currencyCode = $alert->tradeCurrencyModel?->display_code ?? '';
                $expiresAt = $alert->expires_at?->timezone(config('app.timezone'));
            @endphp
            <li class="small">
                <span class="badge {{ $alert->getAlertTypeBadgeClass() }}">
                    {{ $alert->alert_type === 'PRICE_ABOVE' ? '▲ Above' : '▼ Below' }}
                </span>
                {!! MoneyFormat::get_formatted_price_display($currencyCode, (float) $alert->target_price, true) !!}
                @if ($expiresAt)
                    <span class="text-muted">(expires {{ $expiresAt->format('Y-m-d') }})</span>
                @endif
                <a href="{{ route('myfinance2::price-alerts.edit', $alert->id) }}"
                   class="ms-1"
                   target="_blank">edit</a>
            </li>
        @endforeach
    </ul>
</div>
@endif
