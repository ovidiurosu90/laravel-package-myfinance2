<div class="form-check required has-feedback row {{
    $errors->has('is_ledger_currency') ? 'has-error' : '' }}">

    <div class="col-12">
        <input class="form-check-input" type="checkbox"
               id="is_ledger_currency-checkbox" name="is_ledger_currency"
               @if (!isset($is_ledger_currency) || !empty($is_ledger_currency))
               checked
               @endif
               value="1">
        <label class="form-check-label" for="is_ledger_currency">
            {{ trans('myfinance2::currencies.forms.item-form.'
                     . 'is_ledger_currency.label') }}
        </label>
    </div>

    @if ($errors->has('is_ledger_currency'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('is_ledger_currency') }}</strong>
        </span>
    </div>
    @endif
</div>

