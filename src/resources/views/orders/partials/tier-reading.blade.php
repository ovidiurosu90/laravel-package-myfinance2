{{--
    The tier block at the top of the order form (OrderTierReadingController): the warnings, then the tier reading.
    $check  OrderTierCheck result for the form's values (null: an order without a saved reading, unchanged decision)
    $kept   the order's TierSnapshot when its reading is kept (edit, same symbol and action), else null
    $now    on a kept reading, today's live check (the "now" chip and the note)
    $order  the order being edited, or null
--}}
@php
    use ovidiuro\myfinance2\App\Services\OrderTierWarnings;

    $warnings = $check['warnings'] ?? [];
    if ($kept !== null) {
        // The saved warnings are listed in their own box; the banner shows only what the edit adds.
        $savedCodes = array_column($kept->warnings ?? [], 'code');
        $warnings   = array_values(array_filter($warnings, fn ($w) => !in_array($w['code'], $savedCodes, true)));
    }
@endphp
@if($check === null)
    <div class="border rounded px-3 py-2 mb-3 text-muted small">
        <i class="fa fa-lock fa-fw" aria-hidden="true"></i>
        No tier reading was saved with this order: it was created before readings were saved. Changing the symbol or
        action takes one.
    </div>
@else
    @if($kept !== null)
        @include('myfinance2::orders.partials.tier-saved-warnings', ['snapshot' => $kept])
        @include('myfinance2::orders.partials.tier-since-note', ['snapshot' => $kept, 'now' => $now])
    @endif
    @include('myfinance2::orders.partials.tier-warnings-banner', [
        'serious' => OrderTierWarnings::serious($warnings),
        'soft'    => OrderTierWarnings::soft($warnings),
        'isEdit'  => $order !== null,
        'isRecheck' => $kept !== null,
    ])
    @include('myfinance2::orders.partials.tier-reading-panel')
@endif
