<form action="{{ route('myfinance2::orders.place', $id) }}" method="POST"
    accept-charset="utf-8" class="mb-2">
    {{ csrf_field() }}
    <button class="btn w-100 btn-outline-primary btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#confirm-delete-modal"
        data-title="{{ trans('myfinance2::orders.modals.place_modal_title', ['id' => $id]) }}"
        data-message="{{ trans('myfinance2::orders.modals.place_modal_message', ['id' => $id]) }}"
        data-bs-toggle="tooltip"
        title="{{ trans('myfinance2::orders.tooltips.place-order') }}">
        {!! trans('myfinance2::orders.buttons.place') !!}
    </button>
</form>
