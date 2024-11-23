<div class="form-group required has-feedback row {{ $errors->has('dividend_currency') ? ' has-error ' : '' }}">
    <label for="dividend_currency" class="col-8 control-label">
        {{ trans('myfinance2::dividends.forms.item-form.dividend_currency.label') }}
    </label>
    <div class="col-4 p-0 m-0 pt-1 pr-3 text-muted text-right small" id="fetched-dividend-currency" style="display: none">
        <span></span>
    </div>
    <div class="col-12">
        <select name="dividend_currency" id="dividend_currency-select" required>
            <option value="">{{ trans('myfinance2::dividends.forms.item-form.dividend_currency.placeholder') }}</option>
            @foreach ($dividendCurrencies as $dividendCurrencyKey => $dividendCurrencyValue)
                <option @if ($dividendCurrencyKey == $dividend_currency) selected @endif value="{{ $dividendCurrencyKey }}">
                    {{ $dividendCurrencyValue }}
                </option>}
            @endforeach
        </select>
    </div>
    @if ($errors->has('dividend_currency'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('dividend_currency') }}</strong>
            </span>
        </div>
    @endif
</div>

