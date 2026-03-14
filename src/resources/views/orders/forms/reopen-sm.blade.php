<form action="{{ route('myfinance2::orders.reopen', $id) }}" method="POST"
    accept-charset="utf-8" class="mb-2">
    {{ csrf_field() }}
    <button class="btn w-100 btn-outline-secondary btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#confirm-delete-modal"
        data-title="{{ trans('myfinance2::orders.modals.reopen_modal_title', ['id' => $id]) }}"
        data-message="{{ trans('myfinance2::orders.modals.reopen_modal_message', ['id' => $id]) }}"
        title="{{ trans('myfinance2::orders.tooltips.reopen-order') }}">
        {!! trans('myfinance2::orders.buttons.reopen') !!}
    </button>
</form>
