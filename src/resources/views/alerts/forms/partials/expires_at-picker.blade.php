<div class="mb-3 has-feedback row {{ $errors->has('expires_at') ? 'has-error' : '' }}">
    <label for="expires_at" class="col-12 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.expires_at.label') }}
    </label>
    <div class="col-12">
        <input type="datetime-local" name="expires_at" id="expires_at"
            class="form-control"
            value="{{ old('expires_at', !empty($expires_at)
                ? \Carbon\Carbon::parse($expires_at)->format('Y-m-d\TH:i')
                : '') }}">
    </div>
    @if ($errors->has('expires_at'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('expires_at') }}</strong>
            </span>
        </div>
    @endif
</div>
