<div class="form-group required has-feedback row {{ $errors->has('amount') ?
                                                    'has-error' : '' }}">
    <label for="amount" class="col-7 control-label">
        {{ trans('myfinance2::dividends.forms.item-form.amount.label') }}
        <span id="dividend_currency-label-tooltip" data-bs-toggle="tooltip"
            title="Dividend Currency">
            {!! !empty($dividendCurrencyModel) ? $dividendCurrencyModel->display_code
                                               : '' !!}
        </span>
    </label>
    <div class="col-5 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="fetched-unit-price" style="display: none">
        <span style="cursor: pointer" data-bs-toggle="tooltip" title=""></span>
    </div>
    <div class="col-12">
        <input type="number" step=".0001" id="amount" name="amount"
            class="form-control"
            value="{{ !empty($amount) ? $amount + 0 : '' }}"
            placeholder="{{ trans('myfinance2::dividends.forms.item-form.amount'
                                  . '.placeholder') }}" required />
    </div>
    @if ($errors->has('amount'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('amount') }}</strong>
            </span>
        </div>
    @endif
</div>

