<form action="{{ route('myfinance2::trades.close', $id) }}" method="POST"
    accept-charset="utf-8" data-bs-toggle="tooltip"
    title="{{ trans('myfinance2::trades.tooltips.close-trade') }}">
    {{ csrf_field() }}
    {{ method_field('PATCH') }}
    <button class="btn w-100 btn-outline-success btn-sm" type="button"
        style="width: 100%;" data-bs-toggle="modal"
        data-bs-target="#confirm-delete-modal"
        data-title="{!! trans('myfinance2::trades.modals.close_modal_title',
                              ['id' => $id]) !!}"
        data-message="{!! trans('myfinance2::trades.modals.close_modal_message',
                                ['id' => $id]) !!}" >
        {!! trans('myfinance2::trades.buttons.close-trade') !!}
    </button>
</form>

