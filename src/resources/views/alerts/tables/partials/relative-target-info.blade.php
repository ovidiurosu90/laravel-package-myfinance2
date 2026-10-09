{{-- Relative-target line under the target on /price-alerts: the label ("5% below 52W high") with
     the resolved reference in a tooltip, plus a badge when the reference could not be resolved for
     over a day. Expects: $alert (PriceAlert). Renders nothing for a FIXED alert. --}}
@if (!empty($alert) && $alert->isRelative())
    @php
        $referenceTooltip = $alert->getReferenceTooltip();
        $staleTooltip     = 'Not enough stored price history to resolve the reference (e.g. after a stock split);'
            . ' the target shown may be outdated and the alert is skipped until the history is back';
    @endphp
    <div class="text-muted small text-nowrap"
        @if ($referenceTooltip) data-bs-toggle="tooltip" title="{{ $referenceTooltip }}" @endif>
        <i class="fa fa-arrows-v" aria-hidden="true"></i> {{ $alert->getRelativeTargetLabel() }}
    </div>
    @if ($alert->isReferenceStale())
        <span class="badge bg-warning text-dark"
              data-bs-toggle="tooltip"
              title="{{ $staleTooltip }}">
            <i class="fa fa-exclamation-triangle" aria-hidden="true"></i> reference unavailable
        </span>
    @endif
@endif
