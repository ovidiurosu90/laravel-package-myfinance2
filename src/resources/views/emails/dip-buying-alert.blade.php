@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\DipBuyingPresenter')
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
        .section.behind { border-left-color: #dc3545; }
        .section.ahead  { border-left-color: #ffc107; }
        .section.on_plan { border-left-color: #28a745; }
        .section.episode { border-left-color: #0d6efd; background: #fff; border: 1px solid #dee2e6; border-left: 4px solid #0d6efd; }
        .section h2 { margin: 0 0 8px; font-size: 15px; font-weight: 600; }
        .section p { margin: 0 0 6px; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-top: 16px; }
        .text-muted { color: #6c757d; }
        .red { color: #dc3545; }
        .blue { color: #0d6efd; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 8px; border: 1px solid #dee2e6; font-size: 13px; text-align: left; }
        th { background: #e9ecef; font-weight: 600; }
        .num { text-align: right; }
        tr.current td { background: #eafaf0; }
        tr.current td:first-child { border-left: 3px solid #28a745; }
        .cols { width: 100%; margin-top: 8px; }
        .cols td { border: none; vertical-align: top; padding: 4px 12px 4px 0; }
        .footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        .action-link { display: inline-block; margin: 8px 8px 0 0; padding: 8px 16px; border-radius: 4px; background: #0d6efd; color: #fff !important; font-size: 13px; font-weight: 600; text-decoration: none; }
        .action-link.secondary { background: #6c757d; }
    </style>
</head>
@php
    // Every display decision comes from the shared view model ($present, DipBuyingPresenter), the same
    // one the /positions panel renders, so this email can never show a different verdict, number or
    // color. Only email-specific framing stays here: the VUSA.AS link injection and the status-key to
    // inline-hex map (web Bootstrap classes do not survive most mail clients).
    $num     = fn ($v) => MoneyFormat::get_formatted_number_plain((float) $v, 0);
    $dd      = $present['dd_fmt'];
    $driver  = $present['driver'];
    $vusaDd  = $present['vusa_dd_pct'] !== null
        ? MoneyFormat::get_formatted_pct((float) $present['vusa_dd_pct'])
        : null;
    $trend   = $present['trend'];
    $above   = $trend['above'] ?? null;
    $period  = $trend['period'] ?? 200;
    $stall   = $present['stall'];
    $verdict = $present['verdict'];
    $regime  = $present['regime'];
    $ladder  = $present['ladder'];

    // Every VUSA.AS reference points to its Yahoo Finance quote. Two link colors: a light one for the
    // dark header, the brand blue for the white body. Labels are controlled (never user input), so a
    // straight str_replace into escaped text is safe.
    $vusaLink      = '<a href="' . e($vusaUrl) . '" target="_blank" style="color:#0d6efd;text-decoration:none;">VUSA.AS</a>';
    $vusaLinkLight = '<a href="' . e($vusaUrl) . '" target="_blank" style="color:#9ecbff;">VUSA.AS</a>';
    // Basis-table label link that inherits the cell color, so the whole tinted label still links out.
    $vusaLinkInherit = '<a href="' . e($vusaUrl) . '" target="_blank" style="color:inherit;">VUSA.AS</a>';
    $linkVusaTinted  = fn ($t) => str_replace('VUSA.AS', $vusaLinkInherit, e($t));

    // Status key -> inline hex (mirrors the panel's Bootstrap classes for the same keys).
    $statusColor = [
        'passed_behind' => '#b8860b',
        'passed_ahead'  => '#28a745',
        'deploy_now'    => '#dc3545',
        'current'       => '#222',
        'reserved'      => '#6c757d',
        'none'          => '#6c757d',
    ];

    $ce  = $current ?? null;
    $rec = $present['recommendation'];
@endphp
<body>
    <div class="container">
        <div class="header">
            <h1>Dip Buying Plan</h1>
            <div class="sub">
                Effective drawdown -{{ $dd }}%
                @if ($vusaDd !== null)
                    ({!! $driver === 'VUSA.AS' ? $vusaLinkLight : e($driver) !!} is the deeper of the two;
                    {!! $vusaLinkLight !!} -{{ $vusaDd }}%)
                @endif
            </div>
        </div>
        <div class="body">
            <div class="section {{ $verdict['key'] }}">
                <h2>{{ $verdict['label'] }}</h2>
                @if ($trigger === 'new_episode')
                    <p style="font-weight:600;color:#0d6efd;">
                        New dip cycle: {!! $vusaLink !!} reached a new high and is pulling back again.<br>
                        At -{{ $dd }}% drawdown, your plan calls for
                        {{ (int) round($plan['target_pct']) }}% of the pool deployed.
                    </p>
                @endif
                <p>{{ $verdict['banner'] }}</p>
                @if (!is_null($above))
                    <p class="text-muted">
                        VUSA is {{ $above ? 'above' : 'below' }} its {{ $period }}-day MA:
                        {{ $above ? 'an uptrend' : 'a downtrend, the normal time to deploy' }}.
                    </p>
                @endif
                @if (!empty($stall['active']))
                    <p class="text-muted">
                        Stall backstop active: this episode has been open about
                        {{ $stall['months_stalled'] }} months without a deeper band, so the plan is
                        releasing the remaining pool on a slow monthly schedule.
                    </p>
                @endif
            </div>

            @if (!empty($regime))
                <div class="label">Drawdown now, by basis</div>
                <table>
                    <thead>
                        <tr>
                            <th>Basis</th>
                            <th class="num">Drawdown</th>
                            <th class="num">Down now</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($regime as $r)
                        <tr>
                            <td style="color: {{ $r['color'] }};">
                                {!! $linkVusaTinted($r['label']) !!}
                            </td>
                            <td class="num {{ $r['dd_pct'] > 0.005 ? 'red' : 'text-muted' }}">
                                @if ($r['is_effective'])
                                    <strong>{{ $r['dd_fmt'] }}</strong>
                                    <span style="color:#28a745;"
                                          title="This is the effective drawdown the ladder deploys on.">&#10003;</span>
                                @else
                                    {{ $r['dd_fmt'] }}
                                @endif
                            </td>
                            <td class="num {{ $r['down_now_pct'] > 0.005 ? 'red' : 'text-muted' }}">
                                {{ $r['down_now_fmt'] }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="text-muted" style="font-size:12px;margin-top:6px;">
                    Drawdown is below the running peak over the lookback window; Down now is below each
                    basis's most recent local peak (a near-term pullback), so Down now is not comparable
                    across rows. Effective drawdown is the axis the ladder acts on.
                </p>
            @endif

            <div class="label">Ladder</div>
            <p style="margin:4px 0 0;">Deployed so far
                <strong>{{ round($ladder['deployed_pct'], 1) }}%</strong>
                <span class="text-muted">(€{{ $num($ladder['deployed_eur']) }})</span>
            </p>
            <table style="margin-bottom:18px;">
                <thead>
                    <tr><th>Drawdown</th><th class="num">Target</th><th class="num">Gap</th><th>Status</th></tr>
                </thead>
                <tbody>
                @foreach ($ladder['rows'] as $row)
                    @php
                        $color   = $statusColor[$row['status_key']] ?? '#6c757d';
                        // Inline the highlight: many mail clients drop <style> selectors like tr.current td.
                        $rowBg   = $row['is_current'] ? 'background:#eafaf0;font-weight:600;' : '';
                        $firstTd = $rowBg . ($row['is_current'] ? 'border-left:3px solid #28a745;' : '');
                    @endphp
                    <tr>
                        <td style="{{ $firstTd }}">{{ $row['dd_label'] }}</td>
                        <td class="num" style="{{ $rowBg }}">{{ (int) $row['target_pct'] }}%@if ($row['target_pct'] > 0) <span class="text-muted">€{{ $num($row['target_eur']) }}</span>@endif</td>
                        <td class="num" style="{{ $rowBg }}color:{{ $color }};">{{ ($row['gap_pct'] >= 0 ? '+' : '-') . round(abs($row['gap_pct']), 1) }}% {{ ($row['gap_eur'] >= 0 ? '+' : '-') . '€' . $num(abs($row['gap_eur'])) }}</td>
                        <td style="{{ $rowBg }}color:{{ $color }};font-weight:600;">{{ $row['status_label'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @if (!empty($ce))
                @php
                    $ceGapPct  = $verdict['gap_pct'];
                    $ceGapEur  = $verdict['gap_eur'];
                    $runningDd = $ce['running_dd'] ?? null;
                    $peakDd    = $ce['peak_dd'] ?? null;
                    $athSuffix = $present['ath_suffix'];
                @endphp
                <div class="section episode">
                    <h2 class="blue">Current episode (pending)</h2>
                    <p class="text-muted">
                        Now <span class="blue">-{{ $ce['current_dd'] }}%</span> from the local peak on
                        {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}@if ($ce['max_dd'] > $ce['current_dd']); deepest
                        -{{ $ce['max_dd'] }}% on {{ DipBuyingPresenter::shortDate($ce['low_date']) }}@endif.
                        <br>Cash pool €{{ $num($ce['pool_eur']) }} as of {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}.
                    </p>

                    @if (!is_null($runningDd))
                        <p style="margin-top:10px;"><strong>Two rulers for this dip</strong></p>
                        <p>
                            <span class="red">-{{ $runningDd }}%</span> below your all-time high
                            <span class="text-muted">(the effective drawdown the ladder deploys on)</span>
                        </p>
                        <p>
                            <span class="blue">-{{ $ce['current_dd'] }}%</span> below the most recent local
                            peak on {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}
                            <span class="text-muted">(a fresh near-term pullback)</span>
                        </p>
                        @if (!is_null($peakDd))
                            <p class="text-muted">
                                That local peak, on {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}, was itself
                                -{{ $peakDd }}% below the all-time high{!! $athSuffix !!}.
                            </p>
                        @endif
                    @endif

                    <p class="blue" style="margin-top:10px;">{{ $rec['text'] }}</p>

                    <table class="cols">
                        <tr>
                            <td>
                                <div class="label" style="margin-top:0;">Your actual (this dip)</div>
                                <div>Net deployed <strong>€{{ $num($ce['actual']['deployed_eur']) }}</strong>
                                    <br>({{ $ce['actual']['deployed_pct'] }}% of €{{ $num($ce['pool_eur']) }})</div>
                                <div class="text-muted">
                                    Avg entry drawdown -{{ $ce['actual']['avg_entry_dd'] }}%
                                    <br>({{ DipBuyingPresenter::plural($ce['actual']['buy_count'], 'buy') }}, {{ DipBuyingPresenter::plural($ce['actual']['sell_count'] ?? 0, 'sell') }})
                                </div>
                            </td>
                            <td>
                                <div class="label" style="margin-top:0;">Guided ladder (now)</div>
                                <div>Target <strong>€{{ $num($ce['guided']['target_eur']) }}</strong>
                                    <br>({{ $ce['guided']['target_pct'] }}% of €{{ $num($ce['pool_eur']) }})</div>
                                <div class="text-muted">Avg entry drawdown -{{ $ce['guided']['avg_entry_dd'] }}%</div>
                                <div class="text-muted">
                                    Reserve kept: €{{ $num($ce['guided']['reserve_kept_eur']) }}
                                </div>
                            </td>
                            <td>
                                <div class="label" style="margin-top:0;">Deploy more?</div>
                                <div>Deployed vs target:
                                    <strong>{{ ($ceGapPct >= 0 ? '+' : '') . round($ceGapPct, 1) }}</strong>
                                    pts of pool
                                    <br>({{ ($ceGapEur >= 0 ? '+' : '-') . '€' . $num(abs($ceGapEur)) }})
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            @endif

            <p style="margin-top:16px;">
                <a class="action-link" href="{{ $panelUrl }}">Open the panel</a>
                <a class="action-link secondary" href="{{ $settingsUrl }}">Manage alerts</a>
            </p>
        </div>
        <div class="footer">
            This is behavioral pacing for a dip fund you keep anyway; it does not promise to beat
            staying invested. The trend rail is context, never a "wait" signal.
            {!! $vusaLink !!}
        </div>
    </div>
</body>
</html>
