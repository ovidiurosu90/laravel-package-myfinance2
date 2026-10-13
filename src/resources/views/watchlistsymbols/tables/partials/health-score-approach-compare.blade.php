{{-- Bottom of the health card: the owned symbols' tiers under each way of measuring return, with the
     portfolio split each one gives. $comparison is TierApproachComparison::build(). Each symbol shows its tier
     in use and, outlined, today's tier when it differs, as in the Holdings by tier tables. --}}
@php
    use ovidiuro\myfinance2\App\Services\MoneyFormat;
    use ovidiuro\myfinance2\App\Services\TierApproachCatalog;
    use ovidiuro\myfinance2\App\Services\TierApproachComparison;
    use ovidiuro\myfinance2\App\Services\TierCalculationService;

    $approaches  = $comparison['approaches'];
    $signalClass = ['healthy' => 'success', 'caution' => 'warning', 'warning' => 'danger'];
    $groupRows   = [
        'platinum_gold' => 'Platinum + Gold',
        'silver'        => 'Silver',
        'bronze_rust'   => 'Bronze + Rust',
    ];
    // The allocation block: the three tier groups and the signal they give.
    $allocationRows = count($groupRows) + 1;
    $tradeoffRows = ['pros' => ['Pros', 'text-success'], 'cons' => ['Cons', 'text-danger']];
    $verdicts     = [
        TierApproachCatalog::VERDICT_CHOSEN    => ['Chosen', 'text-primary'],
        TierApproachCatalog::VERDICT_RUNNER_UP => ['Runner-up', 'text-body'],
        TierApproachCatalog::VERDICT_NOT_USED  => ['Not used', 'text-muted'],
    ];
@endphp
@if(!empty($comparison['rows']))
<div class="border-top mt-2 pt-2">
    <div class="small">
        <a class="health-collapse-link fw-semibold text-body text-decoration-none collapsed"
            data-bs-toggle="collapse" href="#tier-approach-compare" role="button" aria-expanded="false"
            aria-controls="tier-approach-compare">
            <i class="fa fa-chevron-right fa-xs me-1" aria-hidden="true"></i>Compare tier approaches</a>
        <span class="text-muted">
            · Decision {{ $comparison['decision']['date'] }}:
            {{ $approaches[$comparison['decision']['approach']]['name'] ?? '' }} drives this card
        </span>
    </div>
    <div id="tier-approach-compare" class="collapse mt-1">
    @include('myfinance2::watchlistsymbols.tables.partials.health-score-approach-decision', [
        'decision' => $comparison['decision'], 'approaches' => $approaches,
    ])
    {{-- The approach in use stands out as a blue column; the symbol rows are striped and highlight on hover, so a
         row can be traced across the seven approaches. Theme-neutral (the emphasis colour), so it reads in dark
         mode. --}}
    <style>
        .tier-approach-compare td.in-use, .tier-approach-compare th.in-use {
            background-color: rgba(13, 110, 253, 0.12);
            box-shadow: inset 2px 0 0 rgba(13, 110, 253, 0.45), inset -2px 0 0 rgba(13, 110, 253, 0.45);
        }
        .tier-approach-compare tbody.symbol-rows tr:nth-child(even) td {
            background-color: rgba(var(--bs-emphasis-color-rgb), 0.05);
        }
        .tier-approach-compare tbody.symbol-rows tr:nth-child(even) td.in-use {
            background-color: rgba(13, 110, 253, 0.18);
        }
        .tier-approach-compare tbody.symbol-rows tr:hover td {
            background-color: rgba(var(--bs-emphasis-color-rgb), 0.1);
        }
        .tier-approach-compare tbody.symbol-rows tr:hover td.in-use {
            background-color: rgba(13, 110, 253, 0.26);
        }
        /* The subtitles, the "Measures" row and the "Verdict" row only on large screens; below, the ⓘ left of
           each approach badge carries all three as a tooltip. */
        .tier-approach-compare .measures-row, .tier-approach-compare .approach-sub { display: none; }
        @media (min-width: 1600px) {
            .tier-approach-compare .measures-row { display: table-row; }
            .tier-approach-compare .approach-sub { display: block; }
            .tier-approach-compare .measures-icon { display: none; }
        }
    </style>
    <table class="w-100 tier-approach-compare" style="font-size:0.75rem;white-space:nowrap;line-height:1.5;
        font-variant-numeric:tabular-nums lining-nums">
        <thead>
            <tr>
                <th colspan="2" class="px-1 border-end"></th>
                @foreach($approaches as $key => $approach)
                @php
                    [$verdictLabel, $verdictClass] = $verdicts[$approach['verdict']];
                    $infoTip = '<div class="fw-semibold">' . e($approach['sub']) . '</div>'
                        . '<div>' . e($approach['measures']) . '</div>'
                        . '<div class="mt-1"><span class="fw-semibold">' . e($verdictLabel) . ':</span> '
                        . e($approach['verdict_text']) . '</div>';
                @endphp
                <th @class(['px-1 text-end', 'in-use' => $approach['in_use']])>
                    <i class="fa fa-info-circle text-muted me-1 measures-icon" aria-hidden="true"
                        data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" data-bs-html="true"
                        title="{{ $infoTip }}"></i>
                    <span class="badge {{ $approach['in_use'] ? 'bg-primary' : 'bg-dark' }}">
                        Approach {{ $approach['number'] }}
                    </span>
                </th>
                @endforeach
            </tr>
            <tr class="align-bottom">
                <th class="px-1">Symbol</th>
                <th class="px-1 text-end border-end">MValue €</th>
                @foreach($approaches as $key => $approach)
                @php
                    $headTip = '<div>' . e($approach['title']) . '</div>';
                    foreach ($tradeoffRows as $side => [$sideLabel, $sideClass]) {
                        $headTip .= '<div class="mt-1 fw-semibold">' . $sideLabel . '</div><ul class="mb-0 ps-3">';
                        foreach ($approach[$side] as $point) {
                            $headTip .= '<li>' . e($point) . '</li>';
                        }
                        $headTip .= '</ul>';
                    }
                @endphp
                <th @class(['px-1 text-end', 'in-use' => $approach['in_use']])>
                    <div data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" data-bs-html="true"
                        title="{{ $headTip }}">{{ $approach['name'] }}</div>
                    <div class="fw-normal text-muted approach-sub">{{ $approach['sub'] }}</div>
                </th>
                @endforeach
            </tr>
            <tr class="measures-row align-top" style="white-space:normal;line-height:1.35">
                <td colspan="2" class="px-1 pb-1 text-end text-muted border-end">Measures</td>
                @foreach($approaches as $key => $approach)
                <td @class(['px-1 pb-1 text-end fst-italic', 'in-use' => $approach['in_use']])>
                    {{ $approach['measures'] }}
                </td>
                @endforeach
            </tr>
        </thead>
        <tbody class="border-top border-bottom">
            @foreach($groupRows as $group => $groupLabel)
            <tr>
                @if($loop->first)
                <td rowspan="{{ $allocationRows }}" class="px-1 align-middle" data-bs-toggle="tooltip"
                    title="The share of the portfolio's market value in each tier group under each approach, and
                        the health signal that split gives (Warning: Bronze + Rust over its limit; Caution:
                        Platinum + Gold under its target).">
                    <div class="fw-semibold">Tier allocation</div>
                </td>
                @endif
                <td class="px-1 text-end text-muted border-end">{{ $groupLabel }}</td>
                @foreach($approaches as $key => $approach)
                <td @class(['px-1 text-end', 'in-use' => $approach['in_use']])>
                    {{ MoneyFormat::get_formatted_number_plain($comparison['summary'][$key][$group], 1) }}%
                </td>
                @endforeach
            </tr>
            @endforeach
            <tr>
                <td class="px-1 text-end text-muted border-end">Signal</td>
                @foreach($approaches as $key => $approach)
                @php $split = $comparison['summary'][$key]; @endphp
                <td @class(['px-1 text-end', 'in-use' => $approach['in_use']])>
                    <span class="badge bg-{{ $signalClass[$split['signal']] ?? 'secondary' }}"
                        data-bs-toggle="tooltip"
                        title="{{ $split['signal_message'] }}">{{ ucfirst($split['signal']) }}</span>
                </td>
                @endforeach
            </tr>
        </tbody>
        <tbody class="symbol-rows">
            @foreach($comparison['rows'] as $row)
            <tr>
                <td class="px-1 fw-semibold">
                    @include('myfinance2::watchlistsymbols.tables.partials.health-score-approach-badge', [
                        'tier' => $row['tier'], 'title' => 'Tier in use',
                    ])
                    @include('myfinance2::watchlistsymbols.tables.partials.tier-today-chip', [
                        'chip' => $row['today_chip'], 'compact' => true,
                    ])
                    @include('myfinance2::watchlistsymbols.tables.partials.symbol-label', [
                        'symbol' => $row['symbol'],
                    ])
                    @include('myfinance2::watchlistsymbols.tables.partials.superstar-star', [
                        'superstar' => $row['superstar'] ?? null,
                    ])
                </td>
                <td class="px-1 text-end border-end">
                    {!! MoneyFormat::get_formatted_price_display('&euro;', $row['mvalue_eur']) !!}
                </td>
                @foreach($approaches as $key => $approach)
                @php
                    $cell     = $row['cells'][$key];
                    $cellText = TierApproachComparison::formatValue($cell['value'], $cell['unit'])
                        . ($cell['flag'] ? '*' : '');
                @endphp
                <td @class(['px-1 text-end', 'in-use' => $approach['in_use']])>
                    <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $cell['title'] }}"
                        @class(['text-muted' => $cell['value'] === null])
                        >{{ $cellText }}</span>
                    @include('myfinance2::watchlistsymbols.tables.partials.health-score-approach-badge', [
                        'tier' => $cell['tier'], 'title' => TierCalculationService::tierLabel($cell['tier']),
                    ])
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
        <tfoot style="white-space:normal;line-height:1.35">
            <tr class="measures-row align-top">
                <td colspan="2" class="px-1 pt-1 text-end fw-semibold border-end">Verdict</td>
                @foreach($approaches as $key => $approach)
                @php [$verdictLabel, $verdictClass] = $verdicts[$approach['verdict']]; @endphp
                <td @class(['px-1 pt-1 pb-1 text-end', 'in-use' => $approach['in_use']])>
                    <span class="fw-semibold {{ $verdictClass }}">{{ $verdictLabel }}:</span>
                    {{ $approach['verdict_text'] }}
                </td>
                @endforeach
            </tr>
        </tfoot>
    </table>
    <div class="small text-muted mt-1">
        Hover an approach's name for how it is measured and its pros and cons (on smaller screens, the
        <i class="fa fa-info-circle" aria-hidden="true"></i> next to its number for what it measures and its
        verdict), a figure for how it was reached;
        * marks a stand-in (no market figure, or too short a history) or a period under a year;
        <i class="fas fa-star text-warning" style="font-size:0.85em;" aria-hidden="true"></i> marks a superstar,
        Platinum or Gold under most of the 7 approaches.
    </div>
    </div>
</div>
@endif
