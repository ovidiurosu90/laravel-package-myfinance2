<div class="mb-3 required has-feedback row {{ $errors->has('target_price') ? 'has-error' : '' }}">
    <label for="target_price" class="col-7 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.target_price.label') }}
        <span id="trade_currency-label-tooltip" class="text-muted small">&curren;</span>
    </label>
    <div class="col-5 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="fetched-target-price" style="display: none">
        <span style="cursor: pointer" data-bs-toggle="tooltip" title=""></span>
    </div>
    <div class="col-12">
        <input type="number" step="any" name="target_price" id="target_price"
            class="form-control"
            placeholder="{{ trans('myfinance2::alerts.forms.item-form.target_price.placeholder') }}"
            value="{{ old('target_price', !empty($target_price) ? $target_price + 0 : '') }}"
            required>
    </div>
    @if ($errors->has('target_price'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('target_price') }}</strong>
            </span>
        </div>
    @endif
</div>
