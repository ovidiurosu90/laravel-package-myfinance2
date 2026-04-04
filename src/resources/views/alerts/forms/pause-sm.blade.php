<form action="{{ route('myfinance2::price-alerts.pause', $id) }}" method="POST"
    accept-charset="utf-8"
    data-bs-toggle="tooltip"
    title="{{ trans('myfinance2::alerts.tooltips.pause-alert') }}">
    {{ csrf_field() }}
    <button class="btn w-100 btn-outline-warning btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#confirm-pause-modal"
        data-title="{!! trans('myfinance2::alerts.modals.pause_modal_title', ['id' => $id]) !!}"
        data-message="{!! trans('myfinance2::alerts.modals.pause_modal_message', ['id' => $id]) !!}">
        {!! trans('myfinance2::alerts.buttons.pause') !!}
    </button>
</form>
