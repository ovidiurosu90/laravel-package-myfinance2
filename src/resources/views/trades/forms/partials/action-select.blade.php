<div class="mb-3 required has-feedback row {{ $errors->has('action') ? ' has-error ' : '' }}">
    <div class="col-12 d-flex justify-content-between
                align-items-center mb-2">
        <label for="action" class="control-label mb-0">
            {{ trans('myfinance2::trades.forms.item-form.action.label') }}
        </label>
        <div class="form-check mb-0">
            <input type="hidden" name="is_transfer" value="0">
            <input class="form-check-input" type="checkbox"
                name="is_transfer" id="is_transfer"
                value="1"
                {{ old('is_transfer', $is_transfer ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_transfer"
                style="font-weight: normal;">
                <i class="fa-solid fa-shuffle me-1"
                    style="font-size: 0.8rem;
                           color: #6c757d;"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    data-bs-title="Transfer trades move positions
                        between accounts without affecting cash
                        balances. In returns calculations, they are
                        treated as deposits (BUY) or withdrawals
                        (SELL)."></i>
                Is Transfer
            </label>
        </div>
    </div>
    <div class="col-12">
        <select name="action" id="action-select" required>
            <option value="">{{ trans('myfinance2::trades.forms.item-form.action.placeholder') }}</option>
            @foreach ($actions as $actionKey => $actionValue)
                <option @if ($actionKey == $action) selected @endif value="{{ $actionKey }}">
                    {{ $actionValue }}
                </option>
            @endforeach
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

