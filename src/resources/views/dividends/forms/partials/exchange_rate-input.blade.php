<div class="form-group required has-feedback row {{ $errors->has('exchange_rate')
                                                    ? 'has-error' : '' }}">
    <label for="exchange_rate" class="col-12 control-label">
        {{ trans('myfinance2::dividends.forms.item-form.exchange_rate.label') }}
    </label>
    <div class="col-12">
        <input type="number" step=".0001" id="exchange_rate" name="exchange_rate"
            class="form-control"
            value="{{ !empty($exchange_rate) ? $exchange_rate + 0 : '' }}"
            placeholder="{{ trans('myfinance2::dividends.forms.item-form.'
                                  . 'exchange_rate.placeholder') }}"
            required />
    </div>
    @if ($errors->has('exchange_rate'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('exchange_rate') }}</strong>
            </span>
        </div>
    @endif
</div>

