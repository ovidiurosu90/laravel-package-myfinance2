<button class="btn w-100 btn-outline-warning btn-sm mb-2" type="button"
    data-bs-toggle="modal" data-bs-target="#expire-order-modal"
    data-order-id="{{ $id }}"
    data-order-symbol="{{ $item->symbol }}"
    data-order-action="{{ $item->action }}"
    data-order-price="{{ $item->limit_price }}"
    data-order-currency="{{ $item->trade_currency_id }}"
    title="{{ trans('myfinance2::orders.tooltips.expire-order') }}">
    {!! trans('myfinance2::orders.buttons.expire') !!}
</button>
