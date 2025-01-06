<div class="form-group required has-feedback row
    {{ $errors->has('trade_currency_id') ? 'has-error' : '' }}">
    <label for="trade_currency-select" class="col-8 control-label">
        {{ trans('myfinance2::trades.forms.item-form.trade_currency.label') }}
    </label>
    <div class="col-4 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="fetched-trade-currency" style="display: none">
        <span></span>
    </div>
    <div class="col-12">
        <select name="trade_currency_id" id="trade_currency-select" required>
            <option value="">{{ trans('myfinance2::trades.forms.item-form.'
                                      . 'trade_currency.placeholder') }}</option>
            @foreach ($tradeCurrencies as $tradeCurrency)
            <option @if ($trade_currency_id == $tradeCurrency->id)
                selected @endif
                value="{{ $tradeCurrency->id }}">
                {{ $tradeCurrency->name }}
                ({!! $tradeCurrency->display_code !!})
            </option>
            @endforeach
        </select>
    </div>
    @if ($errors->has('trade_currency_id'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('trade_currency_id') }}</strong>
            </span>
        </div>
    @endif
</div>

