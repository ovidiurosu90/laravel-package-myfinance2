@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    // Bootstrap version of the email's "Peak proximity by window" table. Data comes from
    // PortfolioPeakAlertService::_buildBreakdown (via previewForUser / the alert), the single source
    // of truth shared with the email, so the page and the email can never disagree.
    $breakdown = $breakdown ?? [];

    $eur       = fn ($v) => '&euro;' . MoneyFormat::get_formatted_number_plain((float) $v, 0);
    $pct       = fn ($v) => MoneyFormat::get_formatted_pct((float) $v);
    $windowLbl = ['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'];
    $metricLbl = ['change_eur' => 'EUR gain', 'change_pct' => 'Return %'];

    // change_eur cells are EUR; change_pct cells are a raw return %.
    $cellVal = function (array $row, string $key) use ($eur, $pct)
    {
        return $row['metric'] === 'change_eur'
            ? $eur($row[$key])
            : $pct($row[$key]) . '%';
    };

    $thrLbl = fn ($t) => ($t == (int) $t ? (string) (int) $t : MoneyFormat::get_formatted_pct($t)) . '%';

    // The EUR-gain magnitude floor: change_eur windows whose |peak| is under this are shown n/a,
    // because proximity divides by |peak| and would explode near zero.
    $minPeakAbs = (float) config('alerts.portfolio_peak.min_peak_abs_eur', 1000);
@endphp

@if (empty($breakdown))
    <p class="text-muted small mb-0">
        No overview series yet. The daily chart build populates your EUR gain and return-on-cost
        history; once it has data this table shows how far each window sits from its peak.
    </p>
@else
    <div class="table-responsive">
        <table class="table table-sm mb-2">
            <thead>
                <tr>
                    <th>Metric</th>
                    <th>Window</th>
                    <th class="text-end">Current</th>
                    <th class="text-end">Window peak</th>
                    <th>Peak date</th>
                    <th class="text-end text-nowrap">From peak</th>
                    <th class="text-end">Threshold</th>
                    <th>Fires?</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($breakdown as $row)
                @php
                    $within = $row['proximity_pct'] !== null
                        && $row['proximity_pct'] >= -$row['threshold_pct'];

                    if ($row['skipped']) {
                        $fires = ['label' => 'n/a', 'class' => 'bg-light text-muted border'];
                    } elseif (!$row['is_trigger']) {
                        $fires = ['label' => 'context', 'class' => 'bg-light text-dark border'];
                    } elseif ($row['triggered']) {
                        $fires = ['label' => 'yes', 'class' => 'bg-success'];
                    } else {
                        $fires = ['label' => 'no', 'class' => 'bg-secondary'];
                    }
                @endphp
                <tr @class(['table-success' => $row['triggered']])>
                    <td class="text-nowrap">{{ $metricLbl[$row['metric']] ?? $row['metric'] }}</td>
                    <td>{{ $windowLbl[$row['window']] ?? strtoupper($row['window']) }}</td>
                    <td class="text-end text-nowrap">{!! $cellVal($row, 'current') !!}</td>
                    <td class="text-end text-nowrap">{!! $cellVal($row, 'peak') !!}</td>
                    <td class="text-nowrap text-muted">{{ $row['peak_date'] }}</td>
                    <td class="text-end text-nowrap @if ($within) text-success fw-semibold @endif">
                        @if ($row['proximity_pct'] !== null){{ $row['proximity_pct'] }}%@else n/a @endif
                    </td>
                    <td class="text-end text-nowrap">{{ $thrLbl($row['threshold_pct']) }}</td>
                    <td><span class="badge {{ $fires['class'] }}">{{ $fires['label'] }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="form-text mb-0">
        <p class="mb-1">Every window is shown, not only the ones that would fire.</p>
        <p class="mb-1">
            "From peak" is how far the current value sits below that window's high (always a negative
            drawdown; 0% means at the high); "Threshold" is how close it must get to fire.
        </p>
        <ul class="mb-1 ps-3">
            <li><span class="badge bg-success">yes</span> the window is inside its threshold now.</li>
            <li><span class="badge bg-secondary">no</span> a gating window still outside it.</li>
            <li>
                <span class="badge bg-light text-dark border">context</span> the 3M window (shown for
                awareness, never fires on its own).
            </li>
            <li>
                <span class="badge bg-light text-muted border">n/a</span> a EUR-gain window whose peak
                sits within &plusmn;&euro;{{ MoneyFormat::get_formatted_number_plain($minPeakAbs, 0) }}
                of zero, i.e. its magnitude <code>|peak|</code> is under the
                <code>min_peak_abs_eur</code> floor, too small to measure proximity against without
                flipping on noise. A large negative peak is <strong>not</strong> skipped.
            </li>
        </ul>
        <p class="mb-1">
            For Return % rows the value columns show the raw return on cost, while From peak is the
            value-index proximity (1 + return/100).
        </p>
        <p class="mb-0">
            EUR-gain proximity is measured against the <strong>magnitude</strong> of each window's peak
            (<code>|peak|</code>, ignoring sign), so only a peak within
            &plusmn;&euro;{{ MoneyFormat::get_formatted_number_plain($minPeakAbs, 0) }} of zero is
            skipped. A deeply negative peak (for example -&euro;50,000) is in scope: the alert fires
            when your current EUR gain climbs back to within the window's threshold of it.
        </p>
    </div>
@endif
