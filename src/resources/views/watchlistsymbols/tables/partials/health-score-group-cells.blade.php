{{-- One tier group's five cells in a health card metric row (mvalue or cost): amount, share, progress bar with
     the target marker, target share and target amount. --}}
@php
    use ovidiuro\myfinance2\App\Services\MoneyFormat;

    $weight = $bold ? ' fw-semibold' : '';
@endphp
<td class="text-end px-1{{ $weight }}">{!! MoneyFormat::get_formatted_price_display('&euro;', $amount) !!}</td>
<td class="text-end px-1{{ $weight }}">{{ $pct }}%</td>
<td class="px-1" style="width:33%">
    <div class="progress position-relative" style="height:8px;border:1px solid rgba(0,0,0,0.15);">
        <div class="progress-bar {{ $barClass }}" role="progressbar" style="width: {{ min($pct, 100) }}%"></div>
        <div class="position-absolute top-0 bottom-0"
            style="left:{{ $target }}%;width:2px;background:rgba(0,0,0,0.25);"></div>
    </div>
</td>
<td class="px-1"><span class="text-muted">{{ MoneyFormat::get_formatted_number_plain($target, 1) }}%</span></td>
<td class="px-1{{ $last ? '' : ' border-end' }}">
    <span class="text-muted">{!! MoneyFormat::get_formatted_price_display('&euro;', $targetEur) !!}</span>
</td>
