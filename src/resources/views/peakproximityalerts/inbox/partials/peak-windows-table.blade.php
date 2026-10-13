{{-- Every window with its peak, trigger target and distance, the windows near peak highlighted: the body of a
     near-peak card and the context of a take-profit one. Included by the card, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<table class="table table-sm table-borderless mb-1 mt-1" style="width:auto;">
    <thead>
        <tr class="text-nowrap">
            <th class="pe-3 fw-semibold">Window</th>
            <th class="pe-3 fw-semibold">From peak</th>
            <th class="pe-3 fw-semibold">Peak price</th>
            <th class="pe-3 fw-semibold">Peak date</th>
            <th class="pe-3 fw-semibold">Trigger target</th>
            <th class="fw-semibold">To go</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sumWindows as $w)
            @php $isNear = $w['near'] ?? true; @endphp
            <tr class="text-nowrap @if ($isNear) table-success @endif">
                <td class="pe-3">{{ $w['label'] }}@if ($isNear) <span
                    class="badge bg-success ms-1">near peak</span>@endif</td>
                <td class="pe-3">{{ $signedPct($w['prox'] ?? null) }}</td>
                <td class="pe-3">{!! ($w['peak'] ?? null) !== null
                    ? MoneyFormat::get_formatted_price_display($cur, $w['peak'], true) : 'n/a' !!}</td>
                <td class="pe-3">{{ $w['date'] ?? 'n/a' }}</td>
                <td class="pe-3">
                    @if (($w['target'] ?? null) !== null)
                        {!! MoneyFormat::get_formatted_price_display($cur, $w['target'], true) !!}{{--
                        --}}@if (($w['thr'] ?? null) !== null) <span
                            class="text-muted">({{ $signedPct(-(float) $w['thr']) }})</span>@endif
                    @else
                        n/a
                    @endif
                </td>
                <td>
                    @if ($isNear)
                        <span class="text-success">in zone</span>
                    @elseif (($w['to_go'] ?? null) !== null)
                        {{ $signedPct($w['to_go']) }}
                    @else
                        n/a
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
