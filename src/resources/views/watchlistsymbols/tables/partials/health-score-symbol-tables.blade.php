{{-- The owned symbols in two tables (Platinum, Gold, Silver and unrated | Bronze and Rust) and how to read them,
     under a link that collapses them. Open by default; the choice is remembered per browser
     (scripts/health-score-card). --}}
@php
    $tierTableCount = count($health_score['platinum_gold_symbols'] ?? [])
        + count($health_score['silver_symbols'] ?? [])
        + count($health_score['unrated_symbols'] ?? [])
        + count($health_score['bronze_rust_symbols'] ?? []);
@endphp
<div class="border-top mt-2 pt-2">
    <div class="small">
        <a class="health-collapse-link fw-semibold text-body text-decoration-none" data-bs-toggle="collapse"
            href="#health-symbol-tables" role="button" aria-expanded="true" aria-controls="health-symbol-tables">
            <i class="fa fa-chevron-right fa-xs me-1" aria-hidden="true"></i>Holdings by tier</a>
        <span class="text-muted">· {{ $tierTableCount }} symbols, Market Settled tiers</span>
    </div>
    <div id="health-symbol-tables" class="collapse show">
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
        @include('myfinance2::watchlistsymbols.tables.partials.health-score-help')
    </div>
</div>
