@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
@php use ovidiuro\myfinance2\App\Services\SymbolPerformanceTooltips; @endphp
@once
<style>
    .symbol-performance { font-size: 0.75rem; }
    .symbol-performance .badge { font-size: inherit; }
</style>
@endonce
{{--
    Reusable symbol performance partial.
    Required variable: $symbolPerf  (array from SymbolPerformanceService::handle())
    Optional variable: $technicalIndicators (array from TechnicalIndicatorsService)
--}}
@php $technicalIndicators = $technicalIndicators ?? null; @endphp
@if (!empty($symbolPerf['has_data']) || !empty($symbolPerf['sector']) || !empty($technicalIndicators))
<div class="symbol-performance mt-2">

    {{-- ── Primary tags rows ── --}}
    @php
        $openWin = !empty($symbolPerf['has_data']) ? collect($symbolPerf['windows'])->firstWhere('is_open', true) : null;
        $tt = SymbolPerformanceTooltips::build(
            $symbolPerf, $tradeCurrencyCode ?? 'EUR', $tradeCurrencyDisplayCode ?? ''
        );
    @endphp

    @if (!empty($symbolPerf['has_data']))
    {{-- Open position row (solid border) --}}
    @if ($openWin)
    <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
        <span class="text-muted me-1">Current:</span>
        <span class="badge bg-transparent border border-primary"
              data-bs-toggle="tooltip"
              title="{{ $tt['open_cost'] }}">
            <span class="text-primary">cost: {!! MoneyFormat::get_formatted_price_display('&euro;', $openWin['remaining_cost_eur']) !!}</span>
        </span>
        @if ($tt['open_dividends'])
        <span class="badge bg-transparent border border-info"
              data-bs-toggle="tooltip"
              title="{{ $tt['open_dividends'] }}">
            <span class="text-body">div:</span>
            <span class="text-info">{!! MoneyFormat::get_formatted_gain('&euro;', $openWin['dividends_eur']) !!}</span>
        </span>
        @endif
        <span class="badge bg-transparent border {{ $openWin['total_gain_eur'] >= 0 ? 'border-success' : 'border-danger' }}"
              data-bs-toggle="tooltip"
              @if ($tt['open_gain_big']) data-bs-custom-class="big-tooltips" @endif
              title="{{ $tt['open_gain'] }}">
            <span class="text-body">gain:</span>
            {!! MoneyFormat::get_formatted_gain('&euro;', $openWin['total_gain_eur']) !!}
            @if ($openWin['percentage_gain'] !== null)
            <span class="text-body">(</span>{!! MoneyFormat::get_formatted_gain('%', $openWin['percentage_gain']) !!}<span class="text-body">)</span>
            @endif
        </span>
        @if ($tt['open_annualized_short'])
        <span class="badge bg-transparent border border-secondary"
              data-bs-toggle="tooltip"
              title="{{ $tt['open_annualized_short'] }}">
            <span class="text-body">gain/y:</span>
            <span class="text-muted">n/a</span><sup>*</sup>
        </span>
        @elseif ($tt['open_annualized'])
        <span class="badge bg-transparent border {{ $openWin['annualized_gain_eur'] >= 0 ? 'border-success' : 'border-danger' }}"
              data-bs-toggle="tooltip"
              title="{{ $tt['open_annualized'] }}">
            <span class="text-body">gain/y:</span>
            {!! MoneyFormat::get_formatted_gain('&euro;', $openWin['annualized_gain_eur']) !!}
            @if ($openWin['annualized_percentage_gain'] !== null)
            <span class="text-body">(</span>{!! MoneyFormat::get_formatted_gain('%', $openWin['annualized_percentage_gain']) !!}<span class="text-body">)</span>
            @endif
        </span>
        @endif
        @if (($symbolPerf['window_count'] ?? 1) === 1)
        {{-- Money-weighted return (XIRR), shown alongside the CAGR gain/y. For a single window
             the symbol-level XIRR is exactly this position's, so it is shown on the Current row.
             Never hidden: under a year it is flagged provisional (*); under 30 days it is n/a. --}}
        @if (($symbolPerf['xirr_pct'] ?? null) !== null)
        <span class="badge bg-transparent border {{ $symbolPerf['xirr_pct'] >= 0 ? 'border-success' : 'border-danger' }}"
              data-bs-toggle="tooltip"
              title="{{ $tt['open_money'] }}">
            <span class="text-body">money/y:</span>
            {!! MoneyFormat::get_formatted_gain('%', $symbolPerf['xirr_pct']) !!}@if($tt['xirr_short'])<sup>*</sup>@endif
        </span>
        @else
        <span class="badge bg-transparent border border-secondary"
              data-bs-toggle="tooltip"
              title="{{ $tt['open_money'] }}">
            <span class="text-body">money/y:</span>
            <span class="text-muted">n/a</span><sup>*</sup>
        </span>
        @endif
        @endif
        <span class="badge bg-secondary"
              data-bs-toggle="tooltip"
              title="{{ $tt['open_holding_period'] }}">
            {{ $openWin['period_display'] }} (open)
        </span>
        @if ($symbolPerf['projected_annual_dividend_eur'] !== null)
        <span class="badge bg-transparent border border-info text-info"
              data-bs-toggle="tooltip"
              title="{{ $tt['open_projected_dividend'] }}">
            div exp: ~{!! MoneyFormat::get_formatted_monetary_display('&euro;', round($symbolPerf['projected_annual_dividend_eur']), 0) !!}/y
        </span>
        @endif
    </div>
    @endif

    {{-- Overall row (dotted border) — shown when there are multiple windows or no open position --}}
    @if (!$openWin || $symbolPerf['window_count'] > 1)
    <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
        <span class="text-muted me-1">Overall:</span>
        <span class="badge bg-transparent border border-primary"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_cost'] }}">
            <span class="text-primary">cost: {!! MoneyFormat::get_formatted_price_display('&euro;', $symbolPerf['capital_deployed_eur']) !!}</span>
        </span>
        @if ($tt['overall_dividends'])
        <span class="badge bg-transparent border border-info"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_dividends'] }}">
            <span class="text-body">div:</span>
            <span class="text-info">{!! MoneyFormat::get_formatted_gain('&euro;', $symbolPerf['total_dividends_eur']) !!}</span>
        </span>
        @endif
        <span class="badge bg-transparent border {{ $symbolPerf['total_gain_eur'] >= 0 ? 'border-success' : 'border-danger' }}"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_gain'] }}"
              data-order-gain="{{ round($symbolPerf['total_gain_eur'], 2) }}">
            <span class="text-body">gain:</span>
            {!! MoneyFormat::get_formatted_gain('&euro;', $symbolPerf['total_gain_eur']) !!}
            @if ($symbolPerf['percentage_gain'] !== null)
            <span class="text-body">(</span>{!! MoneyFormat::get_formatted_gain('%', $symbolPerf['percentage_gain']) !!}<span class="text-body">)</span>
            @endif
        </span>
        @if ($tt['overall_annualized_short'])
        <span class="badge bg-transparent border border-secondary"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_annualized_short'] }}">
            <span class="text-body">gain/y:</span>
            <span class="text-muted">n/a</span><sup>*</sup>
        </span>
        @elseif ($tt['overall_annualized'])
        <span class="badge bg-transparent border {{ $symbolPerf['annualized_gain_eur'] >= 0 ? 'border-success' : 'border-danger' }}"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_annualized'] }}">
            <span class="text-body">gain/y:</span>
            {!! MoneyFormat::get_formatted_gain('&euro;', $symbolPerf['annualized_gain_eur']) !!}
            @if ($symbolPerf['annualized_percentage_gain'] !== null)
            <span class="text-body">(</span>{!! MoneyFormat::get_formatted_gain('%', $symbolPerf['annualized_percentage_gain']) !!}<span class="text-body">)</span>
            @endif
        </span>
        @endif
        {{-- Money-weighted return (XIRR) across all windows, next to the time-weighted CAGR gain/y.
             Never hidden: under a year it is flagged provisional (*); under 30 days it is n/a. --}}
        @if (($symbolPerf['xirr_pct'] ?? null) !== null)
        <span class="badge bg-transparent border {{ $symbolPerf['xirr_pct'] >= 0 ? 'border-success' : 'border-danger' }}"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_money'] }}">
            <span class="text-body">money/y:</span>
            {!! MoneyFormat::get_formatted_gain('%', $symbolPerf['xirr_pct']) !!}@if($tt['xirr_short'])<sup>*</sup>@endif
        </span>
        @else
        <span class="badge bg-transparent border border-secondary"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_money'] }}">
            <span class="text-body">money/y:</span>
            <span class="text-muted">n/a</span><sup>*</sup>
        </span>
        @endif
        <span class="badge bg-transparent border border-secondary text-secondary"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_holding_period'] }}"
              data-order-period="{{ $symbolPerf['total_days'] ?? 0 }}">
            {{ $symbolPerf['holding_period_display'] }}
        </span>
        @if ($tt['overall_fees'])
        <span class="badge bg-transparent border border-secondary text-secondary"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="{{ $tt['overall_fees'] }}">
            fees: {!! MoneyFormat::get_formatted_monetary_display('&euro;', round($symbolPerf['fees_eur']), 0) !!}
        </span>
        @endif
    </div>
    @endif

    @endif {{-- has_data --}}

    @if (!empty($symbolPerf['has_data']))
    {{-- ── Per-window detail ── --}}
    @if ($symbolPerf['window_count'] > 0)
    <table class="table table-borderless table-sm symbol-perf-windows mb-1">
        <tbody>
        @foreach ($symbolPerf['windows'] as $win)
        @php $winTt = $tt['windows'][$win['index']]; @endphp
        <tr>
            <td class="text-muted pe-2 text-nowrap">
                <span data-bs-toggle="tooltip"
                     title="{{ $winTt['label'] }}">W{{ $win['index'] }}</span>
                @if ($winTt['star'])
                <span data-bs-toggle="tooltip"
                     title="{{ $winTt['star'] }}">&#9733;</span>
                @endif
            </td>
            <td class="pe-2">
                <span class="text-nowrap">{{ $win['start_date']->format('M Y') }}</span>
                <span class="text-nowrap">→ {{ $win['is_open'] ? '(today)' : $win['end_date']->format('M Y') }}</span>
            </td>
            <td class="text-nowrap pe-2 text-muted">
                {{ $win['period_display'] }}
            </td>
            <td class="pe-2">
                <span @if ($winTt['gain']) data-bs-toggle="tooltip" title="{{ $winTt['gain'] }}" @endif>
                <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('&euro;', $win['total_gain_eur']) !!}</span>
                @if ($win['percentage_gain'] !== null)
                <span class="text-nowrap">({!! MoneyFormat::get_formatted_gain('%', $win['percentage_gain']) !!})</span>
                @endif
                </span>
            </td>
            <td>
                <span class="badge opacity-50 {{ $win['is_open'] ? 'bg-primary' : 'bg-secondary' }}">
                    {{ $win['status'] }}
                </span>
            </td>
            {{-- Peak columns: inline on lg+, hidden below lg (second <tr> takes over) --}}
            <td class="text-muted pe-1 text-end d-none d-lg-table-cell">
                @if ($winTt['peak'])
                <span data-bs-toggle="tooltip"
                      title="{!! $winTt['peak'] !!}">
                    peak
                </span>
                @endif
            </td>
            <td class="d-none d-lg-table-cell">
                @if ($winTt['peak'])
                <span data-bs-toggle="tooltip"
                      title="{!! $winTt['peak'] !!}">
                <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('&euro;', $win['peak_gain_eur'], 0) !!}</span>
                @if ($win['peak_gain_percentage'] !== null)
                <span class="text-nowrap">({!! MoneyFormat::get_formatted_gain('%', $win['peak_gain_percentage']) !!})</span>
                @endif
                </span>
                @endif
            </td>
        </tr>
        @if ($winTt['peak'])
        <tr class="d-lg-none">
            <td colspan="5" class="pt-0 text-muted">
                <span data-bs-toggle="tooltip"
                      title="{!! $winTt['peak'] !!}">
                    peak
                </span>
                {!! MoneyFormat::get_formatted_gain('&euro;', $win['peak_gain_eur'], 0) !!}
                @if ($win['peak_gain_percentage'] !== null)
                ({!! MoneyFormat::get_formatted_gain('%', $win['peak_gain_percentage']) !!})
                @endif
            </td>
        </tr>
        @endif
        @endforeach
        </tbody>
    </table>
    @endif

    @endif {{-- has_data --}}

    {{-- ── Extended metrics ── --}}
    {{-- Removed metrics ("Invested:", "By year:") are documented in SymbolPerformanceService::_buildSymbolResult(). --}}
    <div class="d-flex flex-wrap gap-2 symbol-perf-metrics mb-1">

        @if ($symbolPerf['sector'])
        <span>
            <span class="text-muted">Sector:</span>
            <strong>{{ $symbolPerf['sector'] }}</strong>
        </span>
        @endif

        @if (!empty($symbolPerf['has_data']))
        @if ($symbolPerf['dividend_split_pct'] !== null && $symbolPerf['dividend_split_pct'] > 5)
        <span data-bs-toggle="tooltip"
              title="{{ $tt['gain_split'] }}">
            <span class="text-muted">Gain split:</span>
            <strong>
                {{ round($symbolPerf['trade_split_pct'] ?? 0, 0) }}% trades
                / {{ round($symbolPerf['dividend_split_pct'], 0) }}% div
            </strong>
        </span>
        @endif

        @if ($symbolPerf['win_rate'] !== null)
        <span data-bs-toggle="tooltip" title="{{ $tt['win_rate'] }}">
            <span class="text-muted">Win rate:</span>
            <strong>
                {{ $symbolPerf['win_rate']['wins'] }}/{{ $symbolPerf['win_rate']['completed'] }}
                ({{ round($symbolPerf['win_rate']['wins'] / max($symbolPerf['win_rate']['completed'], 1) * 100, 0) }}%)
            </strong>
        </span>
        @endif

        @if ($symbolPerf['time_pattern_summary'])
        <span class="text-muted"
              data-bs-toggle="tooltip"
              title="{{ $tt['time_pattern'] }}">
            {{ $symbolPerf['time_pattern_summary'] }}
        </span>
        @endif
        @endif {{-- has_data --}}

    </div>

    @if (!empty($technicalIndicators))
    <div class="d-flex flex-wrap gap-2 symbol-perf-metrics mb-1">
        @include('myfinance2::watchlistsymbols.tables.partials.technical-indicators',
            ['indicators' => $technicalIndicators])
    </div>
    @endif

    @if (!empty($symbolPerf['has_data']))
    {{-- ── Warnings / flags ── --}}
    @foreach ($symbolPerf['re_entry_flags'] as $flag)
    <div class="badge bg-warning text-dark mt-1">&#9888; {{ $flag }}</div>
    @endforeach
    @endif {{-- has_data --}}

</div>
@endif
