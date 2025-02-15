<div class="form-group required has-feedback row {{ $errors->has('quantity')
                                                    ? 'has-error' : '' }}">
    <label for="quantity" class="col-5 control-label">
        {{ trans('myfinance2::trades.forms.item-form.quantity.label') }}
    </label>
    <div class="col-7 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="available-quantity" style="display: none">
        Available: <span></span>
    </div>
    <div class="col-12">
        <input type="number" step=".00000001" id="quantity-input" name="quantity"
            class="form-control"
            value="{{ !empty($quantity) ? $quantity + 0 : '' }}"
            placeholder="{{ trans('myfinance2::trades.forms.item-form.quantity'
                                  . '.placeholder') }}"
            required />
    </div>
    @if ($errors->has('quantity'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('quantity') }}</strong>
            </span>
        </div>
    @endif
</div>

