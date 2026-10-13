{{-- The record of which approach drives the allocation and why (TierApproachCatalog::DECISION), above the
     approach comparison: the choice and its main reason on two lines, the full reasoning in the (i) tooltip. --}}
@php
    $chosen     = $approaches[$decision['approach']] ?? null;
    $runnerUp   = $approaches[$decision['runner_up']] ?? null;
    $detailsTip = '<div class="fw-semibold">Why</div><ul class="mb-1 ps-3">';
    foreach ($decision['why'] as $reason) {
        $detailsTip .= '<li>' . e($reason) . '</li>';
    }
    $detailsTip .= '</ul><div><span class="fw-semibold">Known risk:</span> ' . e($decision['risk']) . '</div>'
        . '<div class="mt-1">' . e($decision['note']) . '</div>';
@endphp
<div class="small border rounded px-2 py-1 mb-2" style="line-height:1.5;background-color:rgba(13,110,253,0.04)">
    <div>
        <span class="badge bg-primary me-1">Decision {{ $decision['date'] }}</span>
        <span class="fw-semibold">{{ $chosen['name'] ?? '' }} drives this card</span>
        (approach {{ $chosen['number'] ?? '' }}): {{ $decision['reason'] }}
        <i class="fa fa-info-circle text-muted" aria-hidden="true" data-bs-toggle="tooltip"
            data-bs-custom-class="tooltip-wide" data-bs-html="true" title="{{ $detailsTip }}"></i>
    </div>
    @if($runnerUp !== null)
    <div class="text-muted">
        Runner-up: {{ $runnerUp['name'] }} (approach {{ $runnerUp['number'] }}). {{ $decision['revisit'] }}
    </div>
    @endif
</div>
