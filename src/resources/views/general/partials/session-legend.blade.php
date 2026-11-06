{{-- One-line explanation of the extended-hours marker, rendered once per table
     and only when that table actually holds pre-market or after-hours figures.
     Params:
       $hasPreSession       - true when any row carries a pre-market quote
       $hasPostSession      - true when any row carries an after-hours quote
       $hasOvernightSession - true when any of the pre-market quotes come from the
                              overnight session (OvernightSession) --}}
@if(!empty($hasPreSession) || !empty($hasPostSession))
@php
    $preLegend = !empty($hasOvernightSession) ? 'overnight or pre-market' : 'pre-market';
    if (!empty($hasPreSession) && !empty($hasPostSession)) {
        $sessionLegendText = 'includes extended-hours trading'
            . ' (' . $preLegend . ' or after hours)';
    } elseif (!empty($hasPreSession)) {
        $sessionLegendText = 'includes ' . $preLegend . ' trading,'
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
