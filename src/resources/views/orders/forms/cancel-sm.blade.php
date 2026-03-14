<form action="{{ route('myfinance2::orders.cancel', $id) }}" method="POST"
    accept-charset="utf-8" class="mb-2">
    {{ csrf_field() }}
    <button class="btn w-100 btn-outline-secondary btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#confirm-delete-modal"
        data-title="{{ trans('myfinance2::orders.modals.cancel_modal_title', ['id' => $id]) }}"
        data-message="{{ trans('myfinance2::orders.modals.cancel_modal_message', ['id' => $id]) }}"
        title="{{ trans('myfinance2::orders.tooltips.cancel-order') }}">
        {!! trans('myfinance2::orders.buttons.cancel') !!}
    </button>
</form>
