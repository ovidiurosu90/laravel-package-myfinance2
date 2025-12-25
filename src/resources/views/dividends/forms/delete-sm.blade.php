<form action="{{ route('myfinance2::dividends.destroy', $id) }}" method="POST" accept-charset="utf-8" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.delete-item', ['type' => $type]) }}">
    {{ csrf_field() }}
    {{ method_field('DELETE') }}
    <button class="btn w-100 btn-outline-danger btn-sm" type="button" style="width: 100%;" data-bs-toggle="modal" data-bs-target="#confirm-delete-modal" data-title="{!! trans('myfinance2::general.modals.delete_modal_title', ['type' => $type, 'id' => $id]) !!}" data-message="{!! trans('myfinance2::general.modals.delete_modal_message', ['type' => $type, 'id' => $id]) !!}" >
        {!! trans('myfinance2::general.buttons.delete') !!}
    </button>
</form>

