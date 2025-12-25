<div class="mb-3 required has-feedback row {{ $errors->has('action') ? ' has-error ' : '' }}">
    <label for="action" class="col-12 control-label">
        {{ trans('myfinance2::trades.forms.item-form.action.label') }}
    </label>
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

