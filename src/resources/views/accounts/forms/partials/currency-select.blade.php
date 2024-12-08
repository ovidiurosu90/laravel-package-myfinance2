<div class="form-group required has-feedback row {{ $errors->has('currency_id') ?
                                                    'has-error' : '' }}">

    <label for="currency-select" class="col-12 control-label">
        {{ trans('myfinance2::accounts.forms.item-form.currency.label') }}
    </label>

    <div class="col-12">
        <select name="currency_id" id="currency-select" required>
            <option value="">{{ trans('myfinance2::accounts.forms.item-form.'
                                      . 'currency.placeholder') }}</option>

            @foreach ($currencies as $currency)
            <option @if ($currency_id == $currency->id) selected @endif
                value="{{ $currency->id }}">
                {{ $currency->name }} ({!! $currency->display_code !!})
            </option>}
            @endforeach
        </select>
    </div>

    @if ($errors->has('currency_id'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('currency_id') }}</strong>
            </span>
        </div>
    @endif
</div>

