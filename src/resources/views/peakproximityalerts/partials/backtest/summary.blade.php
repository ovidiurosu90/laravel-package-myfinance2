{{-- Signal backtest: per signal (all episodes, then held and watch-only) and horizon, how often it worked, the median
     and the mean relative return, then the baseline of every evaluated day. A worked share above the baseline's is
     green, below it red. Reads $report. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\TierSignalBacktest')
@php
    $num = fn ($v) => $v === null ? 'n/a' : MoneyFormat::get_formatted_number_plain((float) $v, 0);
    $pts = fn ($v) => $v === null ? 'n/a'
        : (($v > 0 ? '+' : '') . MoneyFormat::get_formatted_number_plain((float) $v, 1));
    $signalNames = TierSignalBacktest::SIGNAL_NAMES;
    $groupNames = [
        TierSignalBacktest::GROUP_ALL   => 'All',
        TierSignalBacktest::GROUP_HELD  => 'Held',
        TierSignalBacktest::GROUP_WATCH => 'Watch only',
    ];
    // The share of all days the signal had to beat: trailing for a sell or a review, beating for a buy.
    $baseShare = function (string $signal, int $h) use ($report): ?float {
        $under = $report['baseline'][$h]['under_rate'] ?? null;
        if ($under === null) {
            return null;
        }

        return in_array($signal, TierSignalBacktest::BUY_SIGNALS, true) ? 100.0 - $under : $under;
    };
@endphp
<div class="table-responsive mb-3">
    <table class="table table-sm table-bordered table-hover align-middle small mb-1">
        <thead class="table-light">
            <tr>
                <th rowspan="2">Signal</th>
                <th rowspan="2">Group</th>
                <th rowspan="2" class="text-end" data-bs-toggle="tooltip" title="Episodes: runs of consecutive days
                    with the same signal">Episodes</th>
                <th rowspan="2" class="text-end" data-bs-toggle="tooltip"
                    title="Median episode length, in trading days">Days</th>
                @foreach ($report['horizons'] as $h)
                    <th colspan="3" class="text-center">{{ $h }} trading days later</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($report['horizons'] as $h)
                    <th class="text-end" data-bs-toggle="tooltip"
                        title="Share of episodes with a figure that worked (n = episodes with {{ $h }} later closes)">
                        Worked</th>
                    <th class="text-end" data-bs-toggle="tooltip"
                        title="Median return against VUSA.AS, in points">Median</th>
                    <th class="text-end" data-bs-toggle="tooltip"
                        title="Mean return against VUSA.AS, in points (a few big moves can pull it)">Mean</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['summary'] as $row)
                <tr @class(['fw-semibold' => $row['group'] === TierSignalBacktest::GROUP_ALL])>
                    <td class="text-nowrap">{{ $signalNames[$row['signal']] ?? $row['signal'] }}</td>
                    <td class="text-nowrap">{{ $groupNames[$row['group']] ?? $row['group'] }}</td>
                    <td class="text-end">{{ $row['episodes'] }}</td>
                    <td class="text-end">{{ $num($row['median_days']) }}</td>
                    @foreach ($report['horizons'] as $h)
                        @php
                            $s    = $row['horizons'][$h];
                            $base = $baseShare($row['signal'], $h);
                            $tone = $s['hit_rate'] === null || $base === null ? ''
                                : ($s['hit_rate'] > $base ? 'text-success' : 'text-danger');
                        @endphp
                        @if ($s['n'] === 0)
                            <td colspan="3" class="text-center text-muted">no later closes yet</td>
                        @else
                            <td class="text-end text-nowrap {{ $tone }}" data-bs-toggle="tooltip"
                                title="{{ $s['n'] }} episodes; the baseline to beat is {{ $num($base) }} %">
                                {{ $num($s['hit_rate']) }} % <span class="text-muted fw-normal">({{ $s['n'] }})</span>
                            </td>
                            <td class="text-end">{{ $pts($s['median_rel']) }}</td>
                            <td class="text-end">{{ $pts($s['avg_rel']) }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + 3 * count($report['horizons']) }}" class="text-muted text-center">
                        No signals in the stored history.
                    </td>
                </tr>
            @endforelse
            <tr class="table-light">
                <td class="text-nowrap fw-semibold" colspan="4" data-bs-toggle="tooltip"
                    title="Every evaluated symbol-day, signal or not">Baseline: every day</td>
                @foreach ($report['horizons'] as $h)
                    @php $b = $report['baseline'][$h]; @endphp
                    <td class="text-end text-nowrap" data-bs-toggle="tooltip"
                        title="Trailed VUSA.AS on this share of {{ $num($b['n']) }} days (beat it on the rest)">
                        trailed {{ $num($b['under_rate']) }} %</td>
                    <td class="text-end">{{ $pts($b['median_rel']) }}</td>
                    <td class="text-end">{{ $pts($b['avg_rel']) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
    <div class="text-muted small">
        Worked in <span class="text-success">green</span> beats the baseline (a sell, a review or a TAKE PROFIT that
        trailed more often than any day did; an ADD or a BUY that beat the index more often), in
        <span class="text-danger">red</span> it does not. The range-bound and TAKE PROFIT rows replay those readings
        from what was stored each day: your record counts Own Lifetime only (not the completed-trades win rate), and
        a range-bound day needs a full year of closes behind it.
    </div>
</div>
