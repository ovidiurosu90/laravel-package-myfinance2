<div class="mb-3 required has-feedback row {{ $errors->has('split_date') ? 'has-error' : '' }}">
    <label for="split_date" class="col-12 control-label">
        {{ trans('myfinance2::splits.forms.item-form.split_date.label') }}
    </label>
    <div class="col-12">
        <div class="input-group date" id="split-date-picker"
            data-td-target-input="nearest" data-td-target-toggle="nearest">
            <input name="split_date" type="text"
                class="form-control datetimepicker-input"
                data-td-target="#split-date-picker"
                value="{{ old('split_date', $split_date ?? '') }}"
                required />
            <span class="input-group-text" data-td-target="#split-date-picker"
                data-td-toggle="datetimepicker" role="button">
                <span class="fas fa-calendar"></span>
            </span>
        </div>
    </div>
    @if ($errors->has('split_date'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('split_date') }}</strong>
            </span>
        </div>
    @endif
</div>
