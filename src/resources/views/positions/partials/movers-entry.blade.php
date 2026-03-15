@php
    $isLoss = $mover['gain_eur'] < 0;
    $colorClass = $isLoss ? 'text-danger' : 'text-success';
    $gainSign = $isLoss ? '- ' : '+ ';
    $gainAbs = number_format(abs($mover['gain_eur']), 2);
    $pctSign = $mover['gain_percentage'] < 0 ? '- ' : '+ ';
    $pctAbs = number_format(abs($mover['gain_percentage']), 2);
@endphp
<div class="d-flex justify-content-between align-items-start mb-2">
    <div>
        <span class="fw-semibold">{{ $mover['symbol'] }}</span>
        @if(!empty($mover['inception_label']))
            <small class="text-muted ms-1">{{ $mover['inception_label'] }}</small>
        @endif
    </div>
    <div class="text-end ms-2">
        <div class="{{ $colorClass }} fw-semibold" style="white-space: nowrap;">
            {{ $gainSign }}{{ $gainAbs }} &euro;
        </div>
        <small class="{{ $colorClass }}">{{ $pctSign }}{{ $pctAbs }} %</small>
    </div>
</div>
