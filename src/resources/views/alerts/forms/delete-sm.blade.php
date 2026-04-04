<form action="{{ route('myfinance2::price-alerts.destroy', $id) }}" method="POST"
    accept-charset="utf-8"
    data-bs-toggle="tooltip"
    title="{{ trans('myfinance2::general.tooltips.delete-item', ['type' => 'Price Alert']) }}">
    {{ csrf_field() }}
    {{ method_field('DELETE') }}
    <button class="btn w-100 btn-outline-danger btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#confirm-delete-modal"
        data-title="{!! trans('myfinance2::general.modals.delete_modal_title',
            ['type' => 'Price Alert', 'id' => $id]) !!}"
        data-message="{!! trans('myfinance2::general.modals.delete_modal_message',
            ['type' => 'Price Alert', 'id' => $id]) !!}">
        {!! trans('myfinance2::general.buttons.delete') !!}
    </button>
</form>
