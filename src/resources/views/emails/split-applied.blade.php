<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <style>
        body { background-color: #F9F9F9; color: #222; font: 14px/1.6 Helvetica, Arial, sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; }
        .header { background: #1a1a2e; color: #fff; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .body { padding: 24px; }
        .section { margin-bottom: 20px; padding: 16px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #fd7e14; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 4px; }
        .action-link { display: inline-block; margin: 6px 8px 6px 0; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-primary { background: #0d6efd; color: #fff; }
        .footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 8px; border: 1px solid #dee2e6; font-size: 13px; text-align: left; vertical-align: top; }
        th { background: #e9ecef; font-weight: 600; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .arrow { color: #6c757d; }
        .new-val { font-weight: 600; }
        h3 { font-size: 15px; margin: 20px 0 4px; }
        .none-msg { color: #6c757d; font-style: italic; margin: 0; }
        a.sym-link { color: #0d6efd; text-decoration: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-primary { background: #0d6efd; color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>&#9988; Stock Split Applied &mdash; {{ $split->symbol }} {{ $split->getRatioLabel() }}</h1>
    </div>
    <div class="body">

        {{-- Split details --}}
        <div class="section">
            <div class="label">Split Event</div>
            <table>
                <tr>
                    <th>Symbol</th>
                    <th>Split Date</th>
                    <th>Ratio</th>
                    @if ($split->notes)
                    <th>Notes</th>
                    @endif
                </tr>
                <tr>
                    <td>
                        <a class="sym-link" href="https://finance.yahoo.com/quote/{{ $split->symbol }}"
                           target="_blank">
                            <strong>{{ $split->symbol }}</strong>
                        </a>
                    </td>
                    <td>{{ $split->split_date->format('Y-m-d') }}</td>
                    <td><strong>{{ $split->getRatioLabel() }}</strong></td>
                    @if ($split->notes)
                    <td>{{ $split->notes }}</td>
                    @endif
                </tr>
            </table>
        </div>

        {{-- Changed trades --}}
        <h3>Trades Updated ({{ $summary['trades_updated'] }})</h3>
        @if (!empty($summary['changed_trades']))
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Account</th>
                    <th>Date</th>
                    <th>Action</th>
                    <th class="num">Old Qty</th>
                    <th class="num"></th>
                    <th class="num">New Qty</th>
                    <th class="num">Old Price</th>
                    <th class="num"></th>
                    <th class="num">New Price</th>
                    <th>Currency</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['changed_trades'] as $t)
                <tr>
                    <td>{{ $t['id'] }}</td>
                    <td>{{ $t['account'] }}</td>
                    <td>{{ $t['date'] }}</td>
                    <td>{{ strtolower($t['action']) }}</td>
                    <td class="num">{{ $t['old_quantity'] }}</td>
                    <td class="num arrow">&rarr;</td>
                    <td class="num new-val">{{ $t['new_quantity'] }}</td>
                    <td class="num">{{ number_format($t['old_price'], 2) }}</td>
                    <td class="num arrow">&rarr;</td>
                    <td class="num new-val">{{ number_format($t['new_price'], 2) }}</td>
                    <td>{{ $t['currency'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="none-msg">No open trades were found for {{ $split->symbol }} on or before {{ $split->split_date->format('Y-m-d') }}.</p>
        @endif

        {{-- Adjusted alerts --}}
        <h3>Alerts Adjusted ({{ $summary['alerts_adjusted'] }})</h3>
        @if (!empty($summary['changed_alerts']))
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th class="num">Old Target</th>
                    <th class="num"></th>
                    <th class="num">New Target</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['changed_alerts'] as $a)
                @php
                    $typeLabel  = $a['alert_type'] === 'PRICE_ABOVE' ? '&#9650; Above' : '&#9660; Below';
                    $badgeClass = $a['alert_type'] === 'PRICE_ABOVE' ? 'badge-danger' : 'badge-primary';
                @endphp
                <tr>
                    <td>{{ $a['id'] }}</td>
                    <td><span class="badge {{ $badgeClass }}">{!! $typeLabel !!}</span></td>
                    <td class="num">{{ number_format($a['old_target_price'], 4) }}</td>
                    <td class="num arrow">&rarr;</td>
                    <td class="num new-val">{{ number_format($a['new_target_price'], 4) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="none-msg">No active price alerts were found for {{ $split->symbol }}.</p>
        @endif

        <div style="margin-top: 24px;">
            <a href="{{ $dashboardUrl }}" class="action-link btn-primary"
               style="color: #fff !important; text-decoration: none;">View All Splits</a>
        </div>

    </div>
    <div class="footer">
        <strong>MyFinance2</strong> &mdash; Stock Splits
    </div>
</div>
</body>
</html>
