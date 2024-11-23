<div class="form-group required has-feedback row {{ $errors->has('symbol') ? ' has-error ' : '' }}">
    <label for="symbol" class="col-4 control-label pr-2">
        {{ trans('myfinance2::watchlistsymbols.forms.item-form.symbol.label') }}
    </label>
    <div class="col-8 p-0 m-0 pt-1 pr-3 text-muted text-right small" id="fetched-symbol-name" style="display: none">
        <span></span>
    </div>

    <div class="col-10">
        <input type="text" id="symbol-input" name="symbol" class="form-control" value="{{ $symbol }}" placeholder="{{ trans('myfinance2::watchlistsymbols.forms.item-form.symbol.placeholder') }}" required maxlength="16" />
    </div>
    <div class="col-2 p-0 pt-1">
        <i class="btn p-0 m-0 fa fa-sign-in" id="get-finance-data" data-bs-toggle="tooltip" title="{{ trans('myfinance2::watchlistsymbols.tooltips.get-finance-data') }}" style="font-size: 24px;"></i>
    </div>

    @if ($errors->has('symbol'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('symbol') }}</strong>
            </span>
        </div>
    @endif
</div>

