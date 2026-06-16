@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    // Everything below is a render-ready view model built in the BE (DipBuyingPresenter), shared with
    // the daily email so the two can never drift. This template only echoes it and maps a status key
    // to its Bootstrap class. No business logic here.
    $present = $dipPresent ?? null;
@endphp
@if (!is_null($present))
@php
    $num     = fn ($v) => MoneyFormat::get_formatted_number_plain((float) $v, 0);
    $summary = $present['summary'];
    $trend   = $present['trend'];
    $above   = $trend['above'] ?? null;
    $period  = $trend['period'] ?? 200;
    $rsiNote = $trend['rsi_note'] ?? null;
    $regime  = $present['regime'];
    $ladder  = $present['ladder'];
    $stall   = $present['stall'];

    // Status key -> Bootstrap classes (web presentation only; the email maps the same keys to hex).
    $ladderStatusClass = [
        'passed_behind' => ['status' => 'text-warning fw-semibold', 'gap' => 'text-warning'],
        'passed_ahead'  => ['status' => 'text-success',            'gap' => 'text-success'],
        'deploy_now'    => ['status' => 'text-danger fw-semibold',  'gap' => 'text-danger'],
        'current'       => ['status' => 'fw-semibold',              'gap' => ''],
        'reserved'      => ['status' => 'text-muted',               'gap' => 'text-muted'],
        'none'          => ['status' => 'text-muted',               'gap' => 'text-muted'],
    ];
@endphp
<style>
    #dip-buying-panel-title .fa-chevron-down {
        transition: transform 0.2s ease;
        transform: rotate(0deg);
    }
    #dip-buying-panel-title.collapsed .fa-chevron-down {
        transform: rotate(90deg);
    }
</style>
<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center gap-3">
            <span id="card_title" class="text-nowrap">Dip Buying Plan</span>
            {{-- Compact summary shown only while the panel is collapsed (toggled in scripts/dip-buying-panel). --}}
            <div id="dip-buying-summary"
                 class="align-items-center gap-2 flex-grow-1 overflow-hidden justify-content-end"
                 style="display: flex;">
                <span class="text-muted text-nowrap">Drawdown</span>
                <span class="text-nowrap fw-semibold" style="color: {{ $summary['dd_color'] }};">
                    -{{ $summary['dd_fmt'] }}%
                </span>
                @if (!is_null($summary['local_dd']))
                    <span class="text-muted">/</span>
                    <span class="text-nowrap fw-semibold" style="color: {{ $summary['local_color'] }};">
                        -{{ $summary['local_dd'] }}%
                    </span>
                @endif
                <span class="text-muted">&middot;</span>
                <span class="text-{{ $summary['status_class'] }} text-nowrap fw-semibold">
                    <i class="fa fa-{{ $summary['status_icon'] }}" aria-hidden="true"></i> {{ $summary['status_label'] }}
                </span>
                <span class="text-muted">&middot;</span>
                <span class="text-muted text-nowrap">Deployed</span>
                <span class="text-nowrap fw-semibold">{{ $summary['deployed_pct'] }}%</span>
                @if (!is_null($summary['local_dd']))
                    <span class="text-muted text-nowrap">(target {{ $summary['target_pct'] }}%)</span>
                @endif
            </div>
            <div class="ms-auto flex-shrink-0">
                <a id="dip-buying-panel-title" class="btn btn-sm collapsed" href="#dip-buying-panel"
                   aria-expanded="false" aria-controls="dip-buying-panel"
                   data-bs-toggle="collapse" title="Expand">
                    <i class="fa fa-chevron-down pull-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div id="dip-buying-panel" class="collapse" aria-labelledby="dip-buying-panel-title">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
                    <span class="label text-muted text-uppercase fw-bold">
                        Drawdown now
                        <i class="fa fa-info-circle" aria-hidden="true"
                           data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                           title="Behavioral pacing for a dip fund you keep anyway. It does not promise to beat staying invested; it stops deploying too early and holding cash too long. The trend rail is context, never a wait signal."></i>
                    </span>
                    <span class="d-flex align-items-baseline gap-2">
                        @if (!is_null($above))
                            <span class="text-nowrap">
                                <i class="fa fa-{{ $above ? 'arrow-up text-success' : 'arrow-down text-danger' }}"
                                   aria-hidden="true"></i>
                                VUSA {{ $above ? 'above' : 'below' }} {{ $period }}-day MA
                                <span class="text-muted">({{ $above ? 'uptrend' : 'downtrend, normal to buy' }})</span>
                            </span>
                        @endif
                        <a href="{{ route('myfinance2::dip-buying-alerts.index') }}"
                           class="btn btn-outline-secondary btn-sm py-0 px-1 lh-1"
                           data-bs-toggle="tooltip" title="Pool size, alerts and ladder settings">
                            <i class="fa fa-fw fa-cog" aria-hidden="true"></i>
                        </a>
                    </span>
                </div>
                @if (!empty($regime))
                    <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0">
                        <thead>
                            <tr class="text-muted">
                                <th>Basis</th>
                                <th class="text-end">
                                    <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                                          title="How far below the running peak (over the lookback window) this basis is now.">Drawdown</span>
                                </th>
                                <th class="text-end">
                                    <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                                          title="How far below the most recent local peak this basis is now: a near-term pullback, shown even when small. Each basis uses its own local peak, so these are not comparable across rows (unlike Drawdown).">Down now</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($regime as $r)
                            <tr>
                                <td class="text-nowrap" style="color: {{ $r['color'] }};">
                                    @if ($r['is_effective'])
                                        <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                                              title="Effective drawdown: the worse of your portfolio's own drawdown and VUSA.AS's at each date. This is the axis the Dip Buying ladder acts on.">{{ $r['label'] }}</span>
                                    @else
                                        {{ $r['label'] }}
                                    @endif
                                </td>
                                <td class="text-end {{ $r['dd_pct'] > 0.005 ? 'text-danger' : 'text-muted' }}">
                                    @if ($r['is_effective'])
                                        <strong>{{ $r['dd_fmt'] }}</strong>
                                        <i class="fa fa-check-circle text-success" aria-hidden="true"
                                           data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                                           title="This is the effective drawdown the ladder on the right actually deploys on."></i>
                                    @else
                                        {{ $r['dd_fmt'] }}
                                    @endif
                                </td>
                                <td class="text-end {{ $r['down_now_pct'] > 0.005 ? 'text-danger' : 'text-muted' }}">
                                    {{ $r['down_now_fmt'] }}
                                    <i class="fa fa-info-circle text-muted" aria-hidden="true"
                                       data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                                       title="{{ $r['down_now_tip'] }}"></i>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                @else
                    <div class="fs-5 fw-semibold">
                        Effective drawdown
                        <span class="{{ $present['effective_dd_pct'] > 0 ? 'text-danger' : '' }}">
                            -{{ $present['dd_fmt'] }}%
                        </span>
                    </div>
                    <div class="small text-muted">driven by: {{ $present['driver'] }}</div>
                    <div class="small">
                        VUSA.AS -{{ MoneyFormat::get_formatted_pct((float) ($present['vusa_dd_pct'] ?? 0)) }}%
                        &middot;
                        Portfolio -{{ MoneyFormat::get_formatted_pct((float) ($present['portfolio_dd_pct'] ?? 0)) }}%
                    </div>
                @endif
                @if ($rsiNote)
                    <div class="small text-muted">
                        RSI(14) {{ MoneyFormat::get_formatted_number_plain((float) $rsiNote['rsi'], 0) }}
                        {{ $rsiNote['oversold'] ? '(oversold)' : '' }}
                    </div>
                @endif
            </div>
            <div class="col-md-6">
                {{-- Mirrors the left column's "Drawdown now" label; the deployed figure is constant across
                     bands, so it lives here once and each ladder row's Gap is measured against it. --}}
                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
                    <span class="label text-muted text-uppercase fw-bold">Deployed so far</span>
                    <span class="d-flex align-items-baseline gap-2">
                        <span class="fw-semibold">{{ round($ladder['deployed_pct'], 1) }}%</span>
                        <span class="text-muted">(&euro;{{ $num($ladder['deployed_eur']) }})</span>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0">
                        <thead>
                            @php
                                $ladderPoolNote = $dipCurrent
                                    ? 'Euro amounts use your actual cash at the start of this dip (€'
                                        . $num($ladder['pool_eur']) . '), not the pool set on the settings page. '
                                        . 'That configured pool is only a fallback, used when no cash is '
                                        . "recorded for the dip's start date."
                                    : 'No active dip, so euro amounts use your configured settings pool (€'
                                        . $num($ladder['pool_eur']) . '). During a dip they switch to your actual '
                                        . "cash at the dip's start.";
                            @endphp
                            <tr class="text-muted">
                                <th>Drawdown</th>
                                <th class="text-end">
                                    <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                                          title="{{ $ladderPoolNote }}">Target</span>
                                </th>
                                <th class="text-end">
                                    <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                                          title="This band's target minus what you have deployed so far. Positive means room to deploy more to reach this band; negative means you are already past it.">Gap</span>
                                </th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($ladder['rows'] as $row)
                            @php
                                $cls = $ladderStatusClass[$row['status_key']] ?? ['status' => '', 'gap' => ''];
                            @endphp
                            <tr class="{{ $row['is_current'] ? 'table-active' : '' }} {{ $row['is_extra'] ? 'dip-ladder-extra d-none' : '' }}">
                                <td>{{ $row['dd_label'] }}</td>
                                <td class="text-end">
                                    {{ (int) $row['target_pct'] }}%
                                    @if ($row['target_pct'] > 0)
                                        <span class="text-muted">&euro;{{ $num($row['target_eur']) }}</span>
                                    @endif
                                </td>
                                <td class="text-end {{ $cls['gap'] }}">
                                    {{ ($row['gap_pct'] >= 0 ? '+' : '-') . round(abs($row['gap_pct']), 1) }}%
                                    {{ ($row['gap_eur'] >= 0 ? '+' : '-') . '€' . $num(abs($row['gap_eur'])) }}
                                </td>
                                <td class="{{ $cls['status'] }}">
                                    @if ($row['status_tip'])
                                        <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                                              title="{{ $row['status_tip'] }}">{{ $row['status_label'] }}</span>
                                    @else
                                        {{ $row['status_label'] }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if ($ladder['collapse'])
                            <tr class="dip-ladder-more-row">
                                <td colspan="4" class="text-center">
                                    <button type="button"
                                            class="btn btn-link btn-sm text-muted p-0 text-decoration-none dip-ladder-more-btn">
                                        &hellip; deeper bands
                                    </button>
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (!empty($dipCurrent))
            {{-- Identical computation as the /dip-buying-alerts/backtest page's current episode (same
                 cash-at-peak pool, net-deployed and guided target), via one shared partial. --}}
            <div class="mt-3">
                @include('myfinance2::dipbuyingalerts.partials.current-episode-card', [
                    'ce'        => $dipCurrent,
                    'firstBand' => $dipFirstBand ?? null,
                    'cardClass' => 'mb-0',
                ])
            </div>
        @else
            <div class="small text-muted mt-3">
                <i class="fa fa-shield" aria-hidden="true"></i>
                No current dip: at -{{ $present['dd_fmt'] }}% you are above the ladder's first band, so the guided plan
                deploys nothing yet.
            </div>
        @endif
        @if (!empty($stall['active']))
            <div class="small text-warning mt-1">
                <i class="fa fa-hourglass-half" aria-hidden="true"></i>
                Stall backstop active ({{ $stall['months_stalled'] }} mo): releasing the rest on a slow
                monthly schedule.
            </div>
        @endif
    </div>
    </div>
</div>
<div class="clearfix mb-3"></div>
@endif
