{{-- The warnings saved with the order (TierSnapshot), in a gray box on the edit form: what the decision was judged
     against when it was made. $snapshot: the order's TierSnapshot. --}}
@php
    use ovidiuro\myfinance2\App\Services\OrderTierWarnings;

    $saved   = $snapshot->warnings ?? [];
    $serious = OrderTierWarnings::serious($saved);
    $when    = $snapshot->evaluated_at?->format('Y-m-d H:i');
@endphp
<div class="alert alert-secondary mb-2" role="status">
    <strong>
        @if(!empty($serious))
            <i class="fa fa-times fa-fw text-danger" aria-hidden="true"></i>
            Created against {{ count($serious) }}
            {{ count($serious) === 1 ? 'serious warning' : 'serious warnings' }}@if($snapshot->acknowledged_at),
                confirmed on save ({{ $snapshot->acknowledged_at->format('Y-m-d H:i') }})@endif
        @elseif(!empty($saved))
            <i class="fa fa-exclamation-circle fa-fw text-warning" aria-hidden="true"></i>
            Created with {{ count($saved) }} {{ count($saved) === 1 ? 'soft warning' : 'soft warnings' }}
            ({{ $when }})
        @else
            <i class="fa fa-check fa-fw text-success" aria-hidden="true"></i>
            Created with no tier warning ({{ $when }})
        @endif
    </strong>
    @if(!empty($saved))
    <ul class="mb-0 mt-1">
        @foreach($saved as $warning)
            <li>{{ $warning['label'] }}: {{ $warning['text'] }}@if($warning['level'] === OrderTierWarnings::LEVEL_SOFT)
                <span class="text-muted">(soft)</span>@endif</li>
        @endforeach
    </ul>
    @endif
</div>
