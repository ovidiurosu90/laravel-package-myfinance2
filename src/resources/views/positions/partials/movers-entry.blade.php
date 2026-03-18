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
            <br><small class="text-muted">{{ $mover['inception_label'] }}
                @if(!empty($mover['inception_tooltip']))
                    <span data-bs-toggle="tooltip" data-bs-placement="top"
                        data-bs-title="{{ $mover['inception_tooltip'] }}"> &#9432;</span>
                @endif
            </small>
        @endif
    </div>
    <div class="text-end ms-2">
        <div class="{{ $colorClass }} fw-semibold" style="white-space: nowrap;">
            {{ $gainSign }}{{ $gainAbs }} &euro;
        </div>
        <small class="{{ $colorClass }}">{{ $pctSign }}{{ $pctAbs }} %</small>
    </div>
</div>
