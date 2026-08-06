{{-- One-line explanation of the extended-hours marker, rendered once per table
     and only when that table actually holds pre-market or after-hours figures.
     Params:
       $hasPreSession  - true when any row carries a pre-market quote
       $hasPostSession - true when any row carries an after-hours quote --}}
@if(!empty($hasPreSession) || !empty($hasPostSession))
@php
    if (!empty($hasPreSession) && !empty($hasPostSession)) {
        $sessionLegendText = 'includes extended-hours trading'
            . ' (pre-market or after hours)';
    } elseif (!empty($hasPreSession)) {
        $sessionLegendText = 'includes pre-market trading,'
            . ' before the regular session opens';
    } else {
        $sessionLegendText = 'includes after-hours trading,'
            . ' after the regular session closed';
    }
@endphp
<div class="small text-secondary mt-1 ms-1">
    <sup class="text-info">&bull;</sup> {{ $sessionLegendText }}
</div>
@endif
