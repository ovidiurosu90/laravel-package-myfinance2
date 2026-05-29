@php
    $posDays     = (int) ($row['position_days'] ?? 0);
    $showOverall = !empty($row['show_overall']);
    $showCurrent = !empty($row['show_current']);
    $overallTime = $row['overall_period'] ?? null;

    // Format a day count the same way the performance service formats holding periods,
    // so the current and overall periods agree for a single window (no 9m vs 10m drift).
    $fmtPeriod = function (int $days): array {
        if ($days < 1) {
            return ['', ''];
        }
        if ($days < 30) {
            return ['< 1m', $days . 'd'];
        }
        $months = (int) round($days / 30.44);
        if ($months < 12) {
            return [$months . 'm', ''];
        }
        $years = intdiv($months, 12);
        $rem   = $months % 12;
        return [$years . 'y' . ($rem > 0 ? ' ' . $rem . 'm' : ''), ''];
    };

    [$posLabel, $posTip] = $fmtPeriod($posDays);

    // Drop the open marker; the position line already conveys the position is open.
    $overallSpan = $overallTime !== null
        ? trim(str_replace([', open', ' (open)', '(open)'], '', $overallTime))
        : null;

    $overallLabel = $overallSpan;
    $overallTip   = '';
    if ($overallSpan !== null && preg_match('/^\d+d$/', $overallSpan)) {
        $overallLabel = '< 1m';
        $overallTip   = $overallSpan;
    } elseif ($overallSpan === null && $showOverall && $posLabel !== '') {
        // Unlisted symbols (no perf windows): fall back to the position-derived label.
        $overallLabel = $posLabel;
        $overallTip   = $posTip;
    }

    // XIRR and the VUSA alpha are both computed over the full holding history, so they carry
    // the overall span's duration (drop the "N windows" prefix, keep just the time).
    $overallDuration = $overallSpan;
    if ($overallSpan !== null && preg_match('/\(([^)]+)\)/', $overallSpan, $m)) {
        $overallDuration = $m[1];
    }
@endphp
<td data-order="{{ $posDays }}" class="text-end text-muted align-top border-bottom">
    @if($showCurrent && $posLabel)
    <div class="text-nowrap"><span @if($posTip) data-bs-toggle="tooltip" title="{{ $posTip }}" @endif>{{ $posLabel }}</span></div>
    @endif
    @if($showOverall && $overallLabel)
    <div class="text-nowrap"><span @if($overallTip) data-bs-toggle="tooltip" title="{{ $overallTip }}" @endif>{{ $overallLabel }}</span></div>
    @endif
    @if(($row['market_1y_pct'] ?? null) !== null)
    <div class="text-nowrap">1y</div>
    @else
    <div class="text-nowrap text-muted">n/a</div>
    @endif
    @if($showOverall)
    <div class="text-nowrap">{{ $overallDuration }}</div>
    @endif
    @if(($row['alpha_vs_vusa_pct'] ?? null) !== null)
    <div class="text-nowrap">{{ $overallDuration }}</div>
    @endif
</td>
