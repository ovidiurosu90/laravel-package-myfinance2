{{-- The trade list's Tier (at the decision) cell: the saved reading (tier-reading-compact), "rebuilt from history"
     beneath a rebuilt one. A reading shared with the order the trade filled needs no note: the order number is under
     the trade id. $trade: the Trade. --}}
@php
    use ovidiuro\myfinance2\App\Models\TierSnapshot;
    use ovidiuro\myfinance2\App\Services\TierVerdictPresenter;

    $tierSnap = $trade->tierSnapshot;
    $tierNote = $tierSnap?->source === TierSnapshot::SOURCE_REBUILT
        ? '<span class="fst-italic">rebuilt from history</span>' : null;
@endphp
<td class="text-start" data-order="{{ $tierSnap !== null ? TierVerdictPresenter::sortValue($tierSnap) : -1 }}">
    @include('myfinance2::watchlistsymbols.tables.partials.tier-reading-compact', [
        'snapshot'   => $tierSnap,
        'verdictTip' => null,
        'tierExtra'  => null,
        'tierNote'   => $tierNote,
    ])
</td>
