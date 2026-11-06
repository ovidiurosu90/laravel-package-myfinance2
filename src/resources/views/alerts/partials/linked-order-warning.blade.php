{{-- Edit form of an alert an open order keeps in sync ($linkedOrder, PriceAlert::linkedOrder(); $alertModel: the
     stored alert): every field stays editable, but scripts/linked-order shows this warning as soon as an edit would
     break the link and asks for a confirmation on save. The order's values are what the alert must keep to stay
     linked; the stored ones are what Reset restores. The warning can be dismissed (it comes back when other fields
     break the link). After a redirect back (the server found an unconfirmed break), the confirmation opens on load.
     Opened through the hidden trigger: only Bootstrap's Tooltip is a jQuery plugin in this build, so modals open
     through the data API. Order links open in a new tab, so the edits in this form are kept. --}}
@php
    $linkedOrderLink = '<a href="' . e(route('myfinance2::orders.edit', $linkedOrder->id))
        . '" target="_blank" rel="noopener">'
        . e(trans('myfinance2::alerts.linked-order.order', ['id' => $linkedOrder->id])) . '</a>';
@endphp
<div class="alert alert-warning d-none align-items-center" id="linked-order-warning" role="alert"
    data-expected="{{ json_encode([
        'symbol'       => $linkedOrder->symbol,
        'alert_type'   => $linkedOrder->action === 'BUY' ? 'PRICE_BELOW' : 'PRICE_ABOVE',
        'target_price' => (float) $linkedOrder->limit_price,
    ]) }}"
    data-linked="{{ json_encode([
        'symbol'       => $alertModel->symbol,
        'alert_type'   => $alertModel->alert_type,
        'status'       => $alertModel->status,
        'target_mode'  => $alertModel->target_mode,
        'target_price' => (string) ((float) $alertModel->target_price),
        'expires_at'   => $alertModel->expires_at?->format('Y-m-d H:i:s') ?? '',
    ]) }}"
    {{-- The server's time in the app timezone, the one the expiry field is typed in (scripts/linked-order). --}}
    data-now="{{ now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}"
    data-field-labels="{{ json_encode(trans('myfinance2::alerts.linked-order.fields')) }}"
    data-template="{{ trans('myfinance2::alerts.linked-order.warning') }}"
    data-detail="{{ trans('myfinance2::alerts.linked-order.warning-detail') }}"
    data-order-link="{{ $linkedOrderLink }}">
    <i class="fa fa-exclamation-triangle fa-fw me-2" aria-hidden="true"></i>
    <span class="linked-order-warning-text flex-grow-1"></span>
    <button type="button" class="btn btn-sm btn-outline-dark ms-3 text-nowrap" id="linked-order-reset"
        data-bs-toggle="tooltip" title="{{ trans('myfinance2::alerts.linked-order.reset-tooltip') }}">
        <i class="fa fa-undo fa-fw" aria-hidden="true"></i> {{ trans('myfinance2::alerts.linked-order.reset') }}
    </button>
    <button type="button" class="btn-close ms-3" id="linked-order-dismiss" aria-label="Close"></button>
</div>

<button type="button" class="d-none" id="linked-order-confirm-open" data-bs-toggle="modal"
    data-bs-target="#linked-order-confirm-modal" aria-hidden="true" tabindex="-1"></button>
<div class="modal fade" id="linked-order-confirm-modal" tabindex="-1"
    aria-labelledby="linked-order-confirm-title" aria-hidden="true"
    data-open-on-load="{{ session()->has('unlink_confirm') ? 'true' : 'false' }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="linked-order-confirm-title">
                    <i class="fa fa-unlink fa-fw text-warning" aria-hidden="true"></i>
                    {!! str_replace(':order', $linkedOrderLink,
                        e(trans('myfinance2::alerts.linked-order.confirm-title'))) !!}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body linked-order-warning-text"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                    id="linked-order-confirm-back">
                    {{ trans('myfinance2::alerts.linked-order.confirm-back') }}
                </button>
                <button type="button" class="btn btn-warning" id="linked-order-confirm-accept">
                    {{ trans('myfinance2::alerts.linked-order.confirm-accept') }}
                </button>
            </div>
        </div>
    </div>
</div>
