<div class="mb-3 has-feedback row {{ $errors->has('trade_currency_id') ? 'has-error' : '' }}">
    <label for="trade_currency-select" class="col-12 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.trade_currency.label') }}
    </label>
    <div class="col-12">
        <select name="trade_currency_id" id="trade_currency-select">
            <option value="">
                {{ trans('myfinance2::alerts.forms.item-form.trade_currency.placeholder') }}
            </option>
            @foreach ($tradeCurrencies as $currency)
            <option @if ($trade_currency_id == $currency->id) selected @endif
                value="{{ $currency->id }}">
                {!! $currency->display_code !!} — {{ $currency->name }}
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
