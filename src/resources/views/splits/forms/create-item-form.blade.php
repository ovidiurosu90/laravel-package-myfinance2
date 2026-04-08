<form action="{{ route('myfinance2::stock-splits.store') }}" method="POST"
    accept-charset="utf-8" class="mb-0 needs-validation" role="form">
    {{ method_field('POST') }}
    <div class="card-body">
        <div class="alert alert-warning mb-3">
            <i class="fa fa-fw fa-exclamation-triangle" aria-hidden="true"></i>
            <strong>Irreversible action:</strong>
            Recording a split will immediately update <strong>all your open trades and active alerts</strong>
            for this symbol.
        </div>
        @include('myfinance2::splits.forms.item-form')
    </div>
    <div class="card-footer">
        <span data-bs-toggle="tooltip"
            title="{!! trans('myfinance2::general.tooltips.save-item', ['type' => 'Split']) !!}">
            <button type="submit" class="btn btn-success btn-lg w-100"
                value="save" name="form_action">
                <i class="fa fa-scissors fa-fw">
                    <span class="sr-only">
                        {!! trans('myfinance2::splits.forms.item-form.buttons.save-item.sr-icon') !!}
                    </span>
                </i>
                {!! trans('myfinance2::splits.forms.item-form.buttons.save-item.name') !!}
            </button>
        </span>
    </div>
</form>
