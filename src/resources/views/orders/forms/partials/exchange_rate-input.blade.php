<div class="mb-3 has-feedback row {{ $errors->has('exchange_rate') ? 'has-error' : '' }}">
    <label for="exchange_rate" class="col-7 control-label">
        {{ trans('myfinance2::orders.forms.item-form.exchange_rate.label') }}
        <span id="account_currency-label-tooltip" data-bs-toggle="tooltip"
            title="Account Currency">
            {!! !empty($accountModel) ? $accountModel->currency->display_code : '' !!}
        </span>
    </label>
    <div class="col-12">
        <input type="number" step=".0001" id="exchange_rate" name="exchange_rate"
            class="form-control"
            value="{{ !empty($exchange_rate) ? $exchange_rate + 0 : '' }}"
            placeholder="{{ trans('myfinance2::orders.forms.item-form.exchange_rate'
                                  . '.placeholder') }}" />
    </div>
    @if ($errors->has('exchange_rate'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('exchange_rate') }}</strong>
            </span>
        </div>
    @endif
</div>
