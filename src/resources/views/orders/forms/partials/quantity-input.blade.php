<div class="mb-3 has-feedback row {{ $errors->has('quantity') ? 'has-error' : '' }}">
    <label for="quantity-input" class="col-12 control-label">
        {{ trans('myfinance2::orders.forms.item-form.quantity.label') }}
    </label>
    <div class="col-12">
        <input type="number" step=".00000001" min="0" id="quantity-input"
            name="quantity" class="form-control"
            value="{{ !empty($quantity) ? $quantity + 0 : '' }}"
            placeholder="{{ trans('myfinance2::orders.forms.item-form.quantity'
                                  . '.placeholder') }}" />
    </div>
    @if ($errors->has('quantity'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('quantity') }}</strong>
            </span>
        </div>
    @endif
</div>
