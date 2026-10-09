@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
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
        .section { margin-bottom: 20px; padding: 16px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #dc3545; }
        .action-link { display: inline-block; margin: 6px 8px 6px 0; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .btn-primary { background: #0d6efd; color: #fff; }
        .footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 8px; border: 1px solid #dee2e6; font-size: 13px; text-align: left; vertical-align: top; }
        th { background: #e9ecef; font-weight: 600; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        a.sym-link { color: #0d6efd; text-decoration: none; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #6c757d; color: #fff; }
        code { background: #e9ecef; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>&#9888; Returns: positions valued at 0 (no price found)</h1>
    </div>
    <div class="body">

        <div class="section">
            The returns refresh found no price for the positions below, on the valuation date or up to 7 days
            before it, so they are left out of the start/end value and the return of those years is wrong.
            This usually means the symbol was delisted and the finance API dropped its history.
        </div>

        <table>
            <thead>
                <tr>
                    <th>Year</th>
                    <th>Valuation date</th>
                    <th>Symbol</th>
                    <th>Account</th>
                    <th class="num">Quantity</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($positionsByYear as $year => $positions)
                    @foreach ($positions as $position)
                    <tr>
                        <td>{{ $year }}</td>
                        <td>{{ $position['date'] }}</td>
                        <td>
                            <a class="sym-link" href="https://finance.yahoo.com/quote/{{ $position['symbol'] }}"
                               target="_blank"><strong>{{ $position['symbol'] }}</strong></a>
                            @if ($position['delisted'])
                                <span class="badge">Delisted</span>
                            @endif
                        </td>
                        <td>{{ $position['account_name'] }}</td>
                        <td class="num">{{ MoneyFormat::get_formatted_quantity_plain($position['quantity']) }}</td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <p style="margin-top: 16px;">
            Fix: add a <code>price_overrides</code> entry for the last trading day on or before each valuation
            date (Jan 1 uses the previous Dec 31) and, if the symbol was delisted, add it to
            <code>delisted_symbols</code>. Then refresh the returns cache.
        </p>

        <div>
            @foreach ($returnsLinks as $year => $url)
                <a href="{{ $url }}" class="action-link btn-primary"
                   style="color: #fff !important; text-decoration: none;">Returns {{ $year }}</a>
            @endforeach
        </div>

    </div>
    <div class="footer">
        <strong>MyFinance2</strong>: Returns refresh (app:finance-api-cron --refresh-returns)
    </div>
</div>
</body>
</html>
