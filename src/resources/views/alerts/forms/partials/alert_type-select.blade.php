<div class="mb-3 required has-feedback row {{ $errors->has('alert_type') ? 'has-error' : '' }}">
    <label for="alert_type-select" class="col-12 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.alert_type.label') }}
    </label>
    <div class="col-12">
        <select name="alert_type" id="alert_type-select" required>
            <option value="">
                {{ trans('myfinance2::alerts.forms.item-form.alert_type.placeholder') }}
            </option>
            @foreach ($alertTypes as $value => $label)
            <option @if ($alert_type == $value) selected @endif value="{{ $value }}">
                {{ $label }}
            </option>
            @endforeach
        </select>
    </div>
    @if ($errors->has('alert_type'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('alert_type') }}</strong>
            </span>
        </div>
    @endif
</div>
