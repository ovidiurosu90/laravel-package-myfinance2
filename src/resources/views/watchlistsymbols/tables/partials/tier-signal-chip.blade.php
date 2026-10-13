{{-- The tier signal chip (TierSignalChip), one look everywhere it shows: the row's Tier History line (next to the
     Chart button), the table's Tier column, the peak-proximity inbox and alerts page (list and the Active view's
     note); not the Portfolio Health card (as the range-bound and TAKE PROFIT chips). The alerts page reads the copy
     the hourly cron stores, which has no severity. $tierSignal: 'signal', 'label', 'action', 'title', 'severity',
     'deferred_to'. Nothing shows when it defers to the range-bound or TAKE PROFIT chip (that chip's tooltip names
     it). A watch-only rare high or
     deterioration has no action and is dimmed; a High severity adds an exclamation mark. $compact shows the action
     only (the label when there is none); the tooltip explains it either way. --}}
@use('ovidiuro\myfinance2\App\Services\SettledTierResolver')
@use('ovidiuro\myfinance2\App\Services\TierSignalSeverity')
@if(!empty($tierSignal) && empty($tierSignal['deferred_to']))
    @php
        [$tsColor, $tsIcon] = match ($tierSignal['signal']) {
            SettledTierResolver::SIGNAL_RARE_HIGH => ['warning', 'fa-caret-up'],
            SettledTierResolver::SIGNAL_RARE_LOW  => ['primary', 'fa-caret-down'],
            default                               => ['danger', 'fa-arrow-trend-down'],
        };
        $tsAction = $tierSignal['action'] ?? null;
        // The words before the action: the label alone without one, "Label: " in full, nothing when compact.
        $tsLabel  = $tsAction === null ? $tierSignal['label'] : (empty($compact) ? $tierSignal['label'] . ': ' : '');
        $tsHigh   = ($tierSignal['severity'] ?? null) === TierSignalSeverity::HIGH;
    @endphp
    <span class="badge bg-{{ $tsColor }}-subtle text-{{ $tsColor }}-emphasis border border-{{ $tsColor }} fw-normal
        text-nowrap{{ $tsAction === null ? ' opacity-50' : '' }}"
        data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $tierSignal['title'] }}"><i
        class="fas {{ $tsIcon }} fa-xs me-1" aria-hidden="true"></i>{{ $tsLabel }}{{--
        --}}@if($tsAction !== null)<span class="fw-semibold">{{ $tsAction }}@if($tsHigh)<i{{--
        --}} class="fas fa-circle-exclamation fa-xs ms-1" aria-hidden="true"></i>@endif</span>@endif</span>
@endif
