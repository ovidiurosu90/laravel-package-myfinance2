<div class="form-group required has-feedback row {{ $errors->has('credit_currency') ? ' has-error ' : '' }}">
    <label for="credit_currency" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.label') }}
    </label>
    <div class="col-10">
        <select name="credit_currency" id="transaction-credit_currency-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.placeholder') }}</option>
            @foreach ($creditCurrencies as $creditCurrencyKey => $creditCurrency)
                <option @if ($creditCurrencyKey == $credit_currency) selected @endif value="{{ $creditCurrencyKey }}">
                    {{ $creditCurrency }}
                </option>}
            @endforeach
        </select>
    </div>
    <div class="col-2 p-0 pt-1">
        <i class="btn p-0 m-0 fa fa-toggle-on" id="enable-transaction-credit_currency-select" data-bs-toggle="tooltip" title="{{ trans('myfinance2::ledger.tooltips.enable-transaction-credit_currency-select') }}" style="font-size: 24px;"></i>
    </div>
    @if ($errors->has('credit_currency'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('credit_currency') }}</strong>
            </span>
        </div>
    @endif
</div>

