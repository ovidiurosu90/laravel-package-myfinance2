<div class="form-group required has-feedback row {{ $errors->has('account_currency') ? ' has-error ' : '' }}">
    <label for="account_currency" class="col-12 control-label">
        {{ trans('myfinance2::cashbalances.forms.item-form.account_currency.label') }}
    </label>
    <div class="col-12">
        <select name="account_currency" id="account_currency-select" required>
            <option value="">{{ trans('myfinance2::cashbalances.forms.item-form.account_currency.placeholder') }}</option>
            @foreach ($accountCurrencies as $accountCurrencyKey => $accountCurrencyValue)
                <option @if ($accountCurrencyKey == $account_currency) selected @endif value="{{ $accountCurrencyKey }}">
                    {{ $accountCurrencyValue }}
                </option>}
            @endforeach
        </select>
    </div>
    @if ($errors->has('account_currency'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('account_currency') }}</strong>
            </span>
        </div>
    @endif
</div>

