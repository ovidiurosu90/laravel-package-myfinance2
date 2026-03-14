<div class="mb-2">
    <button class="btn w-100 btn-outline-info btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#link-trade-modal"
        data-order-id="{{ $id }}"
        data-order-label="{{ $label ?? '' }}"
        title="{{ trans('myfinance2::orders.tooltips.link-trade') }}">
        {!! trans('myfinance2::orders.buttons.link-trade') !!}
    </button>
</div>
