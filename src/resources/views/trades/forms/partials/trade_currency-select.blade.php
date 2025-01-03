<div class="form-group required has-feedback row {{ $errors->has('trade_currency') ? ' has-error ' : '' }}">
    <label for="trade_currency" class="col-8 control-label">
        {{ trans('myfinance2::trades.forms.item-form.trade_currency.label') }}
    </label>
    <div class="col-4 p-0 m-0 pt-1 pr-3 text-muted text-right small" id="fetched-trade-currency" style="display: none">
        <span></span>
    </div>
    <div class="col-12">
        <select name="trade_currency" id="trade_currency-select" required>
            <option value="">{{ trans('myfinance2::trades.forms.item-form.trade_currency.placeholder') }}</option>
            @foreach ($tradeCurrencies as $tradeCurrencyKey => $tradeCurrencyValue)
                <option @if ($tradeCurrencyKey == $trade_currency) selected @endif value="{{ $tradeCurrencyKey }}">
                    {{ $tradeCurrencyValue }}
                </option>
            @endforeach
        </select>
    </div>
    @if ($errors->has('trade_currency'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('trade_currency') }}</strong>
            </span>
        </div>
    @endif
</div>

