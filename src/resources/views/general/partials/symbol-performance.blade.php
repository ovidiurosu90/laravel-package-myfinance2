@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
{{--
    Reusable symbol performance partial.
    Required variable: $symbolPerf  (array from SymbolPerformanceService::handle())
--}}
@if (!empty($symbolPerf['has_data']) || !empty($symbolPerf['sector']))
<div class="symbol-performance mt-2">

    {{-- ── Primary tags rows ── --}}
    @php $openWin = !empty($symbolPerf['has_data']) ? collect($symbolPerf['windows'])->firstWhere('is_open', true) : null; @endphp

    @if (!empty($symbolPerf['has_data']))
    {{-- Open position row (solid border) --}}
    @if ($openWin)
    <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
        <small class="text-muted me-1">Current:</small>
        <span class="badge bg-transparent border border-primary"
              data-bs-toggle="tooltip"
              title="Current cost basis: amount still invested in the open position">
            <span class="text-primary">cost: {!! MoneyFormat::get_formatted_price_display('&euro;', $openWin['remaining_cost_eur']) !!}</span>
        </span>
        <span class="badge bg-transparent border {{ $openWin['total_gain_eur'] >= 0 ? 'border-success' : 'border-danger' }}"
              data-bs-toggle="tooltip"
              title="Current position gain: unrealized gain + dividends, in EUR">
            <span class="text-body">gain:</span> {!! MoneyFormat::get_formatted_gain('&euro;', $openWin['total_gain_eur']) !!}
        </span>
        @if ($openWin['percentage_gain'] !== null)
        <span class="badge bg-transparent border {{ $openWin['percentage_gain'] >= 0 ? 'border-success' : 'border-danger' }}"
              data-bs-toggle="tooltip"
              title="Current position gain as % of amount invested in this window">
            {!! MoneyFormat::get_formatted_gain('%', $openWin['percentage_gain']) !!}
        </span>
        @endif
        <span class="badge bg-secondary"
              data-bs-toggle="tooltip"
              title="Holding period of the current open position">
            {{ $openWin['period_display'] }} (open)
        </span>
        @if ($symbolPerf['projected_annual_dividend_eur'] !== null)
        <span class="badge bg-transparent border border-info text-info"
              data-bs-toggle="tooltip"
              title="Estimated annual dividend income based on dividends received in the last 12 months">
            dividend: ~{!! MoneyFormat::get_formatted_monetary_display('&euro;', round($symbolPerf['projected_annual_dividend_eur']), 0) !!}/y
        </span>
        @endif
    </div>
    @endif

    {{-- Overall row (dotted border) — shown when there are multiple windows or no open position --}}
    @if (!$openWin || $symbolPerf['window_count'] > 1)
    <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
        <small class="text-muted me-1">Overall:</small>
        <span class="badge bg-transparent border border-primary"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="Total capital ever deployed in this symbol across all windows">
            <span class="text-primary">cost: {!! MoneyFormat::get_formatted_price_display('&euro;', $symbolPerf['capital_deployed_eur']) !!}</span>
        </span>
        <span class="badge bg-transparent border {{ $symbolPerf['total_gain_eur'] >= 0 ? 'border-success' : 'border-danger' }}"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="All-time total gain across all windows: realized + unrealized + dividends, in EUR"
              data-order-gain="{{ round($symbolPerf['total_gain_eur'], 2) }}">
            <span class="text-body">gain:</span> {!! MoneyFormat::get_formatted_gain('&euro;', $symbolPerf['total_gain_eur']) !!}
        </span>
        @if ($symbolPerf['percentage_gain'] !== null)
        <span class="badge bg-transparent border {{ $symbolPerf['percentage_gain'] >= 0 ? 'border-success' : 'border-danger' }}"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="All-time gain as % of total invested across all windows"
              data-order-pct="{{ round($symbolPerf['percentage_gain'], 2) }}">
            {!! MoneyFormat::get_formatted_gain('%', $symbolPerf['percentage_gain']) !!}
        </span>
        @endif
        <span class="badge bg-transparent border border-secondary text-secondary"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="Total holding period across all position windows"
              data-order-period="{{ $symbolPerf['total_days'] ?? 0 }}">
            {{ $symbolPerf['holding_period_display'] }}
        </span>
        @if ($symbolPerf['fees_eur'] > 0)
        <span class="badge bg-transparent border border-secondary text-secondary"
              style="border-style: dotted !important;"
              data-bs-toggle="tooltip"
              title="Total fees paid across all trades and dividends for this symbol{{ ($symbolPerf['fees_pct_of_gain'] !== null && $symbolPerf['fees_pct_of_gain'] > 5) ? ' (' . MoneyFormat::get_formatted_number_plain($symbolPerf['fees_pct_of_gain'], 1) . '% of gain)' : '' }}">
            fees: {!! MoneyFormat::get_formatted_monetary_display('&euro;', round($symbolPerf['fees_eur']), 0) !!}
        </span>
        @endif
    </div>
    @endif

    @endif {{-- has_data --}}

    @if (!empty($symbolPerf['has_data']))
    {{-- ── Per-window detail ── --}}
    @if ($symbolPerf['window_count'] > 0)
    <table class="table table-borderless table-sm symbol-perf-windows mb-1"
           style="font-size: 0.78rem;">
        <tbody>
        @foreach ($symbolPerf['windows'] as $win)
        <tr>
            <td class="text-muted pe-2 text-nowrap">
                <div data-bs-toggle="tooltip"
                     title="Position window {{ $win['index'] }}: a continuous holding period that starts with your first purchase and ends when you fully sell out. A new window opens the next time you buy back in.">W{{ $win['index'] }}</div>
                @if ($win['index'] === $symbolPerf['best_window_index'])
                <div data-bs-toggle="tooltip"
                     title="Best window: highest annualized return among completed positions.">&#9733;</div>
                @endif
            </td>
            <td class="pe-2">
                <div>{{ $win['start_date']->format('M Y') }}</div>
                <div>→ {{ $win['is_open'] ? '(today)' : $win['end_date']->format('M Y') }}</div>
            </td>
            <td class="text-nowrap pe-2 text-muted">
                {{ $win['period_display'] }}
            </td>
            <td class="pe-2">
                <div>{!! MoneyFormat::get_formatted_gain('&euro;', $win['total_gain_eur']) !!}</div>
                @if ($win['percentage_gain'] !== null)
                <div>({!! MoneyFormat::get_formatted_gain('%', $win['percentage_gain']) !!})</div>
                @endif
            </td>
            <td>
                <span class="badge {{ $win['is_open'] ? 'bg-primary' : 'bg-secondary' }}">
                    {{ $win['status'] }}
                </span>
            </td>
            <td class="text-muted pe-1 text-end">
                @if ($win['peak_gain_eur'] !== null)
                <span data-bs-toggle="tooltip"
                      title="Peak paper gain during this window based on historical prices{{ !empty($win['peak_gain_date']) ? ', best exit date: ' . $win['peak_gain_date']->format('d M Y') : '' }}">
                    peak
                </span>
                @endif
            </td>
            <td>
                @if ($win['peak_gain_eur'] !== null)
                <div>{!! MoneyFormat::get_formatted_gain('&euro;', $win['peak_gain_eur']) !!}</div>
                @if ($win['peak_gain_percentage'] !== null)
                <div>({!! MoneyFormat::get_formatted_gain('%', $win['peak_gain_percentage']) !!})</div>
                @endif
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    @endif {{-- has_data --}}

    {{-- ── Extended metrics ── --}}
    {{-- Removed metrics ("Invested:", "By year:") are documented in SymbolPerformanceService::_buildSymbolResult(). --}}
    <div class="d-flex flex-wrap gap-2 symbol-perf-metrics" style="font-size: 0.78rem;">

        @if ($symbolPerf['sector'])
        <span>
            <span class="text-muted">Sector:</span>
            <strong>{{ $symbolPerf['sector'] }}</strong>
        </span>
        @endif

        @if (!empty($symbolPerf['has_data']))
        @if ($symbolPerf['dividend_split_pct'] !== null && $symbolPerf['dividend_split_pct'] > 5)
        <span data-bs-toggle="tooltip"
              title="How much of the total gain came from dividends vs. capital gains">
            <span class="text-muted">Gain split:</span>
            <strong>
                {{ round($symbolPerf['trade_split_pct'] ?? 0, 0) }}% trades
                / {{ round($symbolPerf['dividend_split_pct'], 0) }}% div
            </strong>
        </span>
        @endif

        @if ($symbolPerf['win_rate'] !== null)
        <span data-bs-toggle="tooltip" title="Of all completed windows, how many were profitable">
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
              title="The calendar quarter with the most sell trades across all completed windows for this symbol. Indicates a seasonal exit pattern.">
            {{ $symbolPerf['time_pattern_summary'] }}
        </span>
        @endif
        @endif {{-- has_data --}}

    </div>

    @if (!empty($symbolPerf['has_data']))
    {{-- ── Warnings / flags ── --}}
    @foreach ($symbolPerf['re_entry_flags'] as $flag)
    <div class="badge bg-warning text-dark mt-1">&#9888; {{ $flag }}</div>
    @endforeach
    @endif {{-- has_data --}}

</div>
@endif
