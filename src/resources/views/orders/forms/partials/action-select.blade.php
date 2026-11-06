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
        {{-- Shown while no side is set (orders/scripts/action-pick): the form never picks one by itself. --}}
        <div id="action-pick" class="small mt-1" style="display:none">
            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" data-side="BUY">Buy</button>
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" data-side="SELL">Sell</button>
            <span id="action-pick-hint" class="text-muted ms-1"></span>
        </div>
    </div>
    @if ($errors->has('action'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('action') }}</strong>
            </span>
        </div>
    @endif
</div>
