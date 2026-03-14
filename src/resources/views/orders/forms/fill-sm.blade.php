@if (!empty($trade_id))
<form method="POST" action="{{ route('myfinance2::orders.fill', $id) }}" class="mb-2">
    @csrf
    <button class="btn w-100 btn-outline-success btn-sm" type="submit"
        title="{{ trans('myfinance2::orders.tooltips.fill-order') }}">
        {!! trans('myfinance2::orders.buttons.fill') !!}
    </button>
</form>
@else
<div class="mb-2">
    <button class="btn w-100 btn-outline-success btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#fill-order-modal"
        data-order-id="{{ $id }}"
        data-order-label="{{ $label ?? '' }}"
        title="{{ trans('myfinance2::orders.tooltips.fill-order') }}">
        {!! trans('myfinance2::orders.buttons.fill') !!}
    </button>
</div>
@endif
