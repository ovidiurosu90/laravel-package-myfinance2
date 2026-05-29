@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
@if(!empty($health_score))
@php
    $signal = $health_score['signal'];
    $signalClass = [
        'healthy' => 'success',
        'caution' => 'warning',
        'warning' => 'danger',
    ][$signal] ?? 'secondary';
@endphp
<div class="card mb-3">
    <div class="card-header py-2">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-{{ $signalClass }}"
                data-bs-toggle="tooltip"
                title="{{ $health_score['signal_message'] }}">{{ ucfirst($signal) }}</span>
            <span class="fw-semibold text-nowrap">
                Portfolio Health <small class="text-muted fw-normal ms-1">Tier allocation by return</small>
            </span>
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
                    {!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['platinum_gold_mvalue_eur']) !!}
                    ({{ $health_score['platinum_gold_pct'] }}%)
                </span>
                <span class="text-muted">·</span>
                <span class="text-nowrap">
                    <span class="badge bg-secondary"
                        data-bs-toggle="tooltip"
                        title="annualized gain &gt;5%">Silver</span>
                    {!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['silver_mvalue_eur']) !!}
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
                    {!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['bronze_rust_mvalue_eur']) !!}
                    ({{ $health_score['bronze_rust_pct'] }}%)
                </span>
            </div>
            <div class="ms-auto flex-shrink-0">
                <a id="portfolio-health-toggle" class="btn btn-sm collapsed"
                    href="#portfolio-health-body"
                    aria-expanded="false"
                    aria-controls="portfolio-health-body"
                    data-bs-toggle="collapse"
                    title="Collapse">
                    <i class="fa fa-chevron-down pull-right"></i>
                </a>
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
                        <span class="badge bg-info text-dark" data-bs-toggle="tooltip" title="annualized gain &gt;15%">Platinum</span>
                        + <span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="annualized gain &gt;10%">Gold</span>
                    </td>
                    <td colspan="2" class="px-1 border-end">target</td>
                    <td colspan="2" class="text-end px-1">current</td>
                    <td class="text-center px-1" style="white-space:normal"><span class="badge bg-secondary" data-bs-toggle="tooltip" title="annualized gain &gt;5%">Silver</span></td>
                    <td colspan="2" class="px-1 border-end">target</td>
                    <td colspan="2" class="text-end px-1">current</td>
                    <td class="text-center px-1" style="white-space:normal">
                        <span class="badge bg-orange text-white" style="background-color:#e67e22!important;"
                            data-bs-toggle="tooltip" title="annualized gain &ge;0%">Bronze</span>
                        + <span class="badge bg-danger" data-bs-toggle="tooltip" title="annualized gain &lt;0%">Rust</span>
                    </td>
                    <td colspan="2" class="px-1">target</td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-end px-1 border-end text-nowrap">
                        <span class="fw-semibold" style="color:{{ $health_score['mvalue_color'] }}">mvalue</span> =
                        <span class="fw-semibold">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['total_mvalue_eur']) !!}</span>
                    </td>
                    <td class="text-end px-1"><span class="fw-semibold">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['platinum_gold_mvalue_eur']) !!}</span></td>
                    <td class="text-end px-1"><span class="fw-semibold">{{ $health_score['platinum_gold_pct'] }}%</span></td>
                    <td class="px-1" style="width:33%">
                        <div class="progress position-relative" style="height:8px;border:1px solid rgba(0,0,0,0.15);">
                            <div class="progress-bar bg-warning" role="progressbar"
                                style="width: {{ min($health_score['platinum_gold_pct'], 100) }}%"></div>
                            <div class="position-absolute top-0 bottom-0"
                                style="left:{{ $health_score['platinum_gold_target'] }}%;width:2px;background:rgba(0,0,0,0.25);"></div>
                        </div>
                    </td>
                    <td class="px-1"><span class="text-muted">{{ MoneyFormat::get_formatted_number_plain($health_score['platinum_gold_target'], 1) }}%</span></td>
                    <td class="px-1 border-end"><span class="text-muted">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['platinum_gold_target_eur']) !!}</span></td>
                    <td class="text-end px-1"><span class="fw-semibold">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['silver_mvalue_eur']) !!}</span></td>
                    <td class="text-end px-1"><span class="fw-semibold">{{ $health_score['silver_pct'] }}%</span></td>
                    <td class="px-1" style="width:33%">
                        <div class="progress position-relative" style="height:8px;border:1px solid rgba(0,0,0,0.15);">
                            <div class="progress-bar bg-secondary" role="progressbar"
                                style="width: {{ min($health_score['silver_pct'], 100) }}%"></div>
                            <div class="position-absolute top-0 bottom-0"
                                style="left:{{ $health_score['silver_target'] }}%;width:2px;background:rgba(0,0,0,0.25);"></div>
                        </div>
                    </td>
                    <td class="px-1"><span class="text-muted">{{ MoneyFormat::get_formatted_number_plain($health_score['silver_target'], 1) }}%</span></td>
                    <td class="px-1 border-end"><span class="text-muted">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['silver_target_eur']) !!}</span></td>
                    <td class="text-end px-1"><span class="fw-semibold">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['bronze_rust_mvalue_eur']) !!}</span></td>
                    <td class="text-end px-1"><span class="fw-semibold">{{ $health_score['bronze_rust_pct'] }}%</span></td>
                    <td class="px-1" style="width:33%">
                        <div class="progress position-relative" style="height:8px;border:1px solid rgba(0,0,0,0.15);">
                            <div class="progress-bar bg-danger" role="progressbar"
                                style="width: {{ min($health_score['bronze_rust_pct'], 100) }}%"></div>
                            <div class="position-absolute top-0 bottom-0"
                                style="left:{{ $health_score['bronze_rust_target'] }}%;width:2px;background:rgba(0,0,0,0.25);"></div>
                        </div>
                    </td>
                    <td class="px-1"><span class="text-muted">{{ MoneyFormat::get_formatted_number_plain($health_score['bronze_rust_target'], 1) }}%</span></td>
                    <td class="px-1"><span class="text-muted">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['bronze_rust_target_eur']) !!}</span></td>
                </tr>
                <tr>
                    <td class="text-end px-1 border-end text-nowrap">
                        <span class="fw-semibold" style="color:{{ $health_score['cost_color'] }}">cost</span> =
                        {!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['total_cost_eur']) !!}
                    </td>
                    <td class="text-end px-1">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['platinum_gold_cost_eur']) !!}</td>
                    <td class="text-end px-1">{{ $health_score['platinum_gold_cost_pct'] }}%</td>
                    <td class="px-1" style="width:33%">
                        <div class="progress position-relative" style="height:8px;border:1px solid rgba(0,0,0,0.15);">
                            <div class="progress-bar bg-warning" role="progressbar"
                                style="width: {{ min($health_score['platinum_gold_cost_pct'], 100) }}%"></div>
                            <div class="position-absolute top-0 bottom-0"
                                style="left:{{ $health_score['platinum_gold_target'] }}%;width:2px;background:rgba(0,0,0,0.25);"></div>
                        </div>
                    </td>
                    <td class="px-1"><span class="text-muted">{{ MoneyFormat::get_formatted_number_plain($health_score['platinum_gold_target'], 1) }}%</span></td>
                    <td class="px-1 border-end"><span class="text-muted">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['platinum_gold_cost_target_eur']) !!}</span></td>
                    <td class="text-end px-1">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['silver_cost_eur']) !!}</td>
                    <td class="text-end px-1">{{ $health_score['silver_cost_pct'] }}%</td>
                    <td class="px-1" style="width:33%">
                        <div class="progress position-relative" style="height:8px;border:1px solid rgba(0,0,0,0.15);">
                            <div class="progress-bar bg-secondary" role="progressbar"
                                style="width: {{ min($health_score['silver_cost_pct'], 100) }}%"></div>
                            <div class="position-absolute top-0 bottom-0"
                                style="left:{{ $health_score['silver_target'] }}%;width:2px;background:rgba(0,0,0,0.25);"></div>
                        </div>
                    </td>
                    <td class="px-1"><span class="text-muted">{{ MoneyFormat::get_formatted_number_plain($health_score['silver_target'], 1) }}%</span></td>
                    <td class="px-1 border-end"><span class="text-muted">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['silver_cost_target_eur']) !!}</span></td>
                    <td class="text-end px-1">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['bronze_rust_cost_eur']) !!}</td>
                    <td class="text-end px-1">{{ $health_score['bronze_rust_cost_pct'] }}%</td>
                    <td class="px-1" style="width:33%">
                        <div class="progress position-relative" style="height:8px;border:1px solid rgba(0,0,0,0.15);">
                            <div class="progress-bar bg-danger" role="progressbar"
                                style="width: {{ min($health_score['bronze_rust_cost_pct'], 100) }}%"></div>
                            <div class="position-absolute top-0 bottom-0"
                                style="left:{{ $health_score['bronze_rust_target'] }}%;width:2px;background:rgba(0,0,0,0.25);"></div>
                        </div>
                    </td>
                    <td class="px-1"><span class="text-muted">{{ MoneyFormat::get_formatted_number_plain($health_score['bronze_rust_target'], 1) }}%</span></td>
                    <td class="px-1"><span class="text-muted">{!! MoneyFormat::get_formatted_price_display('&euro;', $health_score['bronze_rust_cost_target_eur']) !!}</span></td>
                </tr>
            </tbody>
        </table>
        <div class="row mt-2 gy-2">
            <div class="col-6 pe-1">
                @include('myfinance2::watchlistsymbols.tables.partials.health-score-tier-table', [
                    'tableId'     => 'health-pgs-table',
                    'symbolRows'  => array_merge(
                        $health_score['platinum_gold_symbols'] ?? [],
                        $health_score['silver_symbols'] ?? [],
                        $health_score['unrated_symbols'] ?? []
                    ),
                    'totalMvalue' => $health_score['total_mvalue_eur'],
                    'totalCost'   => $health_score['total_cost_eur'],
                ])
            </div>
            <div class="col-6 ps-1">
                @include('myfinance2::watchlistsymbols.tables.partials.health-score-tier-table', [
                    'tableId'     => 'health-br-table',
                    'symbolRows'  => $health_score['bronze_rust_symbols'] ?? [],
                    'totalMvalue' => $health_score['total_mvalue_eur'],
                    'totalCost'   => $health_score['total_cost_eur'],
                ])
            </div>
        </div>
        <div class="border-top mt-2 pt-2 small text-muted" style="line-height:1.5">
            <span class="fw-semibold" style="color:{{ $health_score['mvalue_color'] }}">mvalue</span> is the primary
            signal: what the portfolio is worth today. The health badge and progress bars are mvalue-based; the badge
            turns Caution when the strong-tier share falls below its target, Warning when Bronze+Rust exceeds its own.
            <span class="fw-semibold" style="color:{{ $health_score['cost_color'] }}">cost</span> is where capital was
            originally committed. The gap between the two rows shows whether a group has grown or given back value
            relative to what was put in.
        </div>
        <div class="border-top mt-2 pt-2 small text-muted" style="line-height:1.8">
            <span class="fw-semibold text-body">Gain/y and tier decision:</span>
            each symbol shows several rows in the Gain/y column: the current open position (muted), the overall
            <abbr title="Compound Annual Growth Rate: the steady yearly rate that compounds to the actual total return; comparable to an index">CAGR</abbr>
            across all holding windows, and the trailing 12-month market return. Below those, when available, are
            <abbr title="Money-weighted return: how your actual euros did, accounting for the timing and size of every buy, sell and dividend">XIRR</abbr>
            (your money's annual rate) and <span class="fw-semibold">vs S&amp;P 500</span>, your CAGR minus the
            VUSA.AS CAGR over the SAME dates you held, so the comparison is apples-to-apples. The
            <span class="fw-semibold">bold</span> row decided the tier; its tooltip confirms "(decides tier)".
            Tiers are assigned in priority order:
            <ol class="mb-1 mt-1 ps-3">
                <li>
                    <span class="fw-semibold text-body">Held &ge; 1 year &rarr; annualized return (CAGR)</span>
                    across all holding windows.
                </li>
                <li>
                    <span class="fw-semibold text-body">Held 90 days to under 1 year &rarr; raw return</span>,
                    not annualized, so a fast short-term gain is never projected into a higher tier.
                </li>
                <li>
                    <span class="fw-semibold text-body">Held under 90 days &rarr; market 1Y</span>.
                    Returns outside a plausible range (&minus;50% to +200%) are treated as artifacts; raw return
                    is used instead
                    <i class="fa fa-exclamation-circle fa-xs text-warning" aria-hidden="true"></i>.
                </li>
            </ol>
            Watchlist-only and exited positions lead with market 1Y, falling back to their realized return if
            unavailable. A manual override always takes precedence.
            A <i class="fa fa-exclamation-circle fa-xs text-warning" aria-hidden="true"></i> icon marks positions
            held under 90 days where market 1Y was unavailable or rejected; the tier is provisional until the
            position matures.
        </div>
    </div>
    </div>
</div>
@endif
