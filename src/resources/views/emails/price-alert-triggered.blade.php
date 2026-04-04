@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <style>
        body { background-color: #F9F9F9; color: #222; font: 14px/1.6 Helvetica, Arial, sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
        .header { background: #1a1a2e; color: #fff; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .body { padding: 24px; }
        .section { margin-bottom: 20px; padding: 16px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #6c757d; }
        .section.triggered { border-left-color: #28a745; }
        .section.loss { border-left-color: #dc3545; }
        .section.split-warning { border-left-color: #ffc107; background: #fffbec; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 4px; }
        .value { font-size: 16px; font-weight: 600; }
        .gain-positive { color: #28a745; }
        .gain-negative { color: #dc3545; }
        .row { display: flex; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 120px; }
        .action-link { display: inline-block; margin: 6px 8px 6px 0; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-secondary { background: #6c757d; color: #fff; }
        .btn-warning { background: #ffc107; color: #000; }
        .footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-primary { background: #0d6efd; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 8px; border: 1px solid #dee2e6; font-size: 13px; text-align: left; vertical-align: top; }
        th { background: #e9ecef; font-weight: 600; }
        a.sym-link { color: #0d6efd; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            @if ($isSplitWarning)
                ⚠️ Price Alert — Possible Stock Split
            @else
                🔔 Price Alert Triggered
            @endif
        </h1>
    </div>
    <div class="body">

        @if ($isSplitWarning)
        {{-- SPLIT WARNING --}}
        <div class="section split-warning">
            <div class="label">Split Anomaly Warning</div>
            <p>
                The price alert for <strong>{{ $alert->symbol }}</strong> may be stale due to a possible stock split.
            </p>
            <p>
                Current price: <strong>{{ MoneyFormat::get_formatted_price($currentPrice) }} {!! html_entity_decode($currencyDisplayCode, ENT_HTML5, 'UTF-8') !!}</strong><br>
                Alert target: <strong>{{ MoneyFormat::get_formatted_price($alert->target_price) }} {!! html_entity_decode($currencyDisplayCode, ENT_HTML5, 'UTF-8') !!}</strong>
            </p>
            <p>Please review and update your alert. You can re-run the suggestion engine to get a fresh target price using split-adjusted data.</p>
        </div>
        @else
        {{-- TRIGGER DETAILS --}}
        @php
            $isLoss = $projectedGain && $projectedGain['gain_value'] < 0;
        @endphp
        <div class="section triggered {{ $isLoss ? 'loss' : '' }}">
            @php
                $directionText = $alert->alert_type === 'PRICE_ABOVE' ? 'above' : 'below';
                $currencyHtml  = html_entity_decode($currencyDisplayCode, ENT_HTML5, 'UTF-8');
            @endphp
            <div style="font-size:18px;font-weight:700;margin-bottom:8px;">
                <a class="sym-link" href="https://finance.yahoo.com/quote/{{ $alert->symbol }}"
                   target="_blank">{{ $alert->symbol }}</a>
            </div>
            <div style="font-size:14px;margin-bottom:12px;">
                Current price of
                <strong>{{ MoneyFormat::get_formatted_price($currentPrice) }} {!! $currencyHtml !!}</strong>
                is {{ $directionText }} the alert target of
                <strong>{{ MoneyFormat::get_formatted_price((float) $alert->target_price) }} {!! $currencyHtml !!}</strong>
            </div>

            @if (!empty($alert->notes))
            <div class="label">Notes</div>
            <div style="margin-bottom:8px">{{ $alert->notes }}</div>
            @endif

            <div class="label">Source</div>
            <div>{{ ucfirst(str_replace('_', ' ', $alert->source)) }}
                — fired {{ $alert->trigger_count + 1 }} {{ ($alert->trigger_count + 1) == 1 ? 'time' : 'times' }}</div>
        </div>

        {{-- PROJECTED GAIN/LOSS --}}
        @if ($projectedGain)
        <div class="section {{ $isLoss ? 'loss' : 'triggered' }}">
            <div class="label">Position & Projected {{ $isLoss ? 'Loss ⚠️' : 'Gain ✅' }}</div>
            <table>
                <tr>
                    <th>Qty</th>
                    <th>Avg Cost</th>
                    <th>Target Price</th>
                    <th>Projected Gain/Loss</th>
                </tr>
                <tr>
                    <td>{{ MoneyFormat::get_formatted_quantity_plain($projectedGain['total_qty']) }}</td>
                    <td>{{ MoneyFormat::get_formatted_price($projectedGain['avg_cost']) }}
                        {!! html_entity_decode($currencyDisplayCode, ENT_HTML5, 'UTF-8') !!}</td>
                    <td>{{ MoneyFormat::get_formatted_price($alert->target_price) }}
                        {!! html_entity_decode($currencyDisplayCode, ENT_HTML5, 'UTF-8') !!}</td>
                    <td class="{{ $isLoss ? 'gain-negative' : 'gain-positive' }}">
                        <strong>
                            {{ $projectedGain['gain_value'] >= 0 ? '+' : '' }}{{ number_format($projectedGain['gain_value'], 2) }}
                            {!! html_entity_decode($currencyDisplayCode, ENT_HTML5, 'UTF-8') !!}
                            ({{ $projectedGain['gain_pct'] >= 0 ? '+' : '' }}{{ number_format($projectedGain['gain_pct'], 2) }}%)
                        </strong>
                        @if ($projectedGain['gain_eur'] !== null && $projectedGain['trade_currency'] !== 'EUR')
                        <br>≈ {{ $projectedGain['gain_eur'] >= 0 ? '+' : '' }}{{ number_format($projectedGain['gain_eur'], 2) }} &euro;
                        @endif
                    </td>
                </tr>
            </table>
            @if ($isLoss)
            <p style="margin-top:8px;font-size:12px;color:#6c757d;">
                ⚠️ This is a loss-minimizing alert — best available exit based on the recent high.
            </p>
            @endif
        </div>
        @else
        <div class="section">
            <div class="label">Position</div>
            <div>No open position found for {{ $alert->symbol }}.</div>
        </div>
        @endif

        {{-- ACTION LINKS --}}
        <div style="margin-top: 20px;">
            <div class="label" style="margin-bottom: 8px;">Quick Actions</div>
            <a href="{{ $createOrderUrl }}" class="action-link btn-primary"
               style="color:#fff !important;text-decoration:none;">
                {{ $alert->alert_type === 'PRICE_ABOVE' ? '→ Create Sell Order' : '→ Create Buy Order' }}
            </a>
            <a href="{{ $manageAlertUrl }}" class="action-link btn-secondary"
               style="color:#fff !important;text-decoration:none;">
                ✏️ Manage Alert
            </a>
            <a href="{{ $pauseAlertUrl }}" class="action-link btn-warning"
               style="color:#000 !important;text-decoration:none;">
                ⏸ Pause Alert
            </a>
        </div>
        @endif

    </div>
    <div class="footer">
        <strong>MyFinance2</strong> — Price Alert #{{ $alert->id }} for {{ $alert->symbol }}<br>
        This alert will continue to fire (max once per day per symbol) until you pause or delete it.
    </div>
</div>
</body>
</html>
