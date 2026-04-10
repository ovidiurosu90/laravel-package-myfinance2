<div class="mb-3 has-feedback row {{ $errors->has('expires_at') ? 'has-error' : '' }}">
    <label for="expires_at" class="col-12 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.expires_at.label') }}
    </label>
    <div class="col-12">
        <div class="input-group date" id="expires-at-picker"
            data-td-target-input="nearest" data-td-target-toggle="nearest">
            <input name="expires_at" type="text"
                class="form-control datetimepicker-input"
                data-td-target="#expires-at-picker"
                value="{{ $expires_at }}" />
            <span class="input-group-text" data-td-target="#expires-at-picker"
                data-td-toggle="datetimepicker" role="button">
                <span class="fas fa-calendar"></span>
            </span>
        </div>
    </div>
    @if ($errors->has('expires_at'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('expires_at') }}</strong>
            </span>
        </div>
    @endif
</div>
