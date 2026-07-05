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
        .header h1 { margin: 0; font-size: 19px; font-weight: 600; }
        .header .sub { margin-top: 6px; font-size: 13px; color: #c9c9d6; }
        .body { padding: 24px; }
        .section { margin-bottom: 18px; padding: 16px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #6c757d; }
        .section.peak { border-left-color: #0d6efd; }
        .section h2 { margin: 0 0 8px; font-size: 15px; font-weight: 600; }
        .section p { margin: 0 0 6px; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-top: 16px; }
        .text-muted { color: #6c757d; }
        .blue { color: #0d6efd; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 8px; border: 1px solid #dee2e6; font-size: 13px; text-align: left; }
        th { background: #e9ecef; font-weight: 600; }
        .num { text-align: right; }
        .footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        .action-link { display: inline-block; margin: 8px 8px 0 0; padding: 8px 16px; border-radius: 4px; background: #0d6efd; color: #fff !important; font-size: 13px; font-weight: 600; text-decoration: none; }
        .action-link.secondary { background: #6c757d; }
        .context { margin-top: 18px; padding: 12px 16px; background: #f1f3f5; border-radius: 4px; font-size: 12px; color: #6c757d; }
    </style>
</head>
@php
    // Display helpers. EUR figures route through MoneyFormat; the return-on-cost figures use the
    // percentage formatter. Proximity is <= 0 by construction (current is always inside the window).
    $eur       = fn ($v) => '&euro;' . MoneyFormat::get_formatted_number_plain((float) $v, 0);
    $pct       = fn ($v) => MoneyFormat::get_formatted_pct((float) $v);
    $windowLbl = ['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'];
    $metricLbl = ['change_eur' => 'EUR gain', 'change_pct' => 'Return %'];

    // A row's peak/current columns are EUR for change_eur and a raw return % for change_pct.
    $cellVal = function (array $row, string $key) use ($eur, $pct)
    {
        return $row['metric'] === 'change_eur'
            ? $eur($row[$key])
            : $pct($row[$key]) . '%';
    };

    // Threshold as a clean label ("3%" not "3.00%") without a raw number_format() call.
    $thrLbl = fn ($t) => ($t == (int) $t ? (string) (int) $t : MoneyFormat::get_formatted_pct($t)) . '%';

    // Inline-styled legend badge (web Bootstrap classes do not survive most mail clients).
    $badge = function (string $label, string $bg, string $fg, bool $border = false)
    {
        $b = $border ? 'border:1px solid #dee2e6;' : '';
        return '<span style="display:inline-block;padding:1px 6px;border-radius:3px;font-size:11px;'
            . 'font-weight:600;background:' . $bg . ';color:' . $fg . ';' . $b . '">' . $label . '</span>';
    };

    // EUR-gain magnitude floor, shown in the legend so the "n/a" rows are self-explanatory.
    $minPeakAbs = (float) config('alerts.portfolio_peak.min_peak_abs_eur', 1000);

    $closest   = max(array_column($pairs, 'proximity_pct'));
    $hasPctRow = in_array('change_pct', array_column($breakdown, 'metric'), true);

    $vusaLink = '<a href="' . e($vusaUrl) . '" target="_blank" style="color:#0d6efd;text-decoration:none;">VUSA.AS</a>';
@endphp
<body>
    <div class="container">
        <div class="header">
            <h1>Portfolio Peak Alert</h1>
            <div class="sub">
                Your portfolio is within {{ $closest }}% of a multi-period high.
            </div>
        </div>
        <div class="body">
            <div class="section peak">
                <h2>Portfolio near a rolling high</h2>
                <p>
                    Your portfolio has rallied back to a multi-period high on the metrics below. This
                    is a moment to review whether to reduce exposure, take some profit, or rebalance,
                    not an instruction to sell.
                </p>
            </div>

            <div class="label">Peak proximity by window</div>
            <table>
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Window</th>
                        <th class="num">Current</th>
                        <th class="num">Window peak</th>
                        <th>Peak date</th>
                        <th class="num">From peak</th>
                        <th class="num">Threshold</th>
                        <th>Fires?</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($breakdown as $row)
                    @php
                        // "Within" = proximity is inside this window's threshold band, flagged green
                        // even for the 3M context row so a near-term top is still visible.
                        $within = $row['proximity_pct'] !== null
                            && $row['proximity_pct'] >= -$row['threshold_pct'];
                        $rowBg  = $row['triggered'] ? 'background:#eafaf0;' : '';

                        if ($row['skipped']) {
                            $fires = ['label' => 'n/a', 'color' => '#6c757d'];
                        } elseif (!$row['is_trigger']) {
                            $fires = ['label' => 'context', 'color' => '#6c757d'];
                        } elseif ($row['triggered']) {
                            $fires = ['label' => 'yes', 'color' => '#28a745'];
                        } else {
                            $fires = ['label' => 'no', 'color' => '#6c757d'];
                        }
                    @endphp
                    <tr>
                        <td style="{{ $rowBg }}">{{ $metricLbl[$row['metric']] ?? $row['metric'] }}</td>
                        <td style="{{ $rowBg }}">{{ $windowLbl[$row['window']] ?? strtoupper($row['window']) }}</td>
                        <td class="num" style="{{ $rowBg }}">{!! $cellVal($row, 'current') !!}</td>
                        <td class="num" style="{{ $rowBg }}">{!! $cellVal($row, 'peak') !!}</td>
                        <td style="{{ $rowBg }}" class="text-muted">{{ $row['peak_date'] }}</td>
                        <td class="num" style="{{ $rowBg }}color:{{ $within ? '#28a745' : '#222' }};{{ $within ? 'font-weight:600;' : '' }}">
                            @if ($row['proximity_pct'] !== null){{ $row['proximity_pct'] }}%@else n/a @endif
                        </td>
                        <td class="num" style="{{ $rowBg }}">{{ $thrLbl($row['threshold_pct']) }}</td>
                        <td style="{{ $rowBg }}color:{{ $fires['color'] }};font-weight:600;">{{ $fires['label'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="text-muted" style="font-size:12px;margin:8px 0 4px;">
                Every window is shown, not only the ones that would fire.
            </p>
            <p class="text-muted" style="font-size:12px;margin:0 0 4px;">
                "From peak" is how far the current value sits below that window's high (always a negative
                drawdown; 0% means at the high); "Threshold" is how close it must get to fire.
            </p>
            <ul class="text-muted" style="font-size:12px;margin:0 0 4px;padding-left:18px;">
                <li style="margin-bottom:4px;">{!! $badge('yes', '#28a745', '#fff') !!} the window is inside its threshold now.</li>
                <li style="margin-bottom:4px;">{!! $badge('no', '#6c757d', '#fff') !!} a gating window still outside it.</li>
                <li style="margin-bottom:4px;">
                    {!! $badge('context', '#f8f9fa', '#222', true) !!} the 3M window (shown for awareness,
                    never fires on its own).
                </li>
                <li style="margin-bottom:4px;">
                    {!! $badge('n/a', '#f8f9fa', '#6c757d', true) !!} a EUR-gain window whose peak sits within
                    &plusmn;&euro;{{ MoneyFormat::get_formatted_number_plain($minPeakAbs, 0) }} of zero, i.e.
                    its magnitude |peak| is under the min_peak_abs_eur floor, too small to measure proximity
                    against without flipping on noise. A large negative peak is <strong>not</strong> skipped.
                </li>
            </ul>
            @if ($hasPctRow)
                <p class="text-muted" style="font-size:12px;margin:0 0 4px;">
                    For Return % rows the value columns show the raw return on cost, while From peak is the
                    value-index proximity (1 + return/100), so it stays sane even when the whole window is
                    underwater.
                </p>
            @endif
            <p class="text-muted" style="font-size:12px;margin:0;">
                EUR-gain proximity is measured against the magnitude of each window's peak (|peak|, ignoring
                sign), so only a peak within
                &plusmn;&euro;{{ MoneyFormat::get_formatted_number_plain($minPeakAbs, 0) }} of zero is skipped.
                A deeply negative peak (for example -&euro;50,000) is in scope: the alert fires when your
                current EUR gain climbs back to within the window's threshold of it.
            </p>

            <div class="label">Current snapshot</div>
            <table>
                <tbody>
                    @if (!is_null($changePctCurrent))
                        <tr>
                            <td>Return on cost</td>
                            <td class="num">{{ $pct($changePctCurrent) }}%</td>
                        </tr>
                    @endif
                    @if (!is_null($changeEurCurrent))
                        <tr>
                            <td>EUR gain</td>
                            <td class="num">{!! $eur($changeEurCurrent) !!}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            @if (!is_null($vusaChangePct))
                <div class="context">
                    Benchmark context: {!! $vusaLink !!} is {{ $vusaChangePct }}% from its own 2Y high.
                    This is shown for perspective only; it never affects whether this alert fires.
                </div>
            @endif

            <p style="margin-top:16px;">
                <a class="action-link secondary" href="{{ $settingsUrl }}">Manage alerts</a>
            </p>
        </div>
        <div class="footer">
            This is a portfolio-level "consider a review" hint, complementary to the per-symbol
            peak-proximity alerts. It does not promise the top is in; it flags that you are near one.
        </div>
    </div>
</body>
</html>
