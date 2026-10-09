@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    $isLoss = $mover['gain_eur'] < 0;
    $colorClass = $isLoss ? 'text-danger' : 'text-success';
    $gainSign = $isLoss ? '- ' : '+ ';
    $pctSign = $mover['gain_percentage'] < 0 ? '- ' : '+ ';
    // "since May '21" -> the "since " prefix is only shown on xl+, the date always
    $inceptionLabel = $mover['inception_label'] ?? '';
    $sincePrefix = str_starts_with($inceptionLabel, 'since ') ? 'since ' : '';
    $inceptionDate = substr($inceptionLabel, strlen($sincePrefix));
@endphp
{{-- One line per symbol: the inception label truncates when the column is narrow (full text on hover);
     the "since" word and the % are only shown on xl+ (same breakpoint as the collapsed summary strip's %). --}}
<div class="d-flex justify-content-between align-items-baseline mb-1 text-nowrap">
    <div class="text-truncate me-2" style="min-width: 0;"
        @if(!empty($mover['inception_label']) && empty($mover['inception_tooltip']))
            title="{{ $mover['symbol'] }} {{ $mover['inception_label'] }}"
        @endif>
        <span class="fw-semibold">{{ $mover['symbol'] }}</span>
        @if(!empty($mover['inception_label']))
            <small class="text-muted ms-1">@if($sincePrefix !== '')<span
                class="d-none d-xl-inline">{{ $sincePrefix }}</span>@endif{{ $inceptionDate }}
                @if(!empty($mover['inception_tooltip']))
                    <span data-bs-toggle="tooltip" data-bs-placement="top"
                        data-bs-title="{{ $mover['inception_tooltip'] }}">&#9432;</span>
                @endif
            </small>
        @endif
    </div>
    <div class="flex-shrink-0 {{ $colorClass }}">
        <span class="fw-semibold">
            {{ $gainSign }}{!! MoneyFormat::get_formatted_price_display('&euro;', abs($mover['gain_eur'])) !!}
        </span>
        <small class="d-none d-xl-inline ms-1">
            {{ $pctSign }}{{ MoneyFormat::get_formatted_pct(abs($mover['gain_percentage'])) }} %
        </small>
    </div>
</div>
