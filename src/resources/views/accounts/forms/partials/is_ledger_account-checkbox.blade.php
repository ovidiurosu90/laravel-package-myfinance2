<div class="form-check required has-feedback row {{
    $errors->has('is_ledger_account') ? 'has-error' : '' }}">

    <div class="col-12">
        <input class="form-check-input" type="checkbox"
               id="is_ledger_account-checkbox" name="is_ledger_account"
               @if (!isset($is_ledger_account) || !empty($is_ledger_account))
               checked
               @endif
               value="1">
        <label class="form-check-label" for="is_ledger_account">
            {{ trans('myfinance2::accounts.forms.item-form.'
                     . 'is_ledger_account.label') }}
        </label>
    </div>

    @if ($errors->has('is_ledger_account'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('is_ledger_account') }}</strong>
        </span>
    </div>
    @endif
</div>

