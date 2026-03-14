<div class="mb-3 has-feedback row {{ $errors->has('trade_currency_id') ? 'has-error' : '' }}">
    <label for="trade_currency-select" class="col-12 control-label">
        {{ trans('myfinance2::orders.forms.item-form.trade_currency.label') }}
        <span id="trade_currency-label-tooltip" data-bs-toggle="tooltip"
            title="Trade Currency">
            {!! !empty($tradeCurrencyModel) ? $tradeCurrencyModel->display_code : '' !!}
        </span>
    </label>
    <div class="col-12">
        <select name="trade_currency_id" id="trade_currency-select">
            <option value="">
                {{ trans('myfinance2::orders.forms.item-form.trade_currency.placeholder') }}
            </option>
            @foreach ($tradeCurrencies as $currency)
            <option @if ($trade_currency_id == $currency->id) selected @endif
                value="{{ $currency->id }}">
                {{ $currency->name }} ({!! $currency->display_code !!})
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
