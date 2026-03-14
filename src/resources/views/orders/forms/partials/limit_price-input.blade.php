<div class="mb-3 has-feedback row {{ $errors->has('limit_price') ? 'has-error' : '' }}">
    <label for="limit_price" class="col-12 control-label">
        {{ trans('myfinance2::orders.forms.item-form.limit_price.label') }}
        <span data-bs-toggle="tooltip" title="Trade Currency">
            {!! !empty($tradeCurrencyModel) ? $tradeCurrencyModel->display_code : '' !!}
        </span>
    </label>
    <div class="col-12">
        <input type="number" step=".0001" id="limit_price" name="limit_price"
            class="form-control"
            value="{{ !empty($limit_price) ? $limit_price + 0 : '' }}"
            placeholder="{{ trans('myfinance2::orders.forms.item-form.limit_price'
                                  . '.placeholder') }}" />
    </div>
    @if ($errors->has('limit_price'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('limit_price') }}</strong>
            </span>
        </div>
    @endif
</div>
