<form action="{{ route('myfinance2::price-alerts.resume', $id) }}" method="POST"
    accept-charset="utf-8"
    data-bs-toggle="tooltip"
    title="{{ trans('myfinance2::alerts.tooltips.resume-alert') }}">
    {{ csrf_field() }}
    <button class="btn w-100 btn-outline-success btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#confirm-resume-modal"
        data-title="{!! trans('myfinance2::alerts.modals.resume_modal_title', ['id' => $id]) !!}"
        data-message="{!! trans('myfinance2::alerts.modals.resume_modal_message', ['id' => $id]) !!}">
        {!! trans('myfinance2::alerts.buttons.resume') !!}
    </button>
</form>
