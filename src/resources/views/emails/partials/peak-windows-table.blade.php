{{-- Every window with its peak, trigger target and distance, the windows near peak highlighted: the near-peak and
     the take-profit summaries. Included by them, reading the email's variables. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<table>
    <tr>
        <th>Window</th>
        <th class="text-nowrap">From peak</th>
        <th>Peak price</th>
        <th>Peak date</th>
        <th>Trigger target</th>
        <th>To go</th>
    </tr>
    @foreach ($summaryWindows as $p)
    <tr @if ($p['near']) class="near-peak" @endif>
        <td class="text-nowrap">{{ $p['label'] }}@if ($p['near']) <span
            class="badge bg-success">near peak</span>@endif</td>
        <td class="text-nowrap">{!! $p['prox'] !== null ? MoneyFormat::get_formatted_gain('%', $p['prox'])
            : '<span class="text-muted">n/a</span>' !!}</td>
        <td class="text-nowrap">
            @if ($p['peak'] !== null){!! MoneyFormat::get_formatted_price_display(
                $cur, $p['peak'], true
            ) !!}@else <span class="text-muted">n/a</span>@endif
        </td>
        <td class="text-nowrap">{{ $p['date'] ?? 'n/a' }}</td>
        <td class="text-nowrap">
            @if ($p['target'] !== null)
            {!! MoneyFormat::get_formatted_price_display($cur, $p['target'], true) !!}
            @if ($p['thr'] !== null)<span class="text-muted">({{ MoneyFormat::get_formatted_gain_plain(
                '%', -$p['thr']) }})</span>@endif
            @else
            <span class="text-muted">n/a</span>
            @endif
        </td>
        <td class="text-nowrap">
            @if ($p['near'])
            <span class="text-success">in zone</span>
            @elseif ($p['to_go'] !== null)
            {{ MoneyFormat::get_formatted_gain_plain('%', $p['to_go']) }}
            @else
            <span class="text-muted">n/a</span>
            @endif
        </td>
    </tr>
    @endforeach
</table>
