{{-- Signal backtest: every episode (newest first), collapsed by default: the symbol, the signal and action, held or
     watch-only, when it began, how long it lasted and its return against VUSA.AS at each horizon (green when it
     worked). Reads $report. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\TierSignalBacktest')
@php
    $pts = fn ($v) => ($v > 0 ? '+' : '') . MoneyFormat::get_formatted_number_plain((float) $v, 1);
@endphp
<p class="mb-1">
    <a class="small" data-bs-toggle="collapse" href="#bt-episodes" role="button" aria-expanded="false"
       aria-controls="bt-episodes">
        <i class="fa fa-fw fa-list" aria-hidden="true"></i> Every episode ({{ count($report['episodes']) }})
    </a>
</p>
<div class="collapse" id="bt-episodes">
    <div class="table-responsive">
        <table class="table table-sm table-striped table-hover small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Symbol</th>
                    <th>Signal</th>
                    <th>Group</th>
                    <th>Began</th>
                    <th class="text-end">Days</th>
                    @foreach ($report['horizons'] as $h)
                        <th class="text-end" data-bs-toggle="tooltip"
                            title="Return against VUSA.AS {{ $h }} trading days after the first day, in points">
                            {{ $h }}d</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report['episodes'] as $episode)
                    @php $epLabel = TierSignalBacktest::episodeLabel($episode); @endphp
                    <tr>
                        <td>{{ $episode['symbol'] }}</td>
                        <td class="text-nowrap">{{ $epLabel }}</td>
                        <td>{{ $episode['owned'] ? 'Held' : 'Watch only' }}</td>
                        <td class="text-nowrap">{{ $episode['start'] }}</td>
                        <td class="text-end">{{ $episode['days'] }}</td>
                        @foreach ($report['horizons'] as $h)
                            @php $rel = $episode['forward'][$h] ?? null; @endphp
                            @if ($rel === null)
                                <td class="text-end text-muted">-</td>
                            @else
                                <td @class(['text-end',
                                    'text-success' => TierSignalBacktest::isHit($episode['signal'], $rel),
                                    'text-danger' => !TierSignalBacktest::isHit($episode['signal'], $rel)])>
                                    {{ $pts($rel) }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
