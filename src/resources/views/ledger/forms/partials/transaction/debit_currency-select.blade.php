<div class="form-group required has-feedback row {{ $errors->has('debit_currency') ? ' has-error ' : '' }}">
    <label for="debit_currency" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.label') }}
    </label>
    <div class="col-10">
        <select name="debit_currency" id="transaction-debit_currency-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.placeholder') }}</option>
            @foreach ($debitCurrencies as $debitCurrencyKey => $debitCurrency)
                <option @if ($debitCurrencyKey == $debit_currency) selected @endif value="{{ $debitCurrencyKey }}">
                    {{ $debitCurrency }}
                </option>}
            @endforeach
        </select>
    </div>
    <div class="col-2 p-0 pt-1">
        <i class="btn p-0 m-0 fa fa-toggle-on" id="enable-transaction-debit_currency-select" data-bs-toggle="tooltip" title="{{ trans('myfinance2::ledger.tooltips.enable-transaction-debit_currency-select') }}" style="font-size: 24px;"></i>
    </div>
    @if ($errors->has('debit_currency'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('debit_currency') }}</strong>
            </span>
        </div>
    @endif
</div>

