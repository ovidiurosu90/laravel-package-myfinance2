<form action="{{ route('myfinance2::orders.expire', $id) }}" method="POST"
    accept-charset="utf-8" class="mb-2">
    {{ csrf_field() }}
    <button class="btn w-100 btn-outline-warning btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#confirm-delete-modal"
        data-title="{{ trans('myfinance2::orders.modals.expire_modal_title', ['id' => $id]) }}"
        data-message="{{ trans('myfinance2::orders.modals.expire_modal_message', ['id' => $id]) }}"
        title="{{ trans('myfinance2::orders.tooltips.expire-order') }}">
        {!! trans('myfinance2::orders.buttons.expire') !!}
    </button>
</form>
