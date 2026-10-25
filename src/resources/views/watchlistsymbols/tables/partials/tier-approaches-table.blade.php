{{-- The approach list of the Tier line, one approach per row (opened by its "details" toggle): number, full
     name (hover: what it measures), figure and tier; the approach in use highlighted. $cells is the reading's
     'cells' (TierReadingBuilder::cells()). --}}
@php
    use ovidiuro\myfinance2\App\Services\TierApproachComparison;
@endphp
@if(!empty($cells))
<div id="{{ $detailsId }}" class="collapse">
    <table class="mt-1 ms-4" style="font-size:0.9em;line-height:1.6;font-variant-numeric:tabular-nums;">
        @foreach($cells as $cell)
            @php
                $tier  = $cell['tier'] ?? null;
                $value = $cell['value'] ?? null;
            @endphp
            <tr @if($cell['in_use']) style="background-color:rgba(13,110,253,0.06)" @endif>
                <td class="pe-2 text-muted text-end">{{ $cell['number'] }}</td>
                <td class="pe-3 text-nowrap" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                    title="{{ $cell['measures'] }}">
                    {{ $cell['name'] }}@if($cell['in_use'])<span class="text-muted"> (in use)</span>@endif
                </td>
                <td class="pe-3 text-end text-nowrap" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                    title="{{ $cell['title'] }}">
                    <span @class(['text-success' => $value > 0, 'text-danger' => $value < 0,
                        'text-muted' => $value === null])>{{ TierApproachComparison::formatValue($value,
                        $cell['unit'] ?? '%') }}{{ !empty($cell['flag']) ? '*' : '' }}</span>
                </td>
                <td class="pe-1">
                    @include('myfinance2::watchlistsymbols.tables.partials.tier-approach-badge', ['full' => true])
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endif
