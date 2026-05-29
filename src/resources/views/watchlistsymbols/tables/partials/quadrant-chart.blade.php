@php
use ovidiuro\myfinance2\App\Services\QuadrantClassifier;

$rt = QuadrantClassifier::RETURN_THRESHOLD;
$dd = QuadrantClassifier::DRAWDOWN_THRESHOLD;

$quadrantConfig = [
    QuadrantClassifier::STEADY_GROWERS   => [
        'bg' => '#d1f5e0', 'label' => '#1a7a3c',
        'title' => 'Steady Growers', 'action' => 'Accumulate', 'id' => 'qdt-steady',
        'hint' => "gain ≥ {$rt}%,  risk ≤ {$dd}x",
    ],
    QuadrantClassifier::VOLATILE_WINNERS => [
        'bg' => '#fef3cd', 'label' => '#856404',
        'title' => 'Volatile Winners', 'action' => 'Hold', 'id' => 'qdt-volatile',
        'hint' => "gain ≥ {$rt}%,  risk > {$dd}x",
    ],
    QuadrantClassifier::DEAD_WEIGHT      => [
        'bg' => '#e9ecef', 'label' => '#495057',
        'title' => 'Dead Weight', 'action' => 'Reduce', 'id' => 'qdt-dead',
        'hint' => "gain < {$rt}%,  risk ≤ {$dd}x",
    ],
    QuadrantClassifier::DANGER_ZONE      => [
        'bg' => '#f8d7da', 'label' => '#842029',
        'title' => 'Danger Zone', 'action' => 'Exit', 'id' => 'qdt-danger',
        'hint' => "gain < {$rt}%,  risk > {$dd}x",
    ],
];

// Per-symbol rows and 1Y quadrant counts are built in the BE (QuadrantChartBuilder) and
// passed in as $quadrant, so this partial stays presentation-only. $symbolsData is also
// read by the scripts.quadrant-chart partial.
$symbolsData = $quadrant['symbols'] ?? [];
$initCounts  = $quadrant['init_counts'] ?? [
    QuadrantClassifier::STEADY_GROWERS   => ['total' => 0, 'owned' => 0],
    QuadrantClassifier::VOLATILE_WINNERS => ['total' => 0, 'owned' => 0],
    QuadrantClassifier::DEAD_WEIGHT      => ['total' => 0, 'owned' => 0],
    QuadrantClassifier::DANGER_ZONE      => ['total' => 0, 'owned' => 0],
];

$summaryCfg = [
    QuadrantClassifier::STEADY_GROWERS   => ['id' => 'qcs-accumulate', 'label' => 'Accumulate', 'color' => '#1a7a3c'],
    QuadrantClassifier::VOLATILE_WINNERS => ['id' => 'qcs-hold',       'label' => 'Hold',       'color' => '#856404'],
    QuadrantClassifier::DEAD_WEIGHT      => ['id' => 'qcs-reduce',     'label' => 'Reduce',     'color' => '#495057'],
    QuadrantClassifier::DANGER_ZONE      => ['id' => 'qcs-exit',       'label' => 'Exit',       'color' => '#842029'],
];
@endphp

<style>
    table[id^="qdt-"] th:nth-child(2),
    table[id^="qdt-"] td:nth-child(2) { border-left: 1px solid var(--bs-border-color); }
    table[id^="qdt-"] th:nth-child(4),
    table[id^="qdt-"] td:nth-child(4) { border-left: 1px solid var(--bs-border-color); }
    table[id^="qdt-"] th:nth-child(5),
    table[id^="qdt-"] td:nth-child(5) { border-right: 1px solid var(--bs-border-color); }
</style>

<div class="card mb-3">
    <div class="card-header py-2">
        <div class="d-flex align-items-center gap-3">
            <span class="fw-semibold text-nowrap">
                Risk vs. Gain Quadrant
                <small class="text-muted fw-normal ms-2">
                    Market return vs. relative drawdown
                </small>
            </span>
            <div id="quadrant-chart-summary"
                class="align-items-center gap-2 flex-grow-1 overflow-hidden justify-content-end"
                style="display: flex;">
                @foreach($summaryCfg as $q => $cfg)
                @php $cnt = $initCounts[$q]; $watchlist = $cnt['total'] - $cnt['owned']; @endphp
                <span id="{{ $cfg['id'] }}" class="text-nowrap" data-bs-toggle="tooltip"
                    title="{{ $cnt['owned'] }} owned · {{ $watchlist }} watchlist only">
                    <span style="color:{{ $cfg['color'] }}">{{ $cfg['label'] }} {{ $cnt['total'] }}</span>
                </span>
                @if(!$loop->last)<span class="text-muted">·</span>@endif
                @endforeach
            </div>
            <div class="ms-auto flex-shrink-0">
                <a id="quadrant-chart-toggle" class="btn btn-sm collapsed"
                    href="#quadrant-chart-collapse"
                    aria-expanded="false"
                    aria-controls="quadrant-chart-collapse"
                    data-bs-toggle="collapse"
                    title="Collapse">
                    <i class="fa fa-chevron-down pull-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div id="quadrant-chart-collapse" class="collapse">
        <div class="card-body p-2">

            <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                <small class="text-muted">Watchlist period:</small>
                <div class="btn-group btn-group-sm" id="quadrant-period-btns">
                    <button type="button" class="btn btn-outline-secondary" data-period="3m">3M</button>
                    <button type="button" class="btn btn-outline-secondary" data-period="6m">6M</button>
                    <button type="button" class="btn btn-outline-secondary active" data-period="1y">1Y</button>
                    <button type="button" class="btn btn-outline-secondary" data-period="2y">2Y</button>
                </div>
                <small class="text-muted">
                    Gain = raw return for 3M/6M; <b>C</b>ompound <b>A</b>nnual <b>G</b>rowth <b>R</b>ate for 1Y/2Y.
                    Benchmark: VUSA.AS.
                </small>
            </div>

            <div class="row gy-2">
                @foreach([
                    [QuadrantClassifier::STEADY_GROWERS,   QuadrantClassifier::VOLATILE_WINNERS],
                    [QuadrantClassifier::DEAD_WEIGHT,      QuadrantClassifier::DANGER_ZONE],
                ] as $quadrantRow)
                @foreach($quadrantRow as $q)
                @php $cfg = $quadrantConfig[$q]; @endphp
                <div class="col-md-6 {{ $loop->first ? 'pe-1' : 'ps-1' }}">
                    <div class="card h-100" style="border-color:{{ $cfg['bg'] }}">
                        <div class="card-header py-2 fw-semibold"
                             style="background-color:{{ $cfg['bg'] }};color:{{ $cfg['label'] }}">
                            {{ $cfg['title'] }}
                            <small class="fw-normal ms-1" style="opacity:0.8">
                                &rarr; {{ $cfg['action'] }}
                            </small>
                            <small class="fw-normal ms-2" style="opacity:0.6">
                                ({{ $cfg['hint'] }})
                            </small>
                        </div>
                        <div class="card-body p-1">
                            <table id="{{ $cfg['id'] }}" class="table table-sm table-hover mb-0 w-100">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th class="text-end">Gain</th>
                                        <th class="text-end">Risk</th>
                                        <th class="text-center">Owned Now</th>
                                        <th class="text-center">Owned Ever</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endforeach
                @endforeach
            </div>

            <div class="border-top mt-2 pt-2 small text-muted" style="line-height:1.5">
                <span class="fw-semibold text-body">How it is built:</span>
                <span class="fw-semibold">Gain</span> is the market return vs. VUSA.AS: raw for 3M/6M, CAGR for
                1Y/2Y. <span class="fw-semibold">Risk</span> is the relative drawdown: the symbol's max drawdown
                divided by VUSA.AS's over the same period (1.0x = benchmark level; higher = more volatile).
                Symbols with no market data for the selected period are excluded.
            </div>
            <div class="border-top mt-2 pt-2 small text-muted" style="line-height:1.8">
                <span class="fw-semibold text-body">The four quadrants</span> are defined by gain &ge; {{ $rt }}%
                and risk &le; {{ $dd }}x.
                <ul class="mb-1 mt-1 ps-3">
                    <li>
                        <span class="fw-semibold" style="color:#1a7a3c">Steady Growers</span>:
                        strong return, contained volatility.
                    </li>
                    <li>
                        <span class="fw-semibold" style="color:#856404">Volatile Winners</span>:
                        strong return, but drawdowns exceed the benchmark. Worth holding; position sizing matters.
                    </li>
                    <li>
                        <span class="fw-semibold" style="color:#495057">Dead Weight</span>:
                        underperforming but not particularly volatile. Capital could be better deployed.
                    </li>
                    <li>
                        <span class="fw-semibold" style="color:#842029">Danger Zone</span>:
                        underperforming and more volatile than the benchmark. Priority exit candidates.
                    </li>
                </ul>
            </div>
            <div class="border-top mt-2 pt-2 small text-muted" style="line-height:1.5">
                <span class="fw-semibold text-body">How to use it:</span>
                the quadrant gives a signal, not a verdict.
                <span class="fw-semibold" style="color:#1a7a3c">Accumulate</span> Steady Growers when they fit your
                allocation. <span class="fw-semibold" style="color:#856404">Hold</span> Volatile Winners with
                controlled sizing. <span class="fw-semibold" style="color:#495057">Reduce</span> Dead Weight to free
                up capital. <span class="fw-semibold" style="color:#842029">Exit</span> Danger Zone positions unless
                you have a specific reason to stay. Use the period selector to check consistency: a symbol that lands
                in the same quadrant across 3M, 6M, 1Y, and 2Y is a much stronger signal than one that flips.
            </div>

        </div>
    </div>
</div>
