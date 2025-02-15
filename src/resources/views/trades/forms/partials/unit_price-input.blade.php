<div class="form-group required has-feedback row {{ $errors->has('unit_price') ?
                                                    'has-error' : '' }}">
    <label for="unit_price" class="col-7 control-label">
        {{ trans('myfinance2::trades.forms.item-form.unit_price.label') }}
        <span id="trade_currency-label-tooltip" data-bs-toggle="tooltip"
            title="Trade Currency">
            {!! !empty($tradeCurrencyModel) ? $tradeCurrencyModel->display_code
                                            : '' !!}
        </span>
    </label>
    <div class="col-5 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="fetched-unit-price" style="display: none">
        <span style="cursor: pointer" data-bs-toggle="tooltip" title=""></span>
    </div>
    <div class="col-12">
        <input type="number" step=".0001" id="unit_price" name="unit_price"
            class="form-control"
            value="{{ !empty($unit_price) ? $unit_price + 0 : '' }}"
            placeholder="{{ trans('myfinance2::trades.forms.item-form.unit_price'
                                  . '.placeholder') }}" required />
    </div>
    @if ($errors->has('unit_price'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('unit_price') }}</strong>
            </span>
        </div>
    @endif
</div>

