{{-- Superscript marker flagging a figure that reflects an extended-hours quote.
     Params:
       $session - 'pre', 'post', or null/empty to render nothing. --}}
@if(!empty($session) && in_array($session, ['pre', 'post'], true))
<sup class="text-info"
     data-bs-toggle="tooltip"
     title="{{ $session === 'pre'
         ? 'Includes pre-market trading, before the regular session opens.'
         : 'Includes after-hours trading, after the regular session closed.' }}">&bull;</sup>
@endif
