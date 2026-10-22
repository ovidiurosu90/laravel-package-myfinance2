{{-- The Performance card of the peak-proximity alert: the open and overall gain, then every window with its peak.
     Included by peak-proximity-alert, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<div class="section" style="border-left-color:#0d6efd;">
    <h2>Performance</h2>
    @if (!empty($perf['has_data']))
    <table>
        <tr>
            <th></th>
            <th>Cost</th>
            <th>Dividends</th>
            <th>Total gain</th>
            <th>Gain/y</th>
            <th>Money/y (XIRR)</th>
            <th>Holding period</th>
        </tr>
        @if ($openWindow)
        <tr>
            <td class="text-muted">Current</td>
            <td class="text-nowrap">
                {!! MoneyFormat::get_formatted_price_display($eur, $openWindow['remaining_cost_eur']) !!}
            </td>
            <td>{!! MoneyFormat::get_formatted_gain($eur, $openWindow['dividends_eur'] ?? 0) !!}</td>
            <td>
                {!! MoneyFormat::get_formatted_gain($eur, $openWindow['total_gain_eur']) !!}
                @if (($openWindow['percentage_gain'] ?? null) !== null)
                ({!! MoneyFormat::get_formatted_gain('%', $openWindow['percentage_gain']) !!})
                @endif
            </td>
            <td>
                @if (($openWindow['annualized_gain_eur'] ?? null) !== null)
                {!! MoneyFormat::get_formatted_gain($eur, $openWindow['annualized_gain_eur']) !!}
                @if (($openWindow['annualized_percentage_gain'] ?? null) !== null)
                ({!! MoneyFormat::get_formatted_gain('%', $openWindow['annualized_percentage_gain']) !!})
                @endif
                @else
                <span class="text-muted">n/a</span>
                @endif
            </td>
            <td>
                @if (($perf['xirr_pct'] ?? null) !== null)
                {!! MoneyFormat::get_formatted_gain('%', $perf['xirr_pct']) !!}
                @else
                <span class="text-muted">n/a</span>
                @endif
            </td>
            <td class="text-nowrap">{{ $openWindow['period_display'] ?? '' }} (open)</td>
        </tr>
        @endif
        @if (!$openWindow || ($perf['window_count'] ?? 1) > 1)
        <tr>
            <td class="text-muted">Overall</td>
            <td class="text-nowrap">
                {!! MoneyFormat::get_formatted_price_display($eur, $perf['capital_deployed_eur'] ?? 0) !!}
            </td>
            <td>{!! MoneyFormat::get_formatted_gain($eur, $perf['total_dividends_eur'] ?? 0) !!}</td>
            <td>
                {!! MoneyFormat::get_formatted_gain($eur, $perf['total_gain_eur'] ?? 0) !!}
                @if (($perf['percentage_gain'] ?? null) !== null)
                ({!! MoneyFormat::get_formatted_gain('%', $perf['percentage_gain']) !!})
                @endif
            </td>
            <td>
                @if (($perf['annualized_gain_eur'] ?? null) !== null)
                {!! MoneyFormat::get_formatted_gain($eur, $perf['annualized_gain_eur']) !!}
                @if (($perf['annualized_percentage_gain'] ?? null) !== null)
                ({!! MoneyFormat::get_formatted_gain('%', $perf['annualized_percentage_gain']) !!})
                @endif
                @else
                <span class="text-muted">n/a</span>
                @endif
            </td>
            <td>
                @if (($perf['xirr_pct'] ?? null) !== null)
                {!! MoneyFormat::get_formatted_gain('%', $perf['xirr_pct']) !!}
                @else
                <span class="text-muted">n/a</span>
                @endif
            </td>
            <td class="text-nowrap">{{ $perf['holding_period_display'] ?? '' }}</td>
        </tr>
        @endif
    </table>
    {{-- One name per value: this card's total gain is not the Summary's unrealized gain --}}
    <p class="text-muted" style="font-size:12px; margin:6px 0 0;">
        Total gain: realized + unrealized + dividends. The unrealized gain in the Summary covers only the shares
        still held.
    </p>
    @if ($openRealizedEur !== null)
    {{-- Partial sells: the Current gain and % cover the whole window, the Cost only what is still held. --}}
    <p class="text-muted" style="font-size:12px; margin:6px 0 0;">
        Current: the total gain includes
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain($eur, $openRealizedEur) !!}</span>
        realized on partial sells in this window, and its % is of the
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_price_display(
            $eur, $openWindow['invested_eur'] ?? 0
        ) !!}</span>
        invested in the window; the Cost is what is still held.
    </p>
    @endif

    {{-- Per-window detail incl. peak --}}
    @if (!empty($perf['windows']))
    <table>
        <tr>
            <th></th>
            <th>Window</th>
            <th>Period</th>
            <th>Total gain</th>
            <th>Status</th>
            <th>Peak</th>
        </tr>
        @foreach ($perf['windows'] as $win)
        <tr>
            <td class="text-muted">W{{ $win['index'] ?? '' }}</td>
            <td class="text-nowrap">
                {{ optional($win['start_date'])->format('M Y') }}
                &rarr; {{ !empty($win['is_open']) ? '(today)' : optional($win['end_date'])->format('M Y') }}
            </td>
            <td class="text-muted text-nowrap">{{ $win['period_display'] ?? '' }}</td>
            <td>
                {!! MoneyFormat::get_formatted_gain($eur, $win['total_gain_eur']) !!}
                @if (($win['percentage_gain'] ?? null) !== null)
                ({!! MoneyFormat::get_formatted_gain('%', $win['percentage_gain']) !!})
                @endif
            </td>
            <td><span class="badge bg-secondary">{{ $win['status'] ?? '' }}</span></td>
            <td>
                @if (($win['peak_gain_eur'] ?? null) !== null)
                {!! MoneyFormat::get_formatted_gain($eur, $win['peak_gain_eur']) !!}
                @if (($win['peak_gain_percentage'] ?? null) !== null)
                ({!! MoneyFormat::get_formatted_gain('%', $win['peak_gain_percentage']) !!})
                @endif
                @else
                <span class="text-muted">-</span>
                @endif
            </td>
        </tr>
        @endforeach
    </table>
    @endif
    @else
    <div class="text-muted">No performance data available.</div>
    @endif

    {{-- Sector + technical indicators --}}
    <div style="margin-top:12px;">
        @if (!empty($perf['sector']))
        <span class="text-muted">Sector:</span> <strong>{{ $perf['sector'] }}</strong>&nbsp;&nbsp;
        @endif
        @if (($tech['rsi'] ?? null) !== null)
        <span class="text-muted">RSI:</span> {{ MoneyFormat::get_formatted_number_plain($tech['rsi'], 1) }}&nbsp;&nbsp;
        @endif
        @if (($tech['ma50'] ?? null) !== null)
        <span class="text-muted">MA50:</span>
        {{ MoneyFormat::get_formatted_price_plain($tech['ma50'], true) }}&nbsp;&nbsp;
        @endif
        @if (($tech['ma200'] ?? null) !== null)
        <span class="text-muted">MA200:</span>
        {{ MoneyFormat::get_formatted_price_plain($tech['ma200'], true) }}&nbsp;&nbsp;
        @endif
        @if (($tech['analyst_target_price'] ?? null) !== null)
        <span class="text-muted">Analyst target:</span>
        {{ MoneyFormat::get_formatted_price_plain($tech['analyst_target_price'], true) }}
        @endif
    </div>
</div>
