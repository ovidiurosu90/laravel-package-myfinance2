@php
    $moverColumns = [
        ['key' => 'today',   'label' => 'Today'],
        ['key' => 'weekly',  'label' => '1 Week'],
        ['key' => 'monthly', 'label' => '1 Month'],
        ['key' => 'yearly',  'label' => '1 Year'],
    ];
    $hasAnyData = !empty(array_filter($moversData ?? [], fn($v) => !is_null($v)));
    foreach ($moverColumns as &$col) {
        $colData = $moversData[$col['key']] ?? null;
        $col['sub_label'] = null;
        $col['sub_label_tooltip'] = null;
        if ($col['key'] === 'today' && !empty($colData['date_label'])) {
            $col['sub_label'] = $colData['date_label'];
            if ($colData['date_label'] !== date('M j')) {
                $col['sub_label_tooltip'] = 'Markets were closed today. Showing the last trading day ('
                    . $colData['date_label'] . ').';
            }
        } elseif (!empty($colData['period_label'])) {
            $col['sub_label'] = $colData['period_label'];
        }
    }
    unset($col);

    $summaryTodayLoser   = ($moversData['today']['losers']    ?? [])[0] ?? null;
    $summaryTodayGainer  = ($moversData['today']['gainers']   ?? [])[0] ?? null;
    $summaryWeekLoser    = ($moversData['weekly']['losers']   ?? [])[0] ?? null;
    $summaryWeekGainer   = ($moversData['weekly']['gainers']  ?? [])[0] ?? null;
    $summaryMonthLoser   = ($moversData['monthly']['losers']  ?? [])[0] ?? null;
    $summaryMonthGainer  = ($moversData['monthly']['gainers'] ?? [])[0] ?? null;
    $summaryYearLoser    = ($moversData['yearly']['losers']   ?? [])[0] ?? null;
    $summaryYearGainer   = ($moversData['yearly']['gainers']  ?? [])[0] ?? null;
    $hasSummary = $summaryTodayLoser || $summaryTodayGainer
        || $summaryWeekLoser || $summaryWeekGainer
        || $summaryMonthLoser || $summaryMonthGainer
        || $summaryYearLoser || $summaryYearGainer;
@endphp

<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center gap-3">
            <span id="card_title" class="text-nowrap">
                {!! trans('myfinance2::positions.titles.biggest-movers') !!}
            </span>
            @if($hasSummary)
            <div id="movers-summary"
                class="d-flex align-items-center gap-2 flex-grow-1 overflow-hidden"
                style="font-size: 0.8rem;">
                <span class="text-muted text-nowrap">Today</span>
                @if($summaryTodayLoser)
                    <span class="text-danger text-nowrap">
                        <i class="fa fa-caret-down"></i>
                        {{ $summaryTodayLoser['symbol'] }}
                        {{ number_format(abs($summaryTodayLoser['gain_eur']), 0, '.', ',') }}&nbsp;&euro;
                    </span>
                @endif
                @if($summaryTodayGainer)
                    <span class="text-success text-nowrap">
                        <i class="fa fa-caret-up"></i>
                        {{ $summaryTodayGainer['symbol'] }}
                        {{ number_format($summaryTodayGainer['gain_eur'], 0, '.', ',') }}&nbsp;&euro;
                    </span>
                @endif
                @if($summaryWeekLoser || $summaryWeekGainer)
                    <span class="text-muted">·</span>
                    <span class="text-muted text-nowrap">1W</span>
                    @if($summaryWeekLoser)
                        <span class="text-danger text-nowrap">
                            <i class="fa fa-caret-down"></i>
                            {{ $summaryWeekLoser['symbol'] }}
                            {{ number_format(abs($summaryWeekLoser['gain_eur']), 0, '.', ',') }}&nbsp;&euro;
                        </span>
                    @endif
                    @if($summaryWeekGainer)
                        <span class="text-success text-nowrap">
                            <i class="fa fa-caret-up"></i>
                            {{ $summaryWeekGainer['symbol'] }}
                            {{ number_format($summaryWeekGainer['gain_eur'], 0, '.', ',') }}&nbsp;&euro;
                        </span>
                    @endif
                @endif
                @if($summaryMonthLoser || $summaryMonthGainer)
                    <span class="text-muted">·</span>
                    <span class="text-muted text-nowrap">1M</span>
                    @if($summaryMonthLoser)
                        <span class="text-danger text-nowrap">
                            <i class="fa fa-caret-down"></i>
                            {{ $summaryMonthLoser['symbol'] }}
                            {{ number_format(abs($summaryMonthLoser['gain_eur']), 0, '.', ',') }}&nbsp;&euro;
                        </span>
                    @endif
                    @if($summaryMonthGainer)
                        <span class="text-success text-nowrap">
                            <i class="fa fa-caret-up"></i>
                            {{ $summaryMonthGainer['symbol'] }}
                            {{ number_format($summaryMonthGainer['gain_eur'], 0, '.', ',') }}&nbsp;&euro;
                        </span>
                    @endif
                @endif
                @if($summaryYearLoser || $summaryYearGainer)
                    <span class="text-muted">·</span>
                    <span class="text-muted text-nowrap">1Y</span>
                    @if($summaryYearLoser)
                        <span class="text-danger text-nowrap">
                            <i class="fa fa-caret-down"></i>
                            {{ $summaryYearLoser['symbol'] }}
                            {{ number_format(abs($summaryYearLoser['gain_eur']), 0, '.', ',') }}&nbsp;&euro;
                        </span>
                    @endif
                    @if($summaryYearGainer)
                        <span class="text-success text-nowrap">
                            <i class="fa fa-caret-up"></i>
                            {{ $summaryYearGainer['symbol'] }}
                            {{ number_format($summaryYearGainer['gain_eur'], 0, '.', ',') }}&nbsp;&euro;
                        </span>
                    @endif
                @endif
            </div>
            @endif
            <div class="ms-auto flex-shrink-0">
                <a id="biggest-movers-title" class="btn btn-sm" href="#biggest-movers"
                    aria-expanded="false" aria-controls="biggest-movers"
                    data-bs-toggle="collapse" title="Expand">
                    <i class="fa fa-chevron-right pull-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div id="biggest-movers" class="collapse" aria-labelledby="biggest-movers-title">
        <div class="card-body">
            @if(!$hasAnyData)
                <p class="text-muted mb-0">
                    <i class="fa fa-spinner fa-spin me-1"></i>
                    Movers data is being calculated... (refreshes every minute)
                </p>
            @else
                <div class="row g-3">
                    @foreach($moverColumns as $col)
                        @php $periodData = $moversData[$col['key']] ?? null; @endphp
                        <div class="col-12 col-md-6 col-lg-3">
                            @php
                                $subLabelHtml = '';
                                if (!empty($col['sub_label'])) {
                                    $tooltipSpan = !empty($col['sub_label_tooltip'])
                                        ? ' <span data-bs-toggle="tooltip" data-bs-placement="top"'
                                          . ' data-bs-title="' . e($col['sub_label_tooltip']) . '">'
                                          . '&#9432;</span>'
                                        : '';
                                    $subLabelHtml = '<small class="fw-normal ms-1" style="font-size: 0.8em;">'
                                        . '(' . e($col['sub_label']) . $tooltipSpan . ')</small>';
                                }
                            @endphp
                            <h6 class="text-muted mb-2 border-bottom pb-1">
                                {{ $col['label'] }}{!! $subLabelHtml !!}
                            </h6>

                            @if(is_null($periodData))
                                <p class="text-muted small mb-0">
                                    <i class="fa fa-spinner fa-spin me-1"></i> Calculating...
                                </p>
                            @else
                                @if(!empty($periodData['losers']))
                                    <div class="mb-1">
                                        <small class="text-muted text-uppercase fw-semibold"
                                            style="font-size: 0.7rem; letter-spacing: 0.05em;">
                                            <i class="fa fa-caret-down me-1"></i>Losers
                                        </small>
                                    </div>
                                    @foreach($periodData['losers'] as $mover)
                                        @include('myfinance2::positions.partials.movers-entry', [
                                            'mover' => $mover,
                                        ])
                                    @endforeach
                                @endif

                                @if(!empty($periodData['losers']) && !empty($periodData['gainers']))
                                    <hr class="my-2">
                                @endif

                                @if(!empty($periodData['gainers']))
                                    <div class="mb-1">
                                        <small class="text-muted text-uppercase fw-semibold"
                                            style="font-size: 0.7rem; letter-spacing: 0.05em;">
                                            <i class="fa fa-caret-up me-1"></i>Gainers
                                        </small>
                                    </div>
                                    @foreach($periodData['gainers'] as $mover)
                                        @include('myfinance2::positions.partials.movers-entry', [
                                            'mover' => $mover,
                                        ])
                                    @endforeach
                                @endif

                                @if(empty($periodData['losers']) && empty($periodData['gainers']))
                                    <p class="text-muted small mb-0">No movers</p>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <strong>&euro;</strong> — total position gain/loss in EUR.
                    <strong>%</strong> — share of total portfolio.
                </p>
            @endif
        </div>
    </div>
</div>

<script type="module">
    const moversEl = document.getElementById('biggest-movers');
    const moversTitleEl = document.getElementById('biggest-movers-title');
    const iconEl = moversTitleEl?.querySelector('i');
    const summaryEl = document.getElementById('movers-summary');

    // Restore saved state (default: collapsed)
    if (localStorage.getItem('movers-collapsed') === 'expanded') {
        summaryEl?.classList.add('d-none');
        bootstrap.Collapse.getOrCreateInstance(moversEl, { toggle: false }).show();
    }

    moversEl?.addEventListener('show.bs.collapse', () => {
        localStorage.setItem('movers-collapsed', 'expanded');
        iconEl?.classList.replace('fa-chevron-right', 'fa-chevron-down');
        moversTitleEl?.setAttribute('title', 'Collapse');
        moversTitleEl?.setAttribute('aria-expanded', 'true');
        summaryEl?.classList.add('d-none');
    });

    moversEl?.addEventListener('hide.bs.collapse', () => {
        localStorage.setItem('movers-collapsed', 'collapsed');
        iconEl?.classList.replace('fa-chevron-down', 'fa-chevron-right');
        moversTitleEl?.setAttribute('title', 'Expand');
        moversTitleEl?.setAttribute('aria-expanded', 'false');
        summaryEl?.classList.remove('d-none');
    });
</script>
