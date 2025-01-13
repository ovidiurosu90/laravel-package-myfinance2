<div class="form-group required has-feedback row {{ $errors->has('exchange_rate') ?
                                                    'has-error' : '' }}">
    <label for="exchange_rate" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.exchange_rate.label') }}
    </label>
    <div class="col-9">
        <input type="number" step=".0001" id="exchange_rate" name="exchange_rate"
            class="form-control"
            value="{{ $exchange_rate ? @number_format($exchange_rate, 4) : '' }}"
            placeholder="{{ trans('myfinance2::ledger.forms.transaction-form.'
                                  . 'exchange_rate.placeholder') }}" required />
    </div>
    <div class="col-3 p-0">
        <input id="toggle-transaction-exchange_rate-input" type="checkbox"
            data-bs-toggle="toggle"
            data-ontitle="{{ trans('myfinance2::ledger.tooltips.'
                                   . 'disable-transaction-exchange_rate-input') }}"
            data-offtitle="{{ trans('myfinance2::ledger.tooltips.'
                                   . 'enable-transaction-exchange_rate-input') }}">
    </div>
    @if ($errors->has('exchange_rate'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('exchange_rate') }}</strong>
            </span>
        </div>
    @endif
</div>

