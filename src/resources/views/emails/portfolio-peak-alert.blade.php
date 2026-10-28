{{-- Portfolio peak alert email: which (metric, window) pairs are inside their threshold, every window's proximity
     with a legend, the current snapshot and the benchmark context. Figures go through MoneyFormat in the same style
     as the other emails ("- 50,000 €", "+ 12.34 %"). --}}
@extends('myfinance2::emails.layouts.base')
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\PortfolioPeakAlertService')

@php
    // Same labels the subject line uses, so the two can never disagree.
    $windowLbl = PortfolioPeakAlertService::WINDOW_LABELS;
    $metricLbl = PortfolioPeakAlertService::METRIC_LABELS;

    // A row's peak/current columns are a EUR gain for change_eur and a raw return % for change_pct.
    $cellVal = fn (array $row, string $key) => MoneyFormat::get_formatted_gain(
        $row['metric'] === 'change_eur' ? '€' : '%', (float) $row[$key]
    );

    // Threshold as a clean label ("3 %" not "3.00 %").
    $thrLbl = fn ($t) => MoneyFormat::get_formatted_pct_compact($t) . "\u{00A0}%";

    // Inline-styled legend badge (web Bootstrap classes do not survive most mail clients).
    $badge = function (string $label, string $bg, string $fg, bool $border = false)
    {
        $b = $border ? 'border:1px solid #dee2e6;' : '';
        return '<span style="display:inline-block;padding:1px 6px;border-radius:3px;font-size:11px;'
            . 'font-weight:600;background:' . $bg . ';color:' . $fg . ';' . $b . '">' . $label . '</span>';
    };

    // EUR-gain magnitude floor, shown in the legend so the "n/a" rows are self-explanatory.
    $minPeakAbs = MoneyFormat::get_formatted_monetary_display(
        '€', (float) config('myfinance2.alerts.portfolio_peak.min_peak_abs_eur', 1000), 0
    );

    $hasPctRow = in_array('change_pct', array_column($breakdown, 'metric'), true);

    // What actually fired, named and quantified. Every figure in a given entry comes from the same
    // row, so the intro can never splice one window's name onto another window's proximity.
    $firesCount  = count($pairs);
    $firesNoun   = $firesCount === 1 ? 'window' : 'windows';
    $fireNames   = array_map(fn ($row) => PortfolioPeakAlertService::pairLabel($row), $pairs);
    $fireDetails = array_map(function ($row) use ($thrLbl)
    {
        // Proximity is <= 0 by construction, so it reads as a distance below the peak.
        return PortfolioPeakAlertService::pairLabel($row) . ', '
            . MoneyFormat::get_formatted_pct(abs((float) $row['proximity_pct'])) . "\u{00A0}% below peak (threshold "
            . $thrLbl($row['threshold_pct']) . ')';
    }, $pairs);

    $vusaLink = '<a href="' . e($vusaUrl) . '" target="_blank" style="color:#0d6efd;text-decoration:none;">VUSA.AS</a>';
@endphp

@section('styles')
        .section.peak { border-left-color: #0d6efd; }
        .label { margin-top: 16px; }
        .context { margin-top: 18px; padding: 12px 16px; background: #f1f3f5; border-radius: 4px; font-size: 12px;
            color: #6c757d; }
@endsection

@section('title', '🔔 Portfolio Peak Alert')

@section('subtitle')
    {{ $firesCount }} {{ $firesNoun }} inside their threshold: {{ implode(', ', $fireNames) }}.
@endsection

@section('body')
    <div class="section peak">
        <h2>{{ $firesCount }} {{ $firesNoun }} inside their threshold</h2>
        <ul style="margin:0 0 8px; padding-left: 18px;">
            @foreach ($fireDetails as $detail)
            <li>{{ $detail }}</li>
            @endforeach
        </ul>
        <p>
            Your portfolio has rallied back to a multi-period high on those windows. This
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
                <th class="num text-nowrap">From peak</th>
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
                <td class="num"
                    style="{{ $rowBg }}color:{{ $within ? '#28a745;font-weight:600' : '#222' }};">
                    {{ MoneyFormat::get_formatted_gain_plain('%', $row['proximity_pct']) }}
                </td>
                <td class="num" style="{{ $rowBg }}">{{ $thrLbl($row['threshold_pct']) }}</td>
                <td style="{{ $rowBg }}color:{{ $fires['color'] }};font-weight:600;">{{ $fires['label'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @include('myfinance2::emails.partials.portfolio-peak-legend')

    <div class="label">Current snapshot</div>
    <table>
        <tbody>
            @if (!is_null($changePctCurrent))
                <tr>
                    <td>Return on cost</td>
                    <td class="num">{!! MoneyFormat::get_formatted_gain('%', $changePctCurrent) !!}</td>
                </tr>
            @endif
            @if (!is_null($changeEurCurrent))
                <tr>
                    <td>EUR gain</td>
                    <td class="num">{!! MoneyFormat::get_formatted_gain('€', $changeEurCurrent) !!}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if (!is_null($vusaChangePct))
        <div class="context">
            Benchmark context: {!! $vusaLink !!} is
            <span class="text-nowrap">{{ MoneyFormat::get_formatted_gain_plain('%', $vusaChangePct) }}</span>
            from its own 2Y high. This is shown for perspective only; it never affects whether this alert fires.
        </div>
    @endif

    @include('myfinance2::emails.partials.action-links', ['links' => [
        ['label' => '✏️ Manage Alerts', 'url' => $settingsUrl, 'variant' => 'secondary'],
    ]])
@endsection

@section('footer-title', 'Portfolio peak alert')

@section('footer')
    A portfolio-level "consider a review" hint, complementary to the per-symbol peak-proximity alerts. It does not
    promise the top is in; it flags that you are near one.
@endsection
