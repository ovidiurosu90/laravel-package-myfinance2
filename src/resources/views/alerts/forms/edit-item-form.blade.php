<form action="{{ route('myfinance2::price-alerts.update', $id) }}" method="POST"
    accept-charset="utf-8" class="mb-0 needs-validation" role="form">
    {{ method_field('PUT') }}
    <div class="card-body">
        @include('myfinance2::alerts.forms.item-form')
    </div>
    <div class="card-footer">
        <div class="row">
            <div class="col-md-6">
                <span data-bs-toggle="tooltip"
                    title="{!! trans('myfinance2::general.tooltips.save-item', ['type' => 'Alert']) !!}">
                    <button type="submit" class="btn btn-success btn-lg w-100"
                        value="save" name="form_action">
                        <i class="fa fa-save fa-fw">
                            <span class="sr-only">
                                {!! trans('myfinance2::alerts.forms.item-form.buttons.update-item.sr-icon') !!}
                            </span>
                        </i>
                        {!! trans('myfinance2::alerts.forms.item-form.buttons.update-item.name') !!}
                    </button>
                </span>
            </div>
        </div>
    </div>
</form>
