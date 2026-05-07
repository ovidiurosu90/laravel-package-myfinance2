@use('ovidiuro\myfinance2\App\Services\FinanceAPI')
<div class="mb-3 required has-feedback row {{ $errors->has('symbol') ? 'has-error' : '' }}">
    <label for="symbol-input" class="col-5 control-label">
        {{ trans('myfinance2::general.forms.symbol.label') }}
    </label>
    <div class="col-7 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="fetched-symbol-name" style="display: none">
        <span></span>
    </div>
    <div class="col-12">
        <div class="input-group">
            <input type="text" id="symbol-input" name="symbol" class="form-control"
                value="{{ $symbol ?? '' }}"
                placeholder="{{ trans('myfinance2::general.forms.symbol.placeholder') }}"
                required maxlength="16"
                oninput="this.value = this.value.toUpperCase()"
            />
            @if ($showListedToggle ?? false)
            <button id="is-listed" class="btn btn-outline-secondary" type="button">{{
                FinanceAPI::isUnlisted($symbol ?? '') ? 'Unlisted' : 'Listed'
            }}</button>
            @endif
            @include('myfinance2::general.partials.get-finance-data-button')
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
