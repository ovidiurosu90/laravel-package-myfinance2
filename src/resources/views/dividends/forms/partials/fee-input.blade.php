<div class="form-group required has-feedback row {{ $errors->has('fee') ? ' has-error ' : '' }}">
    <label for="fee" class="col-12 control-label">
        {{ trans('myfinance2::dividends.forms.item-form.fee.label') }}
        <span id="account_currency-label-tooltip" data-bs-toggle="tooltip" title="Account Currency">
            {!! $account_currency ? config('general.currencies_display.' . $account_currency) : '' !!}
        </span>
    </label>
    <div class="col-9">
        <input type="number" step=".01" id="fee" name="fee" class="form-control" value="{{ $fee }}" placeholder="{{ trans('myfinance2::dividends.forms.item-form.fee.placeholder') }}" required />
        <input type="number" step=".01" id="fee_dividend_currency" name="fee_dividend_currency" class="form-control" style="display: none;" value="{{ ($fee && $exchange_rate) ? round($fee * $exchange_rate, 2) : '' }}" />
    </div>
    <div class="col-3 p-0" id="fee_currency-toggle_container" data-bs-toggle="tooltip" title="Toggle between account and dividend currency if they are different">
        <input type="checkbox" checked id="fee_currency-toggle" data-bs-toggle="toggle"
            data-onstyle="primary"
            data-offstyle="info"
            data-size="normal" disabled
            data-onlabel="{!! $account_currency ? config('general.currencies_display.' . $account_currency) : 'x' !!}"
            data-offlabel="{!! $dividend_currency ? config('general.currencies_display.' . $dividend_currency) : 'x' !!}"
        />
    </div>
    @if ($errors->has('fee'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('fee') }}</strong>
            </span>
        </div>
    @endif
</div>

