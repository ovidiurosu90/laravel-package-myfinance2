@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if(!empty($reconAlerts ?? []))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    <h6 class="alert-heading mb-2">
        <i class="fa fa-exclamation-triangle me-1"></i>
        Reconciliation check failed ({{ count($reconAlerts) }})
        <small class="fw-normal ms-1">
            the numbers below do not agree; a computation may be off
        </small>
    </h6>
    <ul class="mb-0 small">
        @foreach($reconAlerts as $alert)
        <li>
            <strong>{{ $alert['subject'] }}</strong>
            {{ $alert['metric_label'] }}:
            {{ $alert['computed_label'] }}
            {!! MoneyFormat::get_formatted_balance($alert['currency'], $alert['computed']) !!}
            vs {{ $alert['shown_label'] }}
            {!! MoneyFormat::get_formatted_balance($alert['currency'], $alert['shown']) !!}
            (diff
            {!! MoneyFormat::get_formatted_balance($alert['currency'], $alert['diff']) !!},
            {{ MoneyFormat::get_formatted_pct($alert['diff_pct']) }}%)
        </li>
        @endforeach
    </ul>
</div>
<div class="clearfix mb-3"></div>
@endif
