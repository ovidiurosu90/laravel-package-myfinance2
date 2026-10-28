@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
@if(!empty($health_score))
@php
    $signal = $health_score['signal'];
    $signalClass = [
        'healthy' => 'success',
        'caution' => 'warning',
        'warning' => 'danger',
    ][$signal] ?? 'secondary';
    $eur        = fn ($value) => MoneyFormat::get_formatted_price_display('&euro;', $value);
    $tierGroups = ['platinum_gold' => 'bg-warning', 'silver' => 'bg-secondary', 'bronze_rust' => 'bg-danger'];
    // Key suffixes per metric row: mvalue (bold, the primary signal) and cost.
    $metricRows = [
        'mvalue' => ['total' => 'total_mvalue_eur', 'amount' => '_mvalue_eur', 'pct' => '_pct',
            'target_eur' => '_target_eur', 'bold' => true],
        'cost'   => ['total' => 'total_cost_eur', 'amount' => '_cost_eur', 'pct' => '_cost_pct',
            'target_eur' => '_cost_target_eur', 'bold' => false],
    ];
    $settledTip = 'Market Settled: each tier is the level a symbol\'s 1Y market return held or beat on most'
        . ' trading days of the last 12 months, so one good or bad day does not move money between groups.'
        . ' An outlined badge next to a row shows today\'s tier when it differs.';
@endphp
<div class="card mb-3">
    <div class="card-header py-2">
        <div class="d-flex align-items-center gap-3">
            <a id="portfolio-health-toggle" class="collapse-title d-flex align-items-center collapsed"
                href="#portfolio-health-body" role="button" aria-expanded="false"
                aria-controls="portfolio-health-body" data-bs-toggle="collapse">
                @include('myfinance2::general.partials.collapse-chevron')
                <span class="badge bg-{{ $signalClass }} me-3"
                    data-bs-toggle="tooltip"
                    title="{{ $health_score['signal_message'] }}">{{ ucfirst($signal) }}</span>
                <span class="fw-semibold text-nowrap">
                    Portfolio Health <small class="text-muted fw-normal ms-1">Tier allocation by return</small>
                    <small class="text-muted fw-normal" data-bs-toggle="tooltip"
                        title="{{ $settledTip }}">· Market Settled tiers</small>
                </span>
            </a>
            <div id="portfolio-health-summary"
                class="align-items-center gap-2 flex-grow-1 overflow-hidden justify-content-end"
                style="display: flex;">
                <span class="text-nowrap">
                    <span class="badge bg-info text-dark"
                        data-bs-toggle="tooltip"
                        title="annualized gain &gt;15%">Platinum</span>
                    + <span class="badge bg-warning text-dark"
                        data-bs-toggle="tooltip"
                        title="annualized gain &gt;10%">Gold</span>
                    {!! $eur($health_score['platinum_gold_mvalue_eur']) !!}
                    ({{ $health_score['platinum_gold_pct'] }}%)
                </span>
                <span class="text-muted">·</span>
                <span class="text-nowrap">
                    <span class="badge bg-secondary"
                        data-bs-toggle="tooltip"
                        title="annualized gain &gt;5%">Silver</span>
                    {!! $eur($health_score['silver_mvalue_eur']) !!}
                    ({{ $health_score['silver_pct'] }}%)
                </span>
                <span class="text-muted">·</span>
                <span class="text-nowrap">
                    <span class="badge bg-orange text-white"
                        style="background-color:#e67e22!important;"
                        data-bs-toggle="tooltip"
                        title="annualized gain &ge;0%">Bronze</span>
                    + <span class="badge bg-danger"
                        data-bs-toggle="tooltip"
                        title="annualized gain &lt;0%">Rust</span>
                    {!! $eur($health_score['bronze_rust_mvalue_eur']) !!}
                    ({{ $health_score['bronze_rust_pct'] }}%)
                </span>
            </div>
        </div>
    </div>
    <div id="portfolio-health-body" class="collapse">
    <div class="card-body py-2 px-3">
        <table class="w-100" style="white-space:nowrap">
            <thead>
                <tr class="text-muted fw-semibold">
                    <td class="text-end px-1 border-end">metric</td>
                    <td colspan="2" class="text-end px-1">current</td>
                    <td class="text-center px-1" style="white-space:normal">
                        <span class="badge bg-info text-dark" data-bs-toggle="tooltip"
                            title="annualized gain &gt;15%">Platinum</span>
                        + <span class="badge bg-warning text-dark" data-bs-toggle="tooltip"
                            title="annualized gain &gt;10%">Gold</span>
                    </td>
                    <td colspan="2" class="px-1 border-end">target</td>
                    <td colspan="2" class="text-end px-1">current</td>
                    <td class="text-center px-1" style="white-space:normal">
                        <span class="badge bg-secondary" data-bs-toggle="tooltip"
                            title="annualized gain &gt;5%">Silver</span>
                    </td>
                    <td colspan="2" class="px-1 border-end">target</td>
                    <td colspan="2" class="text-end px-1">current</td>
                    <td class="text-center px-1" style="white-space:normal">
                        <span class="badge bg-orange text-white" style="background-color:#e67e22!important;"
                            data-bs-toggle="tooltip" title="annualized gain &ge;0%">Bronze</span>
                        + <span class="badge bg-danger" data-bs-toggle="tooltip"
                            title="annualized gain &lt;0%">Rust</span>
                    </td>
                    <td colspan="2" class="px-1">target</td>
                </tr>
            </thead>
            <tbody>
                @foreach($metricRows as $metric => $metricRow)
                <tr>
                    <td class="text-end px-1 border-end text-nowrap">
                        <span class="fw-semibold"
                            style="color:{{ $health_score[$metric . '_color'] }}">{{ $metric }}</span> =
                        <span @class(['fw-semibold' => $metricRow['bold']])
                            >{!! $eur($health_score[$metricRow['total']]) !!}</span>
                    </td>
                    @foreach($tierGroups as $group => $barClass)
                        @include('myfinance2::watchlistsymbols.tables.partials.health-score-group-cells', [
                            'amount'    => $health_score[$group . $metricRow['amount']],
                            'pct'       => $health_score[$group . $metricRow['pct']],
                            'target'    => $health_score[$group . '_target'],
                            'targetEur' => $health_score[$group . $metricRow['target_eur']],
                            'barClass'  => $barClass,
                            'bold'      => $metricRow['bold'],
                            'last'      => $loop->last,
                        ])
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
        @include('myfinance2::watchlistsymbols.tables.partials.health-score-symbol-tables')
        @if(!empty($health_score['approach_comparison']))
            @include('myfinance2::watchlistsymbols.tables.partials.health-score-approach-compare', [
                'comparison' => $health_score['approach_comparison'],
            ])
        @endif
    </div>
    </div>
</div>
@endif
