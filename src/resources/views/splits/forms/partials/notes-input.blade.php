<div class="mb-3 has-feedback row {{ $errors->has('notes') ? 'has-error' : '' }}">
    <label for="notes" class="col-12 control-label">
        {{ trans('myfinance2::splits.forms.item-form.notes.label') }}
    </label>
    <div class="col-12">
        <textarea name="notes" id="notes"
            class="form-control"
            rows="2"
            placeholder="{{ trans('myfinance2::splits.forms.item-form.notes.placeholder') }}"
            >{{ old('notes', $notes ?? '') }}</textarea>
    </div>
    @if ($errors->has('notes'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('notes') }}</strong>
            </span>
        </div>
    @endif
</div>
