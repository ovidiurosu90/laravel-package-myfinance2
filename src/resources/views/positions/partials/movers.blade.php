@php
    $moverColumns = [
        ['key' => 'today',   'label' => 'Today'],
        ['key' => 'weekly',  'label' => '1 Week'],
        ['key' => 'monthly', 'label' => '1 Month'],
        ['key' => 'yearly',  'label' => '1 Year'],
        ['key' => 'alltime', 'label' => 'All-time'],
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
    $summaryAlltimeLoser  = ($moversData['alltime']['losers']  ?? [])[0] ?? null;
    $summaryAlltimeGainer = ($moversData['alltime']['gainers'] ?? [])[0] ?? null;
    $hasSummary = $summaryTodayLoser || $summaryTodayGainer
        || $summaryWeekLoser || $summaryWeekGainer
        || $summaryMonthLoser || $summaryMonthGainer
        || $summaryYearLoser || $summaryYearGainer
        || $summaryAlltimeLoser || $summaryAlltimeGainer;
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
                @if($summaryAlltimeLoser || $summaryAlltimeGainer)
                    <span class="text-muted">·</span>
                    <span class="text-muted text-nowrap">All-time</span>
                    @if($summaryAlltimeLoser)
                        <span class="text-danger text-nowrap">
                            <i class="fa fa-caret-down"></i>
                            {{ $summaryAlltimeLoser['symbol'] }}
                            {{ number_format(abs($summaryAlltimeLoser['gain_eur']), 0, '.', ',') }}&nbsp;&euro;
                        </span>
                    @endif
                    @if($summaryAlltimeGainer)
                        <span class="text-success text-nowrap">
                            <i class="fa fa-caret-up"></i>
                            {{ $summaryAlltimeGainer['symbol'] }}
                            {{ number_format($summaryAlltimeGainer['gain_eur'], 0, '.', ',') }}&nbsp;&euro;
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
                        <div class="col-12 col-md-6 col-lg">
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
                                @php
                                    $ptEur = $periodData['portfolio_total_eur'] ?? null;
                                    $ptPct = $periodData['portfolio_total_pct'] ?? 0;
                                    $ptColorClass = ($ptEur !== null && $ptEur >= 0) ? 'text-success' : 'text-danger';
                                    $ptSign = ($ptEur !== null && $ptEur >= 0) ? '+' : '-';
                                @endphp
                                @if($ptEur !== null)
                                <div class="d-flex justify-content-between align-items-baseline
                                    border rounded px-2 py-1 mb-3 {{ $ptColorClass }}"
                                    style="font-size: 0.82rem;">
                                    <small class="text-muted text-uppercase fw-semibold"
                                        style="font-size: 0.7rem; letter-spacing: 0.05em;">Portfolio</small>
                                    <span class="fw-semibold">
                                        {{ $ptSign }}{{ number_format(abs($ptEur), 0, '.', ',') }}&nbsp;&euro;
                                        <small>({{ $ptSign }}{{ number_format(abs($ptPct), 2) }}%)</small>
                                    </span>
                                </div>
                                @endif
                                <div class="mb-1">
                                    <small class="text-muted text-uppercase fw-semibold"
                                        style="font-size: 0.7rem; letter-spacing: 0.05em;">
                                        <i class="fa fa-caret-down me-1"></i>Losers
                                    </small>
                                </div>
                                @for($i = 0; $i < 5; $i++)
                                    @if(isset($periodData['losers'][$i]))
                                        @include('myfinance2::positions.partials.movers-entry', [
                                            'mover' => $periodData['losers'][$i],
                                        ])
                                    @else
                                        <div class="mb-2" style="min-height: 2.85rem;"></div>
                                    @endif
                                @endfor

                                <hr class="my-2">

                                <div class="mb-1">
                                    <small class="text-muted text-uppercase fw-semibold"
                                        style="font-size: 0.7rem; letter-spacing: 0.05em;">
                                        <i class="fa fa-caret-up me-1"></i>Gainers
                                    </small>
                                </div>
                                @for($i = 0; $i < 5; $i++)
                                    @if(isset($periodData['gainers'][$i]))
                                        @include('myfinance2::positions.partials.movers-entry', [
                                            'mover' => $periodData['gainers'][$i],
                                        ])
                                    @else
                                        <div class="mb-2" style="min-height: 2.85rem;"></div>
                                    @endif
                                @endfor

                                @if($col['key'] === 'today')
                                    <ul class="d-lg-none text-muted mt-2 mb-0"
                                        style="font-size: 0.72rem; line-height: 1.5; padding-left: 1rem;">
                                        <li><strong>Ref:</strong> today's day change &times; qty
                                            (from live quote)</li>
                                        <li>Matches <em>Day gain</em> in Open Positions below</li>
                                        <li>Small &euro; differences: Movers sums across all accounts
                                            and converts at today's EUR rate; Open Positions shows
                                            per-account values in account currency</li>
                                    </ul>
                                @endif

                                @if(in_array($col['key'], ['weekly', 'monthly', 'yearly']))
                                    <ul class="d-lg-none text-muted mt-2 mb-0"
                                        style="font-size: 0.72rem; line-height: 1.5; padding-left: 1rem;">
                                        <li><strong>Ref:</strong> market price at period start
                                            &times; qty &mdash; not avg cost (can differ from
                                            All-time)</li>
                                        <li>E.g.: avg cost $200, period started at $250, now $180
                                            &rarr; $7,000 here vs $2,000 all-time
                                            (both &times; 100 shares)</li>
                                        <li>Positions opened mid-period use avg cost instead</li>
                                        <li>User Overview&rsquo;s mvalue delta also includes the
                                            full market value of new positions added during the
                                            period &mdash; numbers won&rsquo;t match when new
                                            capital was deployed</li>
                                    </ul>
                                @endif

                                @if($col['key'] === 'alltime')
                                    <ul class="d-lg-none text-muted mt-2 mb-0"
                                        style="font-size: 0.72rem; line-height: 1.5; padding-left: 1rem;">
                                        <li><strong>Ref:</strong> avg cost &times; qty &mdash;
                                            conceptually matches <em>Gain</em> in Open Positions
                                            (summed across accounts, converted to &euro;)</li>
                                        <li>Movers uses today&rsquo;s EUR rate; Open Positions uses
                                            the rate at each trade&rsquo;s execution time</li>
                                        <li>The gap grows with EUR/USD movement since your positions
                                            were opened</li>
                                    </ul>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
                {{-- Notes row: on lg+ spans correctly across the 5 equal-width columns --}}
                <div class="d-none d-lg-flex mt-2" style="gap: 1rem;">
                    <div style="flex: 1 0 0%;">
                        <ul class="text-muted mb-0"
                            style="font-size: 0.72rem; line-height: 1.5; padding-left: 1rem;">
                            <li><strong>Ref:</strong> today's day change &times; qty
                                (from live quote)</li>
                            <li>Matches <em>Day gain</em> in Open Positions below</li>
                            <li>Small &euro; differences: Movers sums across all accounts and
                                converts at today's EUR rate; Open Positions shows per-account
                                values in account currency</li>
                        </ul>
                    </div>
                    <div style="flex: 3 0 0%;">
                        <ul class="text-muted mb-0"
                            style="font-size: 0.72rem; line-height: 1.5; padding-left: 1rem;">
                            <li><strong>Ref:</strong> market price at period start &times; qty
                                &mdash; not avg cost (can differ from All-time)</li>
                            <li>E.g.: avg cost $200, period started at $250, now $180 &rarr;
                                $7,000 here vs $2,000 all-time (both &times; 100 shares)</li>
                            <li>Positions opened mid-period use avg cost instead</li>
                            <li>User Overview&rsquo;s mvalue delta also includes the full market
                                value of new positions added during the period &mdash; numbers
                                won&rsquo;t match when new capital was deployed</li>
                        </ul>
                    </div>
                    <div style="flex: 1 0 0%;">
                        <ul class="text-muted mb-0"
                            style="font-size: 0.72rem; line-height: 1.5; padding-left: 1rem;">
                            <li><strong>Ref:</strong> avg cost &times; qty &mdash; conceptually
                                matches <em>Gain</em> in Open Positions (summed across accounts,
                                converted to &euro;)</li>
                            <li>Movers uses today&rsquo;s EUR rate; Open Positions uses the rate
                                at each trade&rsquo;s execution time</li>
                            <li>The gap grows with EUR/USD movement since your positions were
                                opened</li>
                        </ul>
                    </div>
                </div>
                @php
                    $totalPortfolioEur = null;
                    foreach ($moversData as $pd) {
                        if (!empty($pd['total_portfolio_eur'])) {
                            $totalPortfolioEur = $pd['total_portfolio_eur'];
                            break;
                        }
                    }
                @endphp
                <ul class="text-muted mt-3 mb-0"
                    style="font-size: 0.72rem; line-height: 1.5; padding-left: 1rem;">
                    <li><strong>&euro;</strong> &mdash; total position gain/loss in EUR</li>
                    <li>
                        <strong>%</strong> &mdash; each position&rsquo;s &euro; gain/loss as a
                        share of today&rsquo;s total portfolio market value
                        @if($totalPortfolioEur !== null)
                            (&euro;{{ number_format($totalPortfolioEur, 0, '.', ',') }})
                        @endif
                        &mdash; a weight, not a return rate
                    </li>
                    <li>Unlike User Overview&nbsp;% (e.g. +81.3% for mvalue = your holdings are
                        currently worth 81.3% of what you paid), Movers&nbsp;% is not relative
                        to cost</li>
                </ul>
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
        window.bootstrap?.Collapse.getOrCreateInstance(moversEl, { toggle: false }).show();
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
