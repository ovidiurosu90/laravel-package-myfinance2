{{-- Superscript marker flagging a figure that reflects an extended-hours quote.
     Params:
       $session - 'overnight', 'pre', 'post', or null/empty to render nothing. --}}
@php
    $sessionMarkerTitles = [
        'overnight' => 'Includes overnight trading (20:00 to 04:00 New York time), before pre-market.',
        'pre'       => 'Includes pre-market trading, before the regular session opens.',
        'post'      => 'Includes after-hours trading, after the regular session closed.',
    ];
@endphp
@if(!empty($session) && isset($sessionMarkerTitles[$session]))
<sup class="text-info"
     data-bs-toggle="tooltip"
     title="{{ $sessionMarkerTitles[$session] }}">&bull;</sup>
@endif
