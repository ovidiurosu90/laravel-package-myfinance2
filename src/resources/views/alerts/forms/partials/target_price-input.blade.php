<div class="mb-3 required has-feedback row {{ $errors->has('target_price') ? 'has-error' : '' }}">
    <label for="target_price" class="col-12 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.target_price.label') }}
        <span id="trade_currency-label-tooltip" class="text-muted small">&curren;</span>
    </label>
    <div class="col-12">
        <input type="number" step="any" name="target_price" id="target_price"
            class="form-control"
            placeholder="{{ trans('myfinance2::alerts.forms.item-form.target_price.placeholder') }}"
            value="{{ old('target_price', $target_price) }}"
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
