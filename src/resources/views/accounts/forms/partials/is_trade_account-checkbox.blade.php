<div class="form-check required has-feedback row {{
    $errors->has('is_trade_account') ? 'has-error' : '' }}">

    <div class="col-12">
        <input class="form-check-input" type="checkbox"
               id="is_trade_account-checkbox" name="is_trade_account"
               @if (!isset($is_trade_account) || !empty($is_trade_account))
               checked
               @endif
               value="1">
        <label class="form-check-label" for="is_trade_account">
            {{ trans('myfinance2::accounts.forms.item-form.'
                     . 'is_trade_account.label') }}
        </label>
    </div>

    @if ($errors->has('is_trade_account'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('is_trade_account') }}</strong>
        </span>
    </div>
    @endif
</div>

