<div class="form-check required has-feedback row {{
    $errors->has('is_trade_currency') ? 'has-error' : '' }}">

    <div class="col-12">
        <input class="form-check-input" type="checkbox"
               id="is_trade_currency-checkbox" name="is_trade_currency"
               @if (!isset($is_trade_currency) || !empty($is_trade_currency))
               checked
               @endif
               value="1">
        <label class="form-check-label" for="is_trade_currency">
            {{ trans('myfinance2::currencies.forms.item-form.'
                     . 'is_trade_currency.label') }}
        </label>
    </div>

    @if ($errors->has('is_trade_currency'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('is_trade_currency') }}</strong>
        </span>
    </div>
    @endif
</div>

