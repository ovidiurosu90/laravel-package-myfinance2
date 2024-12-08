<div class="form-check required has-feedback row {{
    $errors->has('is_dividend_account') ? 'has-error' : '' }}">

    <div class="col-12">
        <input class="form-check-input" type="checkbox"
               id="is_dividend_account-checkbox" name="is_dividend_account"
               @if (!isset($is_dividend_account) || !empty($is_dividend_account))
               checked
               @endif
               value="1">
        <label class="form-check-label" for="is_dividend_account">
            {{ trans('myfinance2::accounts.forms.item-form.'
                     . 'is_dividend_account.label') }}
        </label>
    </div>

    @if ($errors->has('is_dividend_account'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('is_dividend_account') }}</strong>
        </span>
    </div>
    @endif
</div>

