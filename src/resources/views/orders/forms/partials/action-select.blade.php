<div class="mb-3 required has-feedback row {{ $errors->has('action') ? 'has-error' : '' }}">
    <label for="action-select" class="col-12 control-label">
        {{ trans('myfinance2::orders.forms.item-form.action.label') }}
    </label>
    <div class="col-12">
        <select name="action" id="action-select" required>
            <option value="">
                {{ trans('myfinance2::orders.forms.item-form.action.placeholder') }}
            </option>
            <option @if ($action == 'BUY') selected @endif value="BUY">Buy</option>
            <option @if ($action == 'SELL') selected @endif value="SELL">Sell</option>
        </select>
    </div>
    @if ($errors->has('action'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('action') }}</strong>
            </span>
        </div>
    @endif
</div>
