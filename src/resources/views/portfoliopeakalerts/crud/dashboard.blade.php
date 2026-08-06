@extends('layouts.app')
@section('template_title', 'Portfolio Peak Alerts')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-default mb-3">
                    <div class="card-header">
                        <span>Current standing</span>
                        <span class="text-muted small ms-2">
                            live proximity of each metric to its rolling window high
                        </span>
                    </div>
                    <div class="card-body">
                        @php
                            $preview = $preview ?? ['breakdown' => [], 'change_eur_current' => null, 'change_pct_current' => null];
                        @endphp
                        @if (!is_null($preview['change_pct_current']) || !is_null($preview['change_eur_current']))
                            <p class="small text-muted mb-2">
                                @if (!is_null($preview['change_pct_current']))
                                    Return on cost
                                    <strong>{{ \ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_pct((float) $preview['change_pct_current']) }}%</strong>
                                @endif
                                @if (!is_null($preview['change_eur_current']))
                                    &nbsp;&middot;&nbsp; EUR gain
                                    <strong>&euro;{{ \ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_number_plain((float) $preview['change_eur_current'], 0) }}</strong>
                                @endif
                            </p>
                        @endif
                        @include('myfinance2::portfoliopeakalerts.partials.breakdown-table', [
                            'breakdown' => $preview['breakdown'],
                        ])
                    </div>
                </div>

                <div class="card card-default">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Portfolio Peak Alerts</span>
                        @include('myfinance2::portfoliopeakalerts.partials.subnav', ['active' => 'settings'])
                    </div>
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        <p class="text-muted small mb-1">
                            Emails you when your portfolio's EUR gain or return on cost climbs back near
                            its rolling 6M, 1Y or 2Y high, a hint to consider reducing exposure or
                            rebalancing.
                        </p>
                        <p class="text-muted small">
                            It complements the per-symbol peak-proximity alerts. Off by default.
                        </p>

                        <form method="POST" action="{{ route('myfinance2::portfolio-peak-alerts.save') }}">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3 ms-3">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="ppa-enabled" name="enabled" value="1"
                                               {{ $setting->status === \ovidiuro\myfinance2\App\Models\PortfolioPeakSetting::ENABLED ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ppa-enabled">
                                            Enabled (evaluate the portfolio against its rolling highs)
                                        </label>
                                    </div>

                                    <div class="form-check form-switch mb-3 ms-3">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="ppa-email" name="email_enabled" value="1"
                                               {{ $setting->email_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ppa-email">
                                            Email alerts (checked hourly, at most one email a day)
                                        </label>
                                    </div>
                                    <div class="form-text ms-3">
                                        Checked every hour. The first run of the day that finds an
                                        enabled metric inside its window threshold sends the email;
                                        later runs that day stay quiet. While the condition holds you
                                        get one email per day, and nothing at all on days when no
                                        window is inside its threshold.
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-2 small fw-semibold">Metrics to watch</div>
                                    <div class="form-check form-switch mb-3 ms-3">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="ppa-change-eur" name="change_eur_enabled" value="1"
                                               {{ $setting->exists ? ($setting->change_eur_enabled ? 'checked' : '') : 'checked' }}>
                                        <label class="form-check-label" for="ppa-change-eur">
                                            EUR gain (absolute P&amp;L near its window high)
                                        </label>
                                    </div>
                                    <div class="form-check form-switch mb-3 ms-3">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="ppa-change-pct" name="change_pct_enabled" value="1"
                                               {{ $setting->exists ? ($setting->change_pct_enabled ? 'checked' : '') : 'checked' }}>
                                        <label class="form-check-label" for="ppa-change-pct">
                                            Return on cost (%) near its window high
                                        </label>
                                    </div>
                                    <div class="form-text mb-1">
                                        Either metric can be switched off independently.
                                    </div>
                                    <div class="form-text">
                                        The alert fires when any enabled metric is within its window
                                        threshold.
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-fw fa-save" aria-hidden="true"></i> Save
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="clearfix mb-4"></div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::general.scripts.tooltips')
@endsection
