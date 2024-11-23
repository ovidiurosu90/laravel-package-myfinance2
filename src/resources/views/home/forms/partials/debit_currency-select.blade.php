<div class="form-group required has-feedback row">
    <label for="debit_currency" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.label') }}
    </label>
    <div class="col-12">
        <select name="debit_currency" id="transaction-debit_currency-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.placeholder') }}</option>
            @foreach ($debitCurrencies as $debitCurrencyKey => $debitCurrency)
                <option value="{{ $debitCurrencyKey }}">
                    {{ $debitCurrency }}
                </option>}
            @endforeach
        </select>
    </div>
</div>

