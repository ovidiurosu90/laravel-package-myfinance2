{{-- The order's tier warnings: the serious ones (a confirmation on save) in red, the soft ones in amber.
     $serious, $soft: OrderTierWarnings warnings; $isEdit: the edit form; $isRecheck: warnings the edit adds to the
     saved ones. --}}
@php
    $what  = $action . ' ' . $symbol;
    $edits = $isRecheck ? ' with these changes' : '';
    // Under a serious banner the soft ones are extras; alone they get their own count.
    $softHeading = !empty($serious) ? 'Also worth a look'
        : count($soft) . ' ' . (count($soft) === 1 ? 'soft warning' : 'soft warnings') . $edits . ' for ' . $what;
@endphp
@if(!empty($serious))
<div class="alert alert-danger mb-2" role="alert">
    <strong><i class="fa fa-exclamation-triangle fa-fw" aria-hidden="true"></i>
        {{ count($serious) }} {{ count($serious) === 1 ? 'serious warning' : 'serious warnings' }}{{ $edits }}
        for {{ $what }}: are you sure this is a good idea?</strong>
    <ul class="mb-1 mt-2">
        @foreach($serious as $warning)
            <li><strong>{{ $warning['label'] }}:</strong> {{ $warning['text'] }}</li>
        @endforeach
    </ul>
    <small>Saving will ask you to confirm. If the order is already placed at the broker, you can still cancel it
        there.</small>
</div>
@endif
@if(!empty($soft))
<div class="alert alert-warning mb-2" role="alert">
    <strong>{{ $softHeading }}</strong>
    <ul class="mb-0 mt-2">
        @foreach($soft as $warning)
            <li><strong>{{ $warning['label'] }}:</strong> {{ $warning['text'] }}</li>
        @endforeach
    </ul>
</div>
@endif
