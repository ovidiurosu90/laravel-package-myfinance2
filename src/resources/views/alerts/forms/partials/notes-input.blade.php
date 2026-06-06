<div class="mb-3 has-feedback row {{ $errors->has('notes') ? 'has-error' : '' }}">
    <label for="notes" class="col-4 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.notes.label') }}
    </label>
    <div class="col-8 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="fetched-notes" style="display: none">
        <span></span>
    </div>
    <div class="col-12">
        <textarea name="notes" id="notes" class="form-control" rows="2"
            placeholder="{{ trans('myfinance2::alerts.forms.item-form.notes.placeholder') }}"
        >{{ old('notes', $notes) }}</textarea>
    </div>
    @if ($errors->has('notes'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('notes') }}</strong>
            </span>
        </div>
    @endif
</div>
