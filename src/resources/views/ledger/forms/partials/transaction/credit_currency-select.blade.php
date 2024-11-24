<div class="form-group required has-feedback row {{ $errors->has('credit_currency') ? ' has-error ' : '' }}">
    <label for="credit_currency" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.label') }}
    </label>
    <div class="col-9">
        <select name="credit_currency" id="transaction-credit_currency-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.placeholder') }}</option>
            @foreach ($creditCurrencies as $creditCurrencyKey => $creditCurrency)
                <option @if ($creditCurrencyKey == $credit_currency) selected @endif value="{{ $creditCurrencyKey }}">
                    {{ $creditCurrency }}
                </option>}
            @endforeach
        </select>
    </div>
    <div class="col-3 p-0">
        <input id="toggle-transaction-credit_currency-select" type="checkbox" data-bs-toggle="toggle" data-ontitle="{{ trans('myfinance2::ledger.tooltips.disable-transaction-credit_currency-select') }}" data-offtitle="{{ trans('myfinance2::ledger.tooltips.enable-transaction-credit_currency-select') }}">
    </div>
    @if ($errors->has('credit_currency'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('credit_currency') }}</strong>
            </span>
        </div>
    @endif
</div>

