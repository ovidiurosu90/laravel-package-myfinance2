<div class="form-group required has-feedback row {{ $errors->has('debit_currency') ? ' has-error ' : '' }}">
    <label for="debit_currency" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.label') }}
    </label>
    <div class="col-9">
        <select name="debit_currency" id="transaction-debit_currency-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.placeholder') }}</option>
            @foreach ($debitCurrencies as $debitCurrencyKey => $debitCurrency)
                <option @if ($debitCurrencyKey == $debit_currency) selected @endif value="{{ $debitCurrencyKey }}">
                    {{ $debitCurrency }}
                </option>}
            @endforeach
        </select>
    </div>
    <div class="col-3 p-0">
        <input id="toggle-transaction-debit_currency-select" type="checkbox" data-bs-toggle="toggle" data-ontitle="{{ trans('myfinance2::ledger.tooltips.disable-transaction-debit_currency-select') }}" data-offtitle="{{ trans('myfinance2::ledger.tooltips.enable-transaction-debit_currency-select') }}">
    </div>
    @if ($errors->has('debit_currency'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('debit_currency') }}</strong>
            </span>
        </div>
    @endif
</div>

