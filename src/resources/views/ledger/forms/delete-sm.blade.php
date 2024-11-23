<form action="{{ route('myfinance2::ledger-transactions.destroy', $id) }}" method="POST" accept-charset="utf-8" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.delete-item', ['type' => 'Ledger Transaction']) }}">
    {{ csrf_field() }}
    {{ method_field('DELETE') }}
    <button class="btn btn-block btn-outline-danger btn-sm" type="button" style="width: 100%;" data-bs-toggle="modal" data-bs-target="#confirmDelete" data-title="{!! trans('myfinance2::general.modals.delete_modal_title', ['type' => $type, 'id' => $id]) !!}" data-message="{!! trans('myfinance2::general.modals.delete_modal_message', ['type' => $type, 'id' => $id]) !!}" >
        {!! trans('myfinance2::general.buttons.delete') !!}
    </button>
</form>

