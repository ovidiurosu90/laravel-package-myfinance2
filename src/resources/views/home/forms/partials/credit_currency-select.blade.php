<div class="form-group required has-feedback row">
    <label for="credit_currency" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.label') }}
    </label>
    <div class="col-12">
        <select name="credit_currency" id="transaction-credit_currency-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.placeholder') }}</option>
            @foreach ($creditCurrencies as $creditCurrencyKey => $creditCurrency)
                <option value="{{ $creditCurrencyKey }}">
                    {{ $creditCurrency }}
                </option>}
            @endforeach
        </select>
    </div>
</div>

