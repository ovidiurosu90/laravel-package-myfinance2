<div class="form-check required has-feedback row {{
    $errors->has('is_dividend_currency') ? 'has-error' : '' }}">

    <div class="col-12">
        <input class="form-check-input" type="checkbox"
               id="is_dividend_currency-checkbox" name="is_dividend_currency"
               @if (!isset($is_dividend_currency) || !empty($is_dividend_currency))
               checked
               @endif
               value="1">
        <label class="form-check-label" for="is_dividend_currency">
            {{ trans('myfinance2::currencies.forms.item-form.'
                     . 'is_dividend_currency.label') }}
        </label>
    </div>

    @if ($errors->has('is_dividend_currency'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('is_dividend_currency') }}</strong>
        </span>
    </div>
    @endif
</div>

