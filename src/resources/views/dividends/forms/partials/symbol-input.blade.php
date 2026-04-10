<div class="mb-3 required has-feedback row {{ $errors->has('symbol') ?
                                                    'has-error' : '' }}">
    <label for="symbol" class="col-5 control-label">
        {{ trans('myfinance2::dividends.forms.item-form.symbol.label') }}
    </label>
    <div class="col-7 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="fetched-symbol-name" style="display: none">
        <span></span>
    </div>
    <div class="col-12">
        <div class="input-group">
            <input type="text" id="symbol-input" name="symbol" class="form-control"
                value="{{ $symbol }}"
                placeholder="{{ trans('myfinance2::dividends.forms.item-form.symbol.'
                                      . 'placeholder') }}"
                required maxlength="16"
                oninput="this.value = this.value.toUpperCase()"
            />
            <div class="input-group-append" id="get-finance-data" role="button"
                data-bs-toggle="tooltip"
                title="{{ trans('myfinance2::dividends.tooltips.get-finance-data') }}">
                <span class="input-group-text">
                    <span class="fas fa-sign-in"></span>
                </span>
            </div>
        </div>
    </div>
    @if ($errors->has('symbol'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('symbol') }}</strong>
            </span>
        </div>
    @endif
</div>

