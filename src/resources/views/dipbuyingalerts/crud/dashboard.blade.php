@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@extends('layouts.app')
@section('template_title', 'Dip Buying Plan Alerts')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::dipbuyingalerts.partials.drawdown-chart-card')
                <div class="clearfix mb-3"></div>
                <div class="card card-default">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Dip Buying Plan Alerts</span>
                        @include('myfinance2::dipbuyingalerts.partials.subnav', ['active' => 'settings'])
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            As the market falls, this tells you how much of your dip-buying cash to put
                            in at each level, so you neither spend it all too soon nor leave it sitting
                            idle. <br>It just paces cash you were keeping for dips anyway; it will not
                            necessarily beat simply staying invested. Off by default.
                        </p>

                        <form method="POST" action="{{ route('myfinance2::dip-buying-alerts.save') }}">
                            @csrf

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3 ms-3">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="dip-enabled" name="enabled" value="1"
                                               {{ $setting->status === \ovidiuro\myfinance2\App\Models\DipBuyingSetting::ENABLED ? 'checked' : '' }}>
                                        <label class="form-check-label" for="dip-enabled">
                                            Enabled (show the panel on /positions and evaluate the plan)
                                        </label>
                                    </div>

                                    @php
                                        // The pool number input / data attribute take a raw, separator-free
                                        // amount with cents dropped on four-figure-plus pools (see
                                        // MoneyFormat::get_formatted_amount_input_plain).
                                        // The read-only cash readout keeps its thousands separator.
                                        $cashDec = (float) $currentCashEur >= 1000 ? 0 : 2;
                                    @endphp
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold" for="dip-pool">
                                            Fallback dip-buying pool (EUR)
                                            <span class="text-muted fw-normal">
                                                used only when there is no actual cash at a dip's start
                                            </span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" class="form-control"
                                                   id="dip-pool" name="pool_amount_eur"
                                                   value="{{ MoneyFormat::get_formatted_amount_input_plain(old('pool_amount_eur', $suggestedPool)) }}">
                                            @if (!is_null($currentCashEur))
                                                <button type="button" class="btn btn-outline-secondary"
                                                        id="dip-use-cash"
                                                        data-cash="{{ MoneyFormat::get_formatted_amount_input_plain($currentCashEur) }}"
                                                        data-bs-toggle="tooltip"
                                                        title="Use your current total cash across all accounts">
                                                    Use cash
                                                </button>
                                            @endif
                                        </div>
                                        @if (!is_null($currentCashEur))
                                            <div class="form-text">
                                                @if ((float) $setting->pool_amount_eur <= 0)
                                                    Prefilled from your current cash across all accounts
                                                    (&euro;{{ MoneyFormat::get_formatted_number_plain((float) $currentCashEur, $cashDec) }},
                                                    from the /positions user overview). Adjust to the share you
                                                    actually earmark for dips.
                                                @else
                                                    Current cash across all accounts:
                                                    &euro;{{ MoneyFormat::get_formatted_number_plain((float) $currentCashEur, $cashDec) }}
                                                    (from the /positions user overview).
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold" for="dip-bands">
                                            Ladder
                                            @if ($usingDefault)
                                                <span class="badge bg-secondary">default</span>
                                            @else
                                                <span class="badge bg-info">custom</span>
                                            @endif
                                        </label>
                                        <div class="small text-muted mb-2">
                                            Cumulative % of the pool to deploy at each drawdown depth.
                                            <br>Leave blank for the default, or paste e.g.
                                            <code>[{"dd":10,"target":30},{"dd":20,"target":75}]</code>
                                        </div>
                                        <textarea class="form-control font-monospace small" id="dip-bands"
                                                  name="bands" rows="3"
                                                  placeholder="Optional, leave blank for the default"
                                        >{{ old('bands', $setting->bands ? json_encode($setting->bands) : '') }}</textarea>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3 ms-3">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="dip-email" name="email_enabled" value="1"
                                               {{ $setting->email_enabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="dip-email">
                                            Email alerts (daily, after the EU close; fires only on a state change)
                                        </label>
                                    </div>

                                    <div class="mb-3">
                                        <div class="table-responsive mb-2">
                                            <table class="table table-sm mb-0">
                                                <thead>
                                                    <tr><th>Drawdown</th><th class="text-end">Target deployed</th></tr>
                                                </thead>
                                                <tbody>
                                                @foreach ($bands as $band)
                                                    @php
                                                        $ddLabel = $band['target'] <= 0
                                                            ? '<' . (int) ($bands[$loop->index + 1]['dd'] ?? $band['dd']) . '%'
                                                            : (int) $band['dd'] . '%+';
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $ddLabel }}</td>
                                                        <td class="text-end">
                                                            {{ (int) $band['target'] }}%
                                                            <span class="dip-band-eur text-muted small ms-1"
                                                                  data-target="{{ (float) $band['target'] }}"></span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="form-text">
                                            Euro amounts here preview this configured pool. The live
                                            /positions plan and the backtest deploy against your actual cash
                                            at each dip's start instead, so those euro figures can differ.
                                        </div>
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
    @include('myfinance2::dipbuyingalerts.scripts.drawdown-chart')
    <script type="module">
        const useCashBtn = document.getElementById('dip-use-cash');
        const poolInput  = document.getElementById('dip-pool');

        // Show each ladder band's EUR target (target% of the pool) beside its percentage, in a muted
        // secondary color, live from the pool input. Hidden when no pool is set.
        const bandEurEls = document.querySelectorAll('.dip-band-eur');
        // Match the /positions ladder's MoneyFormat output ("€31,607"): comma thousands, euro prefix.
        const fmtEur = (v) => '€' + Math.round(v).toLocaleString('en-US');
        function dipUpdateBandEur() {
            const pool = parseFloat(poolInput ? poolInput.value : '') || 0;
            bandEurEls.forEach((el) => {
                const t = parseFloat(el.dataset.target) || 0;
                el.textContent = (pool > 0 && t > 0) ? fmtEur(t / 100 * pool) : '';
            });
        }

        if (poolInput) {
            poolInput.addEventListener('input', dipUpdateBandEur);
            dipUpdateBandEur();
        }
        if (useCashBtn && poolInput) {
            useCashBtn.addEventListener('click', () => {
                poolInput.value = useCashBtn.dataset.cash;
                dipUpdateBandEur();
            });
        }
    </script>
@endsection
