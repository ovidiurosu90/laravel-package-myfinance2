{{--
    The symbol's tier under each approach (TierApproachCatalog), inline on the Tier line (tier-reading): one tier
    initial per approach, the one in use as the tier itself (full name and figure, outlined). Each badge's tooltip
    names the approach, its figure and how it was reached. Market Snapshot (today's tier) is also shown in full with a
    dotted silver outline (the one in use has a solid blue one), so the two read side by side. The "details" toggle
    opens tier-approaches-table below the line. $cells is the reading's 'cells' (TierReadingBuilder::cells()), in
    display order; $detailsId the table's id.
--}}
@php
    use ovidiuro\myfinance2\App\Services\TierApproachComparison;
@endphp
@if(!empty($cells))
    @foreach($cells as $cell)
        @php
            $tier  = $cell['tier'] ?? null;
            $value = TierApproachComparison::formatShort($cell['value'] ?? null, $cell['unit'] ?? '%')
                . (!empty($cell['flag']) ? '*' : '');
        @endphp
        @if($cell['in_use'] || $cell['is_today'])
            {{-- In use: solid blue outline; today (Market Snapshot): dotted silver outline. --}}
            <span @class(['text-nowrap border rounded px-1', 'border-primary' => $cell['in_use'],
                'border-secondary' => !$cell['in_use']])
                @if(!$cell['in_use']) style="border-style:dotted!important;" @endif
                data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $cell['tip'] }}">
                @include('myfinance2::watchlistsymbols.tables.partials.tier-approach-badge', ['full' => true])
                <span @class(['text-success' => ($cell['value'] ?? 0) > 0,
                    'text-danger' => ($cell['value'] ?? 0) < 0])>{{ $value }}</span>
            </span>
        @else
            <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $cell['tip'] }}">
                @include('myfinance2::watchlistsymbols.tables.partials.tier-approach-badge', ['full' => false])
            </span>
        @endif
    @endforeach
    <a class="tier-approaches-toggle text-decoration-none text-nowrap collapsed" data-bs-toggle="collapse"
        href="#{{ $detailsId }}" role="button" aria-expanded="false" aria-controls="{{ $detailsId }}"><i
        class="fas fa-chevron-down fa-xs me-1" aria-hidden="true"></i>details</a>
@endif
