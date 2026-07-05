{{-- Expiry countdown badge for a price alert, used on /price-alerts.
     Expects: $alert (PriceAlert). Renders nothing when it has no expiry. --}}
@if (!empty($alert) && $alert->expires_at)
    <span class="badge {{ $alert->getExpiryBadgeClass() }}"
          data-bs-toggle="tooltip"
          data-bs-placement="top"
          title="Expires: {{ $alert->expires_at->timezone(config('app.timezone'))->format('Y-m-d') }}">
        <i class="fa fa-clock-o" aria-hidden="true"></i> {{ $alert->getExpiryLabel() }}
    </span>
@endif
