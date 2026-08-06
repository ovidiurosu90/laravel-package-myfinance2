@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <style>
        body { background-color: #F9F9F9; color: #222; font: 14px/1.6 Helvetica, Arial, sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 1024px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
        .header { background: #1a1a2e; color: #fff; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .header h1 a { color: #fff; text-decoration: underline; }
        .header .sub { margin-top: 6px; font-size: 13px; color: #c9c9d6; }
        .body { padding: 24px; }
        .section { margin-bottom: 20px; padding: 16px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #6c757d; }
        .section h2 { margin: 0 0 12px; font-size: 15px; font-weight: 600; }
        .section p { margin: 0 0 8px; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 4px; }
        .text-muted { color: #6c757d; }
        .text-nowrap, .gain-positive, .gain-negative, .text-success, .text-danger { white-space: nowrap; }
        .gain-positive, .text-success { color: #28a745; }
        .gain-negative, .text-danger { color: #dc3545; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; color: #fff; }
        .bg-success { background: #28a745; color: #fff; }
        .bg-danger  { background: #dc3545; color: #fff; }
        .bg-warning { background: #ffc107; color: #000; }
        .bg-info    { background: #0dcaf0; color: #000; }
        .bg-secondary { background: #6c757d; color: #fff; }
        .bg-orange  { background: #e67e22; color: #fff; }
        .bg-light   { background: #f1f3f5; color: #000; border: 1px solid #dee2e6; }
        .text-dark  { color: #000 !important; }
        .text-white { color: #fff !important; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 8px; border: 1px solid #dee2e6; font-size: 13px; text-align: left; vertical-align: top; }
        th { background: #e9ecef; font-weight: 600; }
        tr.near-peak td { background: #eafaf0; }
        tr.near-peak td:first-child { border-left: 3px solid #28a745; }
        .acct-block { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0; }
        .acct-title { font-weight: 600; margin-bottom: 6px; }
        .footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        a.sym-link, a.action-link { color: #0d6efd; text-decoration: none; }
        .action-link { display: inline-block; margin-top: 8px; padding: 8px 16px; border-radius: 4px; background: #0d6efd; color: #fff !important; font-size: 13px; font-weight: 600; }
    </style>
</head>
@php
    // Presentation helpers only; every derived value is prepared in the PeakProximityAlert mailable.
    $decode = fn ($s) => html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $eur    = '€';
    $perf   = $quoteData['performance'] ?? [];
    $tech   = $quoteData['technical_indicators'] ?? [];
    $cur    = $decode($quoteData['tradeCurrencyModel']->display_code ?? $eur);
    $price  = isset($quoteData['price']) ? (float) $quoteData['price'] : null;

    $signedPct = function (?float $v): string {
        if ($v === null) {
            return 'n/a';
        }
        return ($v > 0 ? '+' : '') . MoneyFormat::get_formatted_pct($v) . '%';
    };
@endphp
<body>
<div class="container">
    <div class="header">
        <h1>&#128276; <a href="{{ $yahooUrl }}" target="_blank">{{ $symbol }}</a>
            @if (!empty($tierLabel))
                <span class="badge {{ $tierClass }}">{{ $tierLabel }}</span>
            @endif
            near peak</h1>
        <div class="sub">
            @if (!empty($isReminder))Reminder &middot; @endif
            @if (!empty($tierLabel)){{ $tierLabel }}@if (!empty($headAction)), {{ $headAction }}@endif &middot; @endif
            {{ implode(' · ', $triggerSummary) }}
        </div>
    </div>
    <div class="body">

        {{-- Summary --}}
        <div class="section" style="border-left-color:#28a745;">
            <h2>Summary</h2>
            <p>
                @if (!empty($tierLabel))
                    <strong>{{ $tierLabel }}@if (!empty($headAction)), {{ $headAction }}@endif.</strong>
                @endif
                Current price
                <strong class="text-nowrap">{!! $price !== null ? MoneyFormat::get_formatted_price_display($cur, $price, true) : 'n/a' !!}</strong>,
                near its peak in {{ $nearCount }} {{ $nearCount === 1 ? 'window' : 'windows' }}.
                Every window is shown below with the target price that would put it near peak
                (its threshold below the peak) and how far the price still has to run.
            </p>
            <table>
                <tr>
                    <th>Window</th>
                    <th class="text-nowrap">From peak</th>
                    <th>Peak price</th>
                    <th>Peak date</th>
                    <th>Trigger target</th>
                    <th>To go</th>
                </tr>
                @foreach ($summaryWindows as $p)
                <tr @if ($p['near']) class="near-peak" @endif>
                    <td class="text-nowrap">{{ $p['label'] }}@if ($p['near']) <span class="badge bg-success">near peak</span>@endif</td>
                    <td class="text-nowrap">{{ $signedPct($p['prox']) }}</td>
                    <td class="text-nowrap">
                        @if ($p['peak'] !== null){!! MoneyFormat::get_formatted_price_display($cur, $p['peak'], true) !!}@else <span class="text-muted">n/a</span>@endif
                    </td>
                    <td class="text-nowrap">{{ $p['date'] ?? 'n/a' }}</td>
                    <td class="text-nowrap">
                        @if ($p['target'] !== null)
                        {!! MoneyFormat::get_formatted_price_display($cur, $p['target'], true) !!}
                        @if ($p['thr'] !== null)<span class="text-muted">({{ $signedPct(-$p['thr']) }})</span>@endif
                        @else
                        <span class="text-muted">n/a</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if ($p['near'])
                        <span class="text-success">in zone</span>
                        @elseif ($p['to_go'] !== null)
                        {{ $signedPct($p['to_go']) }}
                        @else
                        <span class="text-muted">n/a</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </table>
            @if ($todayGainEur !== null)
            <p style="margin-top:8px;">
                Today's move:
                <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain($eur, $todayGainEur) !!}</span>@if ($todayGainPct !== null) (<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $todayGainPct) !!}</span>)@endif,
                which nudged the position toward its peak today.
            </p>
            @endif
            @if ($sellGainEur !== null)
            <p style="margin-top:8px;">
                Your position's current unrealized gain is
                <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain($eur, $sellGainEur) !!}</span>@if ($sellGainPct !== null) (<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $sellGainPct) !!}</span>)@endif,
                which is what you would lock in by selling now.
            </p>
            @endif
        </div>

        {{-- Card 1: Performance --}}
        <div class="section" style="border-left-color:#0d6efd;">
            <h2>Performance</h2>
            @if (!empty($perf['has_data']))
            <table>
                <tr>
                    <th></th>
                    <th>Cost</th>
                    <th>Dividends</th>
                    <th>Gain</th>
                    <th>Gain/y</th>
                    <th>Money/y (XIRR)</th>
                    <th>Holding period</th>
                </tr>
                @if ($openWindow)
                <tr>
                    <td class="text-muted">Current</td>
                    <td class="text-nowrap">{!! MoneyFormat::get_formatted_price_display($eur, $openWindow['remaining_cost_eur']) !!}</td>
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
                    <td class="text-nowrap">{!! MoneyFormat::get_formatted_price_display($eur, $perf['capital_deployed_eur'] ?? 0) !!}</td>
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

            {{-- Per-window detail incl. peak --}}
            @if (!empty($perf['windows']))
            <table>
                <tr>
                    <th></th>
                    <th>Window</th>
                    <th>Period</th>
                    <th>Gain</th>
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
                <span class="text-muted">MA50:</span> {{ MoneyFormat::get_formatted_price_plain($tech['ma50'], true) }}&nbsp;&nbsp;
                @endif
                @if (($tech['ma200'] ?? null) !== null)
                <span class="text-muted">MA200:</span> {{ MoneyFormat::get_formatted_price_plain($tech['ma200'], true) }}&nbsp;&nbsp;
                @endif
                @if (($tech['analyst_target_price'] ?? null) !== null)
                <span class="text-muted">Analyst target:</span> {{ MoneyFormat::get_formatted_price_plain($tech['analyst_target_price'], true) }}
                @endif
            </div>
        </div>

        {{-- Card 2: Quadrant --}}
        @if ($quadrant)
        <div class="section" style="border-left-color:#6f42c1;">
            <h2>Quadrant</h2>
            <div style="margin-bottom:8px;">
                <span class="text-muted">Tier:</span>
                <span class="badge {{ $quadrant['tier_class'] }}">{{ $quadrant['tier_label'] }}</span>
                @if ($quadrant['basis_val'] !== null)
                <span class="text-muted">based on</span>
                @if ($quadrant['gain_eur'] !== null)<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain($eur, $quadrant['gain_eur']) !!}</span>@endif
                (<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $quadrant['basis_val']) !!}</span>)
                @endif
                @if ($quadrant['action'] !== null)
                &middot; <span class="badge {{ $quadrant['action_class'] }}">{{ $quadrant['action'] }}</span>
                @endif
            </div>
            @if ($quadrant['xirr_pct'] !== null || $quadrant['alpha_pct'] !== null)
            <div style="margin-bottom:8px;" class="text-muted">
                @if ($quadrant['xirr_pct'] !== null)
                Money-weighted XIRR <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $quadrant['xirr_pct']) !!}/y</span>
                @endif
                @if ($quadrant['alpha_pct'] !== null)
                &middot; vs <a class="sym-link" href="{{ $vusaUrl }}" target="_blank">VUSA.AS</a>
                <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $quadrant['alpha_pct']) !!}</span>
                @endif
            </div>
            @endif

            @if (!empty($quadrant['rows']))
            <table>
                <tr>
                    <th></th>
                    <th>Tier</th>
                    <th>Quadrant</th>
                    <th>Gain</th>
                    <th>Risk</th>
                    <th>Action</th>
                    <th class="text-nowrap">From peak</th>
                    <th>P&amp;L at peak</th>
                </tr>
                @foreach ($quadrant['rows'] as $row)
                <tr @if ($row['near']) class="near-peak" @endif>
                    <td class="text-muted text-nowrap">{{ $row['label'] }}@if ($row['near']) <span class="badge bg-success">near peak</span>@endif</td>
                    <td>
                        @if ($row['tier_label'] !== null)
                        <span class="badge {{ $row['tier_class'] }}">{{ $row['tier_label'] }}</span>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if ($row['quadrant_label'] !== null)
                        <span class="badge {{ $row['quadrant_class'] }}">{{ $row['quadrant_label'] }}</span>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if ($row['gain'] !== null){!! MoneyFormat::get_formatted_gain('%', $row['gain']) !!}@else <span class="text-muted">-</span>@endif
                    </td>
                    <td class="text-nowrap">
                        @if ($row['risk'] !== null){{ MoneyFormat::get_formatted_number_plain($row['risk'], 2) }}x @else <span class="text-muted">-</span>@endif
                    </td>
                    <td>
                        @if ($row['action'] !== null)
                        <span class="badge {{ $row['action_class'] }}">{{ $row['action'] }}</span>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @if ($row['prox'] !== null)
                        {!! MoneyFormat::get_formatted_gain('%', $row['prox']) !!}
                        <span class="text-muted">(peak {{ $row['peak_date'] ?? 'n/a' }})</span>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if ($row['pnl_eur'] !== null)
                        {!! MoneyFormat::get_formatted_gain($eur, $row['pnl_eur']) !!}
                        @if ($row['pnl_pct'] !== null)
                        ({!! MoneyFormat::get_formatted_gain('%', $row['pnl_pct']) !!})
                        @endif
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </table>
            @endif
        </div>
        @endif

        {{-- Card 3: Open positions --}}
        @if (!empty($openPositions))
        <div class="section" style="border-left-color:#28a745;">
            <h2>Open positions</h2>
            @foreach ($openPositions as $openPosition)
            <div class="acct-block">
                <div class="acct-title">
                    Account: {{ $openPosition['accountModel']->name }}
                    ({!! $decode($openPosition['accountModel']->currency->display_code) !!})
                </div>
                <table>
                    <tr>
                        <th>Quantity</th>
                        <th>MValue</th>
                        <th>Cost Basis</th>
                        <th>Avg Cost</th>
                        <th>Gain</th>
                    </tr>
                    <tr>
                        <td class="text-nowrap">{{ MoneyFormat::get_formatted_quantity_plain($openPosition['quantity']) }}</td>
                        <td>{!! $decode($openPosition['market_value_in_account_currency_formatted']) !!}</td>
                        <td>
                            {!! $decode($openPosition['cost2_in_account_currency_formatted']
                                ?: $openPosition['cost_in_account_currency_formatted']) !!}
                        </td>
                        <td>
                            {!! $decode($openPosition['average_unit_cost2_in_trade_currency_formatted']
                                ?: $openPosition['average_unit_cost_in_trade_currency_formatted']) !!}
                        </td>
                        <td>
                            {!! $decode($openPosition['overall_change2_in_account_currency_formatted']
                                ?: $openPosition['overall_change_in_account_currency_formatted']) !!}
                        </td>
                    </tr>
                </table>

                <table>
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Quantity</th>
                        <th>Unit price</th>
                    </tr>
                    @foreach ($openPosition['timeline'] as $event)
                    @if ($event['type'] === 'split')
                    @php $splitItem = $event['data']; @endphp
                    <tr class="text-muted">
                        <td class="text-nowrap">{{ $splitItem->split_date->format('Y-m-d') }}</td>
                        <td colspan="3">&#9988; {{ $splitItem->getRatioLabel() }} split</td>
                    </tr>
                    @else
                    @php $trade = $event['data']; @endphp
                    <tr>
                        <td class="text-nowrap">{{ $trade->timestamp->format('Y-m-d') }}</td>
                        <td>{{ strtolower($trade->action) }}</td>
                        <td class="text-nowrap">{{ MoneyFormat::get_formatted_quantity_plain($trade->quantity) }}x</td>
                        <td class="text-nowrap">
                            {!! $decode(MoneyFormat::get_formatted_price_display(
                                $trade->tradeCurrencyModel->display_code,
                                (float) $trade->unit_price,
                                true
                            )) !!}
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </table>
            </div>
            @endforeach
        </div>
        @endif

        <a href="{{ $watchlistUrl }}" class="action-link" target="_blank">&rarr; Open watchlist symbols</a>

    </div>
    <div class="footer">
        <strong>MyFinance2</strong> &middot; Peak-proximity exit hint for {{ $symbol }}<br>
        Sent because an open position is near a recent peak. Max once per day per symbol.
    </div>
</div>
</body>
</html>
