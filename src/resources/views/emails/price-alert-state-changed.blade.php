@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <style>
        body { background-color: #F9F9F9; color: #222; font: 14px/1.6 Helvetica, Arial, sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 680px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
        .header { background: #1a1a2e; color: #fff; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .body { padding: 24px; }
        .section { margin-bottom: 20px; padding: 16px; background: #f8f9fa; border-radius: 4px; }
        .section.paused  { border-left: 4px solid #ffc107; }
        .section.resumed { border-left: 4px solid #198754; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 4px; }
        .action-link { display: inline-block; margin: 6px 8px 6px 0; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-primary { background: #0d6efd; color: #fff; }
        .footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .badge-primary   { background: #0d6efd; color: #fff; }
        .badge-secondary { background: #6c757d; color: #fff; }
        .badge-success   { background: #198754; color: #fff; }
        .badge-danger    { background: #dc3545; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 8px; border: 1px solid #dee2e6; font-size: 13px; text-align: left; vertical-align: top; }
        th { background: #e9ecef; font-weight: 600; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        a.sym-link { color: #0d6efd; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            @if ($actionRaw === 'paused')
                ⏸ Alert(s) Paused
            @else
                ▶ Alert(s) Resumed
            @endif
            ({{ count($alerts) }})
        </h1>
    </div>
    <div class="body">

        <div class="section {{ $actionRaw }}">
            <div class="label">Status Change</div>
            <p style="margin:0">
                @if (count($alerts) === 1)
                    Alert <strong>#{{ $alerts[0]->id }}</strong>
                    for <strong>{{ $alerts[0]->symbol }}</strong>
                    has been <strong>{{ $actionRaw }}</strong>.
                @else
                    <strong>{{ count($alerts) }}</strong> alerts have been
                    <strong>{{ $actionRaw }}</strong>.
                @endif
            </p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Symbol</th>
                    <th>Type</th>
                    <th class="num">Target Price</th>
                    <th>Account(s)</th>
                    <th>New Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($alerts as $alert)
                @php
                    $currency   = html_entity_decode(strip_tags($alert->tradeCurrencyModel?->display_code ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $typeLabel  = $alert->alert_type === 'PRICE_ABOVE' ? '▲ Above' : '▼ Below';
                    $typeBadge  = $alert->alert_type === 'PRICE_ABOVE' ? 'badge-danger' : 'badge-primary';
                    $statusBadge = $actionRaw === 'paused' ? 'badge-secondary' : 'badge-success';
                    $statusLabel = $actionRaw === 'paused' ? 'PAUSED' : 'ACTIVE';
                @endphp
                <tr>
                    <td>{{ $alert->id }}</td>
                    <td>
                        <a class="sym-link"
                           href="https://finance.yahoo.com/quote/{{ $alert->symbol }}"
                           target="_blank">
                            <strong>{{ $alert->symbol }}</strong>
                        </a>
                    </td>
                    <td><span class="badge {{ $typeBadge }}">{{ $typeLabel }}</span></td>
                    <td class="num">
                        {{ MoneyFormat::get_formatted_price((float) $alert->target_price) }}
                        @if ($currency) {{ $currency }} @endif
                    </td>
                    <td>
                        @if (!empty($accountNames[$alert->symbol] ?? []))
                            @foreach ($accountNames[$alert->symbol] as $accountName)
                                <div style="white-space: nowrap">{{ $accountName }}</div>
                            @endforeach
                        @else
                            <span style="color:#6c757d">—</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top: 20px;">
            <a href="{{ $dashboardUrl }}" class="action-link btn-primary"
               style="color: #fff !important; text-decoration: none;">View All Alerts</a>
        </div>

    </div>
    <div class="footer">
        <strong>MyFinance2</strong> — Price Alerts
    </div>
</div>
</body>
</html>
