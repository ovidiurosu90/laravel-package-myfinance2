@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
@php use ovidiuro\myfinance2\App\Services\TechnicalIndicatorTooltips; @endphp
@php
    $ind     = $indicators;
    $tt      = TechnicalIndicatorTooltips::build($ind);
    $signals = $ind['signals'] ?? [];

    $signalClass = ['buy' => 'text-success', 'hold' => 'text-muted', 'sell' => 'text-danger'];
@endphp
<span class="text-muted me-1"
      data-bs-toggle="tooltip"
      data-bs-custom-class="tooltip-wide"
      title="{{ $tt['label'] }}">Indicators:</span>

@if (($ind['analyst_target_price'] ?? null) !== null && ($ind['analyst_target_delta_pct'] ?? null) !== null)
@php
    $analystDelta = $ind['analyst_target_delta_pct'];
    if ($analystDelta >= 15)    { $analystClass = 'text-success'; }
    elseif ($analystDelta >= 0) { $analystClass = 'text-info'; }
    else                        { $analystClass = 'text-danger'; }
@endphp
<span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $tt['analyst'] }}">
    <span class="text-muted">Analyst:</span>
    <strong class="{{ $analystClass }}">{{ $analystDelta >= 0 ? '+' : '' }}{{ MoneyFormat::get_formatted_number_plain($analystDelta, 1) }}%</strong>
    @if (!empty($signals['analyst']))
    <span class="{{ $signalClass[$signals['analyst']] ?? '' }}">{{ $signals['analyst'] }}</span>
    @endif
</span>
@endif

@if (($ind['rsi'] ?? null) !== null)
@php
    $rsi = $ind['rsi'];
    $rsiClass = $rsi < 30 ? 'text-danger' : ($rsi > 70 ? 'text-warning' : '');
@endphp
<span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $tt['rsi'] }}">
    <span class="text-muted">RSI-14:</span>
    <strong class="{{ $rsiClass }}">{{ $rsi }}</strong>
    @if (!empty($signals['rsi']))
    <span class="{{ $signalClass[$signals['rsi']] ?? '' }}">{{ $signals['rsi'] }}</span>
    @endif
</span>
@endif

@if (($ind['ma50'] ?? null) !== null && ($ind['ma50_diff_pct'] ?? null) !== null)
@php
    $ma50Diff  = $ind['ma50_diff_pct'];
    $ma50Class = $ma50Diff >= 0 ? 'text-success' : 'text-danger';
@endphp
<span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $tt['ma50'] }}">
    <span class="text-muted">MA50:</span>
    <strong class="{{ $ma50Class }}">{{ $ma50Diff >= 0 ? '+' : '' }}{{ MoneyFormat::get_formatted_number_plain($ma50Diff, 1) }}%</strong>
    @if (!empty($signals['ma50']))
    <span class="{{ $signalClass[$signals['ma50']] ?? '' }}">{{ $signals['ma50'] }}</span>
    @endif
</span>
@endif

@if (($ind['ma200'] ?? null) !== null && ($ind['ma200_diff_pct'] ?? null) !== null)
@php
    $ma200Diff  = $ind['ma200_diff_pct'];
    $ma200Class = $ma200Diff >= 0 ? 'text-success' : 'text-danger';
@endphp
<span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $tt['ma200'] }}">
    <span class="text-muted">MA200:</span>
    <strong class="{{ $ma200Class }}">{{ $ma200Diff >= 0 ? '+' : '' }}{{ MoneyFormat::get_formatted_number_plain($ma200Diff, 1) }}%</strong>
    @if (!empty($signals['ma200']))
    <span class="{{ $signalClass[$signals['ma200']] ?? '' }}">{{ $signals['ma200'] }}</span>
    @endif
</span>
@endif
