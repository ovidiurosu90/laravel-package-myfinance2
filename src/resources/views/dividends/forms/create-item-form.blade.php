<form action="{{ route('myfinance2::dividends.store') }}" method="POST" accept-charset="utf-8" class="mb-0 needs-validation" enctype="multipart/form-data" role="form" >
    {{ method_field('POST') }}
    <div class="card-body">
        @include('myfinance2::dividends.forms.item-form')
    </div>
    <div class="card-footer">
        <div class="row ">
            <div class="col-md-6">
                <span data-bs-toggle="tooltip" title="{!! trans('myfinance2::general.tooltips.save-item', ['type' => 'Dividend']) !!}">
                    <button type="submit" class="btn btn-success btn-lg btn-block" value="save" name="form_action">
                        <i class="fa fa-save fa-fw">
                            <span class="sr-only">
                                 {!! trans('myfinance2::dividends.forms.item-form.buttons.save-item.sr-icon') !!}
                            </span>
                        </i>
                        {!! trans('myfinance2::dividends.forms.item-form.buttons.save-item.name') !!}
                    </button>
                </span>
            </div>
        </div>
    </div>
</form>

